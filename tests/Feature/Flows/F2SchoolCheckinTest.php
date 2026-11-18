<?php

use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\CaseTouch;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\ActivityAttendee;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\HighSchool\Services\SchoolStatistics;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Models\TerritoryAssignment;
use App\Domain\Platform\Models\AssignmentRule;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Flow F2 (docs/05): activity at a school → QR check-in → applicant + case + touch + attendee → owner by territory.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create(['name' => 'Đại học Demo']);
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->officer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Territory]);
    $this->officer->assignRole('school_officer');

    $this->area = PriorityArea::query()->where('code', 'KV1')->first();
    $this->school = HighSchool::factory()->for($this->org)->create(['name' => 'THPT Chu Văn An', 'priority_area_id' => $this->area?->id]);
    $territory = Territory::factory()->for($this->org)->create(['user_id' => $this->officer->id]);
    TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'high_school', 'assignable_id' => $this->school->id]);
    $this->campaign = Campaign::factory()->for($this->org)->create(['cycle_id' => $this->cycle->id]);
    $this->major = Major::factory()->for($this->org)->create(['name' => 'Công nghệ thông tin']);

    $this->activity = SchoolActivity::factory()->create([
        'organization_id' => $this->org->id, 'cycle_id' => $this->cycle->id, 'high_school_id' => $this->school->id, 'campaign_id' => $this->campaign->id,
        'type' => 'school_counseling', 'start_at' => now()->subHour(), 'end_at' => now()->addHours(2), 'title' => 'Tư vấn tại THPT Chu Văn An',
    ]);
    $this->form = fn (array $extra = []) => $extra + ['full_name' => 'Nguyễn Văn An', 'phone' => '0912345678', 'class_name' => '12A1', 'major' => $this->major->id, 'consent' => true];
    $this->checkin = fn (array $extra = [], ?SchoolActivity $a = null) => $this->postJson('/checkin/'.($a ?? $this->activity)->checkin_token, ($this->form)($extra));
});

describe('the public page', function () {
    it('shows the activity and the short form behind the QR code, without logging in', function () {
        $this->get("/checkin/{$this->activity->checkin_token}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Checkin/Show')
            ->where('activity.title', 'Tư vấn tại THPT Chu Văn An')->where('activity.school', 'THPT Chu Văn An')->where('activity.type_label', 'Tư vấn tại trường')
            ->where('activity.organization', 'Đại học Demo')->where('closed', null)->where('majors.0.label', 'Công nghệ thông tin'));
    });

    it('is a plain 404 for an unknown, malformed or deleted token', function () {
        $this->get('/checkin/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/checkin/short')->assertNotFound();
        $token = $this->activity->checkin_token;
        $this->activity->delete();

        $this->get("/checkin/{$token}")->assertNotFound();
        $this->postJson("/checkin/{$token}", ($this->form)())->assertNotFound();
    });

    it('says why it is closed: cancelled, done, not started, ended', function () {
        $state = fn (array $attributes) => SchoolActivity::factory()->create($attributes + ['organization_id' => $this->org->id, 'cycle_id' => $this->cycle->id, 'start_at' => now()->subHour(), 'end_at' => now()->addHour()])->checkin_token;

        $this->get('/checkin/'.$state(['status' => 'cancelled']))->assertInertia(fn (Assert $page) => $page->where('closed', 'cancelled'));
        $this->get('/checkin/'.$state(['status' => 'done']))->assertInertia(fn (Assert $page) => $page->where('closed', 'done'));
        $this->get('/checkin/'.$state(['start_at' => now()->addDays(3), 'end_at' => now()->addDays(3)->addHours(2)]))->assertInertia(fn (Assert $page) => $page->where('closed', 'not_started')->where('opens_at', fn ($v) => $v !== null));
        $this->get('/checkin/'.$state(['start_at' => now()->subDays(3), 'end_at' => now()->subDays(3)->addHours(2)]))->assertInertia(fn (Assert $page) => $page->where('closed', 'ended'));
    });

    it('opens 2 hours before the start and closes 12 hours after the end', function () {
        $activity = SchoolActivity::factory()->create(['organization_id' => $this->org->id, 'cycle_id' => $this->cycle->id, 'start_at' => now()->addHours(5), 'end_at' => now()->addHours(7)]);
        $this->get("/checkin/{$activity->checkin_token}")->assertInertia(fn (Assert $page) => $page->where('closed', 'not_started'));
        $this->travel(210)->minutes(); // 3.5 h later: 1.5 h before the start
        $this->get("/checkin/{$activity->checkin_token}")->assertInertia(fn (Assert $page) => $page->where('closed', null));
        $this->travel(16)->hours(); // 19.5 h from the beginning: 12.5 h after the end
        $this->get("/checkin/{$activity->checkin_token}")->assertInertia(fn (Assert $page) => $page->where('closed', 'ended'));
    });
});

describe('checking in', function () {
    it('creates the applicant, the case with its source, campaign and activity, a touch, the attendee, the owner and the first-call task', function () {
        ($this->checkin)()->assertOk()->assertJson(['ok' => true, 'repeat' => false]);

        $applicant = Applicant::query()->withoutGlobalScopes()->firstOrFail();
        $case = AdmissionCase::query()->withoutGlobalScopes()->firstOrFail();
        $source = LeadSource::query()->where('code', 'school_counseling')->value('id');

        expect($applicant->full_name)->toBe('Nguyễn Văn An')->and($applicant->phone_normalized)->toBe('84912345678')->and($applicant->class_name)->toBe('12A1')
            ->and($applicant->high_school_id)->toBe($this->school->id)->and($applicant->priority_area_id)->toBe($this->area?->id)->and($applicant->consent_marketing)->toBeTrue()
            ->and($applicant->first_source_id)->toBe($source)->and($applicant->first_campaign_id)->toBe($this->campaign->id)
            ->and($case->cycle_id)->toBe($this->cycle->id)->and($case->stage->code)->toBe('new')->and($case->source_id)->toBe($source)->and($case->campaign_id)->toBe($this->campaign->id)
            ->and($case->school_activity_id)->toBe($this->activity->id)->and($case->primary_major_id)->toBe($this->major->id)->and($case->owner_id)->toBe($this->officer->id)
            ->and(CaseTouch::query()->where('case_id', $case->id)->where('school_activity_id', $this->activity->id)->count())->toBe(1)
            ->and(Task::query()->withoutGlobalScopes()->where('subject_id', $case->id)->where('title', 'Gọi lần đầu')->count())->toBe(1);

        $attendee = ActivityAttendee::query()->firstOrFail();
        expect($attendee->applicant_id)->toBe($applicant->id)->and($attendee->school_activity_id)->toBe($this->activity->id)->and($attendee->checked_in_at)->not->toBeNull()->and($attendee->form_data['class_name'])->toBe('12A1');
    });

    it('twice with the same phone: one applicant, one case, one attendee, two touches', function () {
        ($this->checkin)()->assertOk();
        ($this->checkin)(['phone' => '+84 912 345 678', 'full_name' => 'Nguyễn Văn An (nhập lại)'])->assertOk()->assertJson(['ok' => true, 'repeat' => true]);

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(1)->and(AdmissionCase::query()->withoutGlobalScopes()->count())->toBe(1)
            ->and(ActivityAttendee::query()->count())->toBe(1)->and(CaseTouch::query()->count())->toBe(2)
            ->and(Applicant::query()->withoutGlobalScopes()->value('full_name'))->toBe('Nguyễn Văn An');
    });

    it('recognises a student who is already in the CRM and links the activity to their case', function () {
        $existing = Applicant::factory()->for($this->org)->create(['full_name' => 'Nguyễn An', 'phone' => '0912345678']);
        app(CreateAdmissionCase::class)->handle($existing, $this->cycle, ['owner_id' => $this->admin->id], $this->admin);

        ($this->checkin)()->assertOk();

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(1)->and(AdmissionCase::query()->withoutGlobalScopes()->count())->toBe(1)
            ->and(AdmissionCase::query()->withoutGlobalScopes()->value('owner_id'))->toBe($this->admin->id)
            ->and(CaseTouch::query()->where('school_activity_id', $this->activity->id)->count())->toBe(1)->and(ActivityAttendee::query()->where('applicant_id', $existing->id)->count())->toBe(1);
    });

    it('different students are different applicants and attendees', function () {
        ($this->checkin)()->assertOk();
        ($this->checkin)(['full_name' => 'Trần Thị Bình', 'phone' => '0987654321'])->assertOk();

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(2)->and(ActivityAttendee::query()->count())->toBe(2);
    });

    it('gives the source of the activity type (career fair → Ngày hội tuyển sinh)', function () {
        $fair = SchoolActivity::factory()->create(['organization_id' => $this->org->id, 'cycle_id' => $this->cycle->id, 'type' => 'career_fair', 'start_at' => now()->subHour(), 'end_at' => now()->addHour()]);

        ($this->checkin)([], $fair)->assertOk();

        expect(AdmissionCase::query()->withoutGlobalScopes()->value('source_id'))->toBe(LeadSource::query()->where('code', 'open_day')->value('id'));
    });

    it('follows the assignment rules of the organization', function () {
        $other = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own, 'name' => 'Người theo quy tắc']);
        AssignmentRule::factory()->create(['organization_id' => $this->org->id, 'priority' => 1, 'strategy' => 'fixed', 'user_ids' => [$other->id]]);

        ($this->checkin)()->assertOk();

        expect(AdmissionCase::query()->withoutGlobalScopes()->value('owner_id'))->toBe($other->id);
    });

    it('validates the form: name, phone, consent', function () {
        ($this->checkin)(['full_name' => ''])->assertStatus(422)->assertJsonValidationErrors('full_name');
        ($this->checkin)(['phone' => ''])->assertStatus(422)->assertJsonValidationErrors('phone');
        ($this->checkin)(['phone' => '123'])->assertStatus(422)->assertJsonValidationErrors('phone');
        ($this->checkin)(['consent' => false])->assertStatus(422)->assertJsonValidationErrors('consent');
        ($this->checkin)(['class_name' => str_repeat('x', 60)])->assertStatus(422)->assertJsonValidationErrors('class_name');

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(0)->and(ActivityAttendee::query()->count())->toBe(0);
    });

    it('drops a honeypot submission quietly', function () {
        ($this->checkin)(['hp' => 'http://spam.example'])->assertOk()->assertJson(['ok' => true]);

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(0)->and(ActivityAttendee::query()->count())->toBe(0);
    });

    it('is refused when the activity is cancelled, done or not open yet', function () {
        $this->activity->update(['status' => 'cancelled']);
        ($this->checkin)()->assertStatus(422)->assertJsonValidationErrors('activity');

        $this->activity->update(['status' => 'planned', 'start_at' => now()->addDays(2), 'end_at' => now()->addDays(2)->addHours(2)]);
        ($this->checkin)()->assertStatus(422)->assertJsonValidationErrors('activity');

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('is rate limited per IP and activity', function () {
        $codes = collect(range(1, 32))->map(fn () => ($this->checkin)(['phone' => ''])->status());

        expect($codes->take(30)->unique()->all())->toBe([422])->and($codes->last())->toBe(429);
    });

    it('needs no CSRF token or login (it is embedded in a QR scan)', function () {
        $this->withMiddleware();
        $this->get("/checkin/{$this->activity->checkin_token}")->assertOk();
    });
});

describe('what the school page shows', function () {
    it('lists the activities and the statistics per cycle of the school', function () {
        ($this->checkin)()->assertOk();
        ($this->checkin)(['full_name' => 'Trần Thị Bình', 'phone' => '0987654321'])->assertOk();
        $won = AdmissionCase::query()->withoutGlobalScopes()->first();
        $won->forceFill(['stage_id' => $won->pipeline->stages()->where('code', 'enrolled')->value('id'), 'status' => 'won'])->saveQuietly();
        $this->activity->update(['status' => 'done', 'actual_attendees' => 120, 'cost' => 3000000]);
        SchoolActivity::factory()->create(['organization_id' => $this->org->id, 'cycle_id' => $this->cycle->id, 'high_school_id' => $this->school->id, 'status' => 'cancelled', 'cost' => 999]);

        $stats = app(SchoolStatistics::class)->forSchool($this->school);

        expect($stats)->toHaveCount(1)->and($stats[0])->toMatchArray([
            'cycle_id' => $this->cycle->id, 'applicants' => 2, 'cases' => 2, 'admitted' => 1, 'enrolled' => 1, 'enrolled_rate' => 50.0,
            'activities' => 1, 'checkins' => 2, 'attendance' => 120, 'cost' => 3000000,
        ]);

        $this->actingAs($this->admin)->get("/high-schools/{$this->school->id}")->assertInertia(fn (Assert $page) => $page->component('HighSchools/Show')
            ->has('activities', 2)->where('stats.0.enrolled', 1)->where('stats.0.checkins', 2)->where('can.activities.create', true));
    });

    it('has no statistics for a school without students or activities', function () {
        expect(app(SchoolStatistics::class)->forSchool(HighSchool::factory()->for($this->org)->create()))->toBe([]);
    });
});
