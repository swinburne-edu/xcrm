<?php

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Activity\Models\Appointment;
use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Actions\AssignCase;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Actions\MoveCaseStage;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\CallResult;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    app(SeedOrganizationCatalogs::class)->handle($this->other);

    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->makeCase = fn (array $data = [], ?User $owner = null) => app(CreateAdmissionCase::class)->handle(
        Applicant::factory()->for($this->org)->create(), $this->cycle, $data + ['owner_id' => ($owner ?? $this->admin)->id], $this->admin,
    );
    $this->counselor = function (): User {
        $user = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        $user->assignRole('counselor');

        return $user;
    };
    $this->result = fn (string $name) => CallResult::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->where('name', $name)->firstOrFail();
});

describe('observer entries', function () {
    it('writes a system entry when a case is created, and one entry per stage change and owner change', function () {
        $case = ($this->makeCase)();
        $stage = PipelineStage::query()->where('pipeline_id', $case->pipeline_id)->where('code', 'counseling')->firstOrFail();
        $newOwner = ($this->counselor)();

        $this->actingAs($this->admin);
        app(MoveCaseStage::class)->handle($case, $stage, $this->admin);
        app(AssignCase::class)->handle($case->refresh(), $newOwner, null, $this->admin);

        $entries = Activity::query()->where('subject_type', 'admission_case')->where('subject_id', $case->id)->get();

        expect($entries->pluck('type.value')->all())->toContain('system', 'stage_changed', 'owner_changed')
            ->and($entries->firstWhere('type', ActivityType::StageChanged)->title)->toContain('Đang tư vấn');
    });
});

describe('timeline entries', function () {
    it('logs a note on a case and moves last_activity_at', function () {
        $case = ($this->makeCase)();

        $this->actingAs($this->admin)->postJson('/timeline', ['subject_type' => 'case', 'subject' => $case->uuid, 'type' => 'note', 'body' => 'Phụ huynh quan tâm ngành CNTT'])
            ->assertCreated()->assertJsonPath('entry.type', 'note')->assertJsonPath('entry.user_name', $this->admin->name);

        expect($case->refresh()->last_activity_at)->not->toBeNull();
    });

    it('requires a body for notes but not for calls', function () {
        $case = ($this->makeCase)();
        $base = ['subject_type' => 'case', 'subject' => $case->uuid];

        $this->actingAs($this->admin)->postJson('/timeline', $base + ['type' => 'note'])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAs($this->admin)->postJson('/timeline', $base + ['type' => 'call', 'call_result_id' => ($this->result)('Không nghe máy')->id])->assertCreated();
    });

    it('does not let people write system entries', function () {
        $case = ($this->makeCase)();

        $this->actingAs($this->admin)->postJson('/timeline', ['subject_type' => 'case', 'subject' => $case->uuid, 'type' => 'stage_changed', 'body' => 'x'])->assertUnprocessable();
    });

    it('a reached call sets first_contacted_at once and moves a new case to "contacted"', function () {
        $case = ($this->makeCase)();
        $payload = ['subject_type' => 'case', 'subject' => $case->uuid, 'type' => 'call', 'call_result_id' => ($this->result)('Quan tâm')->id];

        $this->actingAs($this->admin)->postJson('/timeline', $payload)->assertCreated();

        $case->refresh()->load('stage');
        $first = $case->first_contacted_at;

        expect($first)->not->toBeNull()->and($case->stage->code)->toBe('contacted');

        $this->travel(2)->hours();
        $this->actingAs($this->admin)->postJson('/timeline', $payload)->assertCreated();

        expect($case->refresh()->first_contacted_at->equalTo($first))->toBeTrue();
    });

    it('a call nobody answered does not count as first contact', function () {
        $case = ($this->makeCase)();

        $this->actingAs($this->admin)->postJson('/timeline', ['subject_type' => 'case', 'subject' => $case->uuid, 'type' => 'call', 'call_result_id' => ($this->result)('Không nghe máy')->id])->assertCreated();

        expect($case->refresh()->first_contacted_at)->toBeNull();
    });

    it('a call with a callback time books a "gọi lại" task for the case', function () {
        $case = ($this->makeCase)();

        $this->actingAs($this->admin)->postJson('/timeline', ['subject_type' => 'case', 'subject' => $case->uuid, 'type' => 'call', 'call_result_id' => ($this->result)('Hẹn gọi lại')->id, 'callback_at' => '2026-12-01T09:30'])->assertCreated();

        $task = Task::query()->where('subject_type', 'admission_case')->where('subject_id', $case->id)->firstOrFail();

        expect($task->type->value)->toBe('call_back')->and($task->assignee_id)->toBe($this->admin->id)
            ->and($case->refresh()->next_action_at->format('Y-m-d H:i'))->toBe('2026-12-01 02:30');
    });

    it('an entry typed on an applicant lands on their case of the current cycle, and the applicant timeline shows it', function () {
        $case = ($this->makeCase)();
        $applicant = $case->applicant;

        $this->actingAs($this->admin)->postJson('/timeline', ['subject_type' => 'applicant', 'subject' => $applicant->uuid, 'type' => 'note', 'body' => 'Gọi cho mẹ'])->assertCreated();

        expect(Activity::query()->where('subject_type', 'admission_case')->where('subject_id', $case->id)->where('type', 'note')->exists())->toBeTrue();

        $this->actingAs($this->admin)->getJson("/timeline?subject_type=applicant&subject={$applicant->uuid}")
            ->assertOk()->assertJsonFragment(['body' => 'Gọi cho mẹ']);
    });

    it('lists entries newest first with paging and the open tasks of the record', function () {
        $case = ($this->makeCase)();

        foreach (range(1, 25) as $i) {
            Activity::factory()->create(['organization_id' => $this->org->id, 'subject_type' => 'admission_case', 'subject_id' => $case->id, 'type' => 'note', 'body' => "Ghi chú {$i}", 'occurred_at' => now()->addMinutes($i)]);
        }

        Task::factory()->create(['organization_id' => $this->org->id, 'subject_type' => 'admission_case', 'subject_id' => $case->id, 'assignee_id' => $this->admin->id]);

        $page1 = $this->actingAs($this->admin)->getJson("/timeline?subject_type=case&subject={$case->uuid}")->assertOk()
            ->assertJsonPath('has_more', true)->assertJsonPath('entries.0.body', 'Ghi chú 25')->assertJsonCount(1, 'tasks');
        $last = collect($page1->json('entries'))->last()['id'];

        $this->actingAs($this->admin)->getJson("/timeline?subject_type=case&subject={$case->uuid}&before={$last}")->assertOk()->assertJsonPath('has_more', false);
    });

    it('shows the school timeline together with its contacts', function () {
        $school = HighSchool::factory()->for($this->org)->create();
        $this->actingAs($this->admin)->postJson('/timeline', ['subject_type' => 'high_school', 'subject' => (string) $school->id, 'type' => 'meeting', 'body' => 'Gặp ban giám hiệu'])->assertCreated();

        $this->getJson("/timeline?subject_type=high_school&subject={$school->id}")->assertOk()->assertJsonPath('entries.0.body', 'Gặp ban giám hiệu');
    });

    it('hides records outside the data scope and other organizations', function () {
        $counselor = ($this->counselor)();
        $mine = ($this->makeCase)([], $counselor);
        $notMine = ($this->makeCase)();
        $foreign = app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->other)->create(), AdmissionCycle::factory()->for($this->other)->current()->create());

        $this->actingAs($counselor);
        $this->getJson("/timeline?subject_type=case&subject={$mine->uuid}")->assertOk()->assertJsonPath('can.write', true);
        $this->getJson("/timeline?subject_type=case&subject={$notMine->uuid}")->assertNotFound();
        $this->getJson("/timeline?subject_type=case&subject={$foreign->uuid}")->assertNotFound();
        $this->postJson('/timeline', ['subject_type' => 'case', 'subject' => $notMine->uuid, 'type' => 'note', 'body' => 'x'])->assertNotFound();
    });

    it('refuses notes on a case of a closed cycle', function () {
        $case = ($this->makeCase)();
        $this->cycle->update(['status' => 'closed']);

        $this->actingAs($this->admin)->postJson('/timeline', ['subject_type' => 'case', 'subject' => $case->uuid, 'type' => 'note', 'body' => 'x'])->assertForbidden();
    });
});

describe('tasks', function () {
    it('creates a task for a case, completes it and keeps next_action_at in step', function () {
        $case = ($this->makeCase)();
        $this->actingAs($this->admin);

        $this->post('/tasks', ['title' => 'Gọi tư vấn', 'type' => 'call_back', 'due_at' => '2026-12-02T10:00', 'subject_type' => 'case', 'subject' => $case->uuid])->assertRedirect();
        $this->post('/tasks', ['title' => 'Gửi hồ sơ', 'due_at' => '2026-12-05T10:00', 'subject_type' => 'case', 'subject' => $case->uuid])->assertRedirect();

        $first = Task::query()->where('title', 'Gọi tư vấn')->firstOrFail();

        expect($first->assignee_id)->toBe($this->admin->id)->and($case->refresh()->next_action_at->format('Y-m-d H:i'))->toBe('2026-12-02 03:00');

        $this->post("/tasks/{$first->id}/status", ['status' => 'done'])->assertRedirect();

        expect($first->refresh()->status->value)->toBe('done')->and($first->completed_at)->not->toBeNull()
            ->and($case->refresh()->next_action_at->format('Y-m-d H:i'))->toBe('2026-12-05 03:00');

        $this->delete('/tasks/'.Task::query()->where('title', 'Gửi hồ sơ')->value('id'))->assertRedirect();

        expect($case->refresh()->next_action_at)->toBeNull();
    });

    it('splits the list into today, overdue, upcoming and done', function () {
        $this->travelTo(now()->setTime(12, 0));

        $make = fn (string $title, $due, string $status = 'todo') => Task::factory()->create(['organization_id' => $this->org->id, 'assignee_id' => $this->admin->id, 'title' => $title, 'due_at' => $due, 'status' => $status]);
        $make('hôm qua', now()->subDay());
        $make('hôm nay', now()->addHour());
        $make('ngày mai', now()->addDay());
        $make('không hạn', null);
        $make('xong', now(), 'done');

        $titles = function (string $view): array {
            $found = [];
            $this->actingAs($this->admin)->get("/tasks?view={$view}")->assertInertia(function (Assert $page) use (&$found) {
                $found = collect($page->toArray()['props']['tasks']['data'])->pluck('title')->all();
            });

            return $found;
        };

        expect($titles('today'))->toBe(['hôm nay'])->and($titles('overdue'))->toBe(['hôm qua'])
            ->and($titles('upcoming'))->toEqualCanonicalizing(['ngày mai', 'không hạn'])->and($titles('done'))->toBe(['xong']);

        $this->get('/tasks')->assertInertia(fn (Assert $page) => $page->component('Tasks/Index')->where('view', 'today')->where('counts.overdue', 1)->where('counts.upcoming', 2)->where('counts.done', 1));
    });

    it('a counselor sees only their own tasks and cannot change someone else\'s', function () {
        $counselor = ($this->counselor)();
        $mine = Task::factory()->create(['organization_id' => $this->org->id, 'assignee_id' => $counselor->id, 'due_at' => now()]);
        $theirs = Task::factory()->create(['organization_id' => $this->org->id, 'assignee_id' => $this->admin->id, 'due_at' => now()]);
        Task::factory()->create(['organization_id' => $this->other->id, 'due_at' => now()]);

        $this->actingAs($counselor)->get('/tasks?view=today')->assertInertia(fn (Assert $page) => $page->has('tasks.data', 1)->where('tasks.data.0.id', $mine->id));
        $this->post("/tasks/{$theirs->id}/status", ['status' => 'done'])->assertForbidden();
        $this->post("/tasks/{$mine->id}/status", ['status' => 'done'])->assertRedirect();
    });

    it('validates the task form', function () {
        $this->actingAs($this->admin)->postJson('/tasks', ['title' => ''])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson('/tasks', ['title' => 'x', 'priority' => 'zzz'])->assertUnprocessable()->assertJsonValidationErrors('priority');
        $this->postJson('/tasks', ['title' => 'x', 'assignee_id' => User::factory()->for($this->other)->create()->id])->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
    });
});

describe('appointments', function () {
    it('books an appointment for a case, notes it on the timeline and lists it', function () {
        $case = ($this->makeCase)();
        $this->actingAs($this->admin);

        $this->post('/appointments', ['case_id' => $case->id, 'type' => 'campus_tour', 'start_at' => '2026-12-10T09:00', 'end_at' => '2026-12-10T10:00', 'location' => 'Cơ sở Hoà Lạc'])->assertRedirect();

        $appointment = Appointment::query()->firstOrFail();

        expect($appointment->start_at->format('Y-m-d H:i'))->toBe('2026-12-10 02:00')->and($appointment->host_id)->toBe($this->admin->id)
            ->and(Activity::query()->where('subject_type', 'admission_case')->where('subject_id', $case->id)->where('type', 'meeting')->count())->toBe(1);

        $this->get('/appointments?view=all')->assertInertia(fn (Assert $page) => $page->component('Appointments/Index')->has('appointments.data', 1)
            ->where('appointments.data.0.start_input', '2026-12-10T09:00')->where('appointments.data.0.applicant_name', $case->applicant->full_name));
    });

    it('rejects an end time before the start', function () {
        $case = ($this->makeCase)();

        $this->actingAs($this->admin)->postJson('/appointments', ['case_id' => $case->id, 'type' => 'counseling', 'start_at' => '2026-12-10T09:00', 'end_at' => '2026-12-10T08:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('end_at');
    });

    it('records attendance once and then locks the appointment', function () {
        $appointment = Appointment::factory()->create(['organization_id' => $this->org->id, 'case_id' => ($this->makeCase)()->id]);
        $this->actingAs($this->admin);

        $this->post("/appointments/{$appointment->id}/attendance", ['attendance' => 'attended', 'result' => 'Rất hứng thú'])->assertRedirect();

        expect($appointment->refresh()->attendance->value)->toBe('attended')->and($appointment->result)->toBe('Rất hứng thú');

        $this->postJson("/appointments/{$appointment->id}/attendance", ['attendance' => 'no_show'])->assertUnprocessable();
        $this->putJson("/appointments/{$appointment->id}", ['type' => 'counseling', 'start_at' => '2026-12-12T09:00'])->assertUnprocessable();
    });

    it('splits the list into upcoming and past', function () {
        $case = ($this->makeCase)();
        Appointment::factory()->create(['organization_id' => $this->org->id, 'case_id' => $case->id, 'start_at' => now()->addDays(2)]);
        Appointment::factory()->create(['organization_id' => $this->org->id, 'case_id' => $case->id, 'start_at' => now()->subDays(3), 'attendance' => 'attended']);

        $this->actingAs($this->admin)->get('/appointments')->assertInertia(fn (Assert $page) => $page->where('view', 'upcoming')->has('appointments.data', 1));
        $this->get('/appointments?view=past')->assertInertia(fn (Assert $page) => $page->has('appointments.data', 1)->where('appointments.data.0.attendance', 'attended'));
    });

    it('a counselor sees and books appointments only for their own cases', function () {
        $counselor = ($this->counselor)();
        $mine = ($this->makeCase)([], $counselor);
        $notMine = ($this->makeCase)();
        Appointment::factory()->create(['organization_id' => $this->org->id, 'case_id' => $mine->id]);
        $hidden = Appointment::factory()->create(['organization_id' => $this->org->id, 'case_id' => $notMine->id]);

        $this->actingAs($counselor)->get('/appointments?view=all')->assertInertia(fn (Assert $page) => $page->has('appointments.data', 1)->where('appointments.data.0.case_id', $mine->id));
        $this->post('/appointments', ['case_id' => $notMine->id, 'type' => 'counseling', 'start_at' => '2026-12-10T09:00'])->assertForbidden();
        $this->post("/appointments/{$hidden->id}/attendance", ['attendance' => 'attended'])->assertForbidden();
    });
});

describe('navigation', function () {
    it('serves the task and appointment pages from the menu without the "under construction" placeholder', function () {
        $this->actingAs($this->admin)->get('/tasks')->assertInertia(fn (Assert $page) => $page->component('Tasks/Index'));
        $this->get('/appointments')->assertInertia(fn (Assert $page) => $page->component('Appointments/Index'));
    });
});
