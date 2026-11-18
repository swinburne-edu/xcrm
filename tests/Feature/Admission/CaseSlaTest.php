<?php

use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Admission\Actions\AssignCase;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Actions\MoveCaseStage;
use App\Domain\Admission\Jobs\CheckCaseSla;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LostReason;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Actions\SaveTeam;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Platform\Services\AssignmentSettings;
use App\Models\User;
use App\Notifications\CaseRecalled;
use App\Notifications\SlaBreached;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->head = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->head->assignRole('admission_head');
    $this->leader = User::factory()->for($this->org)->create(['name' => 'Trưởng nhóm', 'data_scope' => DataScopeLevel::Team]);
    $this->leader->assignRole('team_lead');
    $this->owner = User::factory()->for($this->org)->create(['name' => 'Tư vấn viên', 'data_scope' => DataScopeLevel::Own]);
    $this->owner->assignRole('counselor');
    $this->actingAs($this->admin);
    $this->team = app(SaveTeam::class)->handle(null, ['name' => 'Nhóm Hà Nội', 'leader_id' => $this->leader->id, 'member_ids' => [$this->owner->id]]);

    $this->stage = fn (string $code) => PipelineStage::query()->whereHas('pipeline', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $this->org->id))->where('code', $code)->firstOrFail();
    /** An owned case in the team ("new" stage, SLA 24 h). */
    $this->owned = fn (): AdmissionCase => app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $this->cycle, ['owner_id' => $this->owner->id, 'team_id' => $this->team->id], $this->admin);
    $this->setting = fn (array $values) => app(AssignmentSettings::class)->save($this->org, $values);
    $this->check = fn () => CheckCaseSla::dispatchSync();
    $this->patch = fn (AdmissionCase $case, array $values) => AdmissionCase::query()->withoutGlobalScopes()->whereKey($case->id)->update($values);
});

describe('SLA alert', function () {
    it('does nothing before the deadline, then tells the team leader once', function () {
        $case = ($this->owned)();

        $this->travel(23)->hours();
        ($this->check)();
        expect($this->leader->notifications()->count())->toBe(0);

        $this->travel(2)->hours(); // 25 h after assignment; the "new" stage allows 24
        ($this->check)();
        ($this->check)(); // a second run must not repeat it

        $notification = $this->leader->notifications()->get();
        expect($notification)->toHaveCount(1)->and($notification[0]->type)->toBe(SlaBreached::class)->and($notification[0]->data['kind'])->toBe('sla')
            ->and($notification[0]->data['url'])->toBe("/applicants/{$case->applicant->uuid}")->and($notification[0]->data['body'])->toContain('Mới')->toContain('Tư vấn viên')
            ->and($this->owner->notifications()->count())->toBe(0)->and($this->head->notifications()->count())->toBe(0)
            ->and($case->refresh()->sla_alerted_at)->not->toBeNull();
    });

    it('is cancelled by a call, note or message since the case was assigned, also on the applicant', function () {
        $viaCase = ($this->owned)();
        $viaApplicant = ($this->owned)();
        $system = ($this->owned)();
        $this->travel(10)->hours();
        app(RecordActivity::class)->handle($viaCase, ActivityType::Call, ['body' => 'Đã gọi'], $this->owner);
        app(RecordActivity::class)->handle($viaApplicant->applicant, ActivityType::Note, ['body' => 'Ghi chú'], $this->owner);
        app(RecordActivity::class)->handle($system, ActivityType::System, ['title' => 'Việc của hệ thống']);
        $this->travel(20)->hours();

        ($this->check)();

        $titles = $this->leader->notifications()->get()->map(fn ($n) => $n->data['title'])->all();
        expect($titles)->toBe(["Quá hạn xử lý: {$system->applicant->full_name}"]);
    });

    it('starts again when the case enters a new stage', function () {
        $case = ($this->owned)();
        $this->travel(25)->hours();
        ($this->check)();
        app(MoveCaseStage::class)->handle($case, ($this->stage)('contacted'), $this->owner); // SLA 72 h

        $this->travel(48)->hours();
        ($this->check)();
        expect($this->leader->notifications()->count())->toBe(1);

        $this->travel(30)->hours(); // 78 h in "Đã liên hệ"
        ($this->check)();
        expect($this->leader->notifications()->count())->toBe(2);
    });

    it('starts again when the case is handed to someone else', function () {
        $case = ($this->owned)();
        $this->travel(25)->hours();
        ($this->check)();
        $other = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        app(AssignCase::class)->handle($case->refresh(), $other, $this->team->id, $this->admin);

        $this->travel(10)->hours();
        ($this->check)();
        expect($this->leader->notifications()->count())->toBe(1);

        $this->travel(15)->hours();
        ($this->check)();
        expect($this->leader->notifications()->count())->toBe(2);
    });

    it('goes to the managers when the case has no team leader', function () {
        $case = ($this->owned)();
        ($this->patch)($case, ['team_id' => null]);
        $this->travel(25)->hours();

        ($this->check)();

        expect($this->admin->notifications()->count())->toBe(1)->and($this->head->notifications()->count())->toBe(1)
            ->and($this->leader->notifications()->count())->toBe(0)->and($this->owner->notifications()->count())->toBe(0);
    });

    it('ignores stages without SLA, the pool, finished cases and closed cycles, and can be switched off', function () {
        $noSla = ($this->owned)();
        app(MoveCaseStage::class)->handle($noSla, ($this->stage)('counseling'), $this->owner);
        app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $this->cycle, ['owner_id' => null], $this->admin);
        $lost = ($this->owned)();
        app(MoveCaseStage::class)->handle($lost, ($this->stage)('lost'), $this->owner, ['lost_reason_id' => LostReason::query()->value('id')]);
        $closed = AdmissionCycle::factory()->for($this->org)->create(['status' => 'closed']);
        $stale = ($this->owned)();
        ($this->patch)($stale, ['cycle_id' => $closed->id]);
        $this->travel(100)->hours();

        ($this->check)();
        expect($this->leader->notifications()->count())->toBe(0);

        ($this->owned)();
        $this->travel(25)->hours();
        ($this->setting)(['sla_alerts' => false]);
        ($this->check)();
        expect($this->leader->notifications()->count())->toBe(0);

        ($this->setting)(['sla_alerts' => true]);
        ($this->check)();
        expect($this->leader->notifications()->count())->toBe(1);
    });

    it('keeps organizations apart', function () {
        $other = Organization::factory()->create();
        $stranger = User::factory()->for($other)->create();
        $stranger->assignRole('admin');
        ($this->owned)();
        $this->travel(25)->hours();

        ($this->check)();

        expect($stranger->notifications()->count())->toBe(0);
    });
});

describe('automatic recall', function () {
    it('is off by default', function () {
        $case = ($this->owned)();
        ($this->patch)($case, ['last_activity_at' => now()->subDays(60)]);
        ($this->setting)(['sla_alerts' => false]);

        ($this->check)();

        expect($case->refresh()->owner_id)->toBe($this->owner->id);
    });

    it('takes an idle case back to the team pool, tells the owner and the leader, and logs why', function () {
        ($this->setting)(['sla_alerts' => false, 'recall_after_days' => 14]);
        $idle = ($this->owned)();
        $busy = ($this->owned)();
        ($this->patch)($idle, ['last_activity_at' => now()->subDays(15), 'sla_alerted_at' => now()->subDays(14)]);
        ($this->patch)($busy, ['last_activity_at' => now()->subDays(13)]);

        ($this->check)();

        $idle->refresh();
        expect($idle->owner_id)->toBeNull()->and($idle->team_id)->toBe($this->team->id)->and($idle->assigned_at)->toBeNull()->and($idle->sla_alerted_at)->toBeNull()
            ->and($busy->refresh()->owner_id)->toBe($this->owner->id);

        $titles = Activity::query()->where('subject_type', 'admission_case')->where('subject_id', $idle->id)->pluck('title')->all();
        expect(collect($titles)->contains(fn ($t) => str_contains((string) $t, 'Thu hồi tự động')))->toBeTrue()
            ->and(collect($titles)->contains('Tư vấn viên → Kho chung'))->toBeTrue();

        foreach ([$this->owner, $this->leader] as $user) {
            $n = $user->notifications()->get();
            expect($n)->toHaveCount(1)->and($n[0]->type)->toBe(CaseRecalled::class)->and($n[0]->data['body'])->toContain('14 ngày')->toContain('Tư vấn viên');
        }

        ($this->check)(); // already in the pool: nothing more
        expect($this->owner->notifications()->count())->toBe(1);
    });

    it('leaves alone a case with a planned next action, a finished case and a closed cycle', function () {
        ($this->setting)(['sla_alerts' => false, 'recall_after_days' => 7]);
        $planned = ($this->owned)();
        $won = ($this->owned)();
        $closedCase = ($this->owned)();
        $closed = AdmissionCycle::factory()->for($this->org)->create(['status' => 'closed']);
        $past = ['last_activity_at' => now()->subDays(30)];
        ($this->patch)($planned, $past + ['next_action_at' => now()->addDays(2)]);
        ($this->patch)($won, $past + ['status' => 'won', 'stage_id' => ($this->stage)('enrolled')->id]);
        ($this->patch)($closedCase, $past + ['cycle_id' => $closed->id]);

        ($this->check)();

        expect(AdmissionCase::query()->withoutGlobalScopes()->whereIn('id', [$planned->id, $won->id, $closedCase->id])->pluck('owner_id')->unique()->all())->toBe([$this->owner->id]);
    });

    it('recalls a planned case once its next action date has passed', function () {
        ($this->setting)(['sla_alerts' => false, 'recall_after_days' => 7]);
        $case = ($this->owned)();
        ($this->patch)($case, ['last_activity_at' => now()->subDays(30), 'next_action_at' => now()->subDay()]);

        ($this->check)();

        expect($case->refresh()->owner_id)->toBeNull();
    });
});

describe('settings and scheduling', function () {
    it('has sensible defaults and clamps what it stores', function () {
        $settings = app(AssignmentSettings::class);

        expect($settings->all($this->org))->toBe(['sla_alerts' => true, 'recall_after_days' => null]);

        $settings->save($this->org, ['sla_alerts' => false, 'recall_after_days' => '30']);
        expect($settings->all($this->org->refresh()))->toBe(['sla_alerts' => false, 'recall_after_days' => 30]);

        $settings->save($this->org, ['recall_after_days' => 0]);
        expect($settings->recallAfterDays($this->org->refresh()))->toBeNull();

        $settings->save($this->org, ['recall_after_days' => 5000]);
        expect($settings->recallAfterDays($this->org->refresh()))->toBe(365);

        $this->org->forceFill(['settings' => ['other' => 'kept']])->save();
        $settings->save($this->org, ['recall_after_days' => 10]);
        expect($this->org->refresh()->settings['other'])->toBe('kept');
    });

    it('runs from the crm:check-sla command every 15 minutes', function () {
        $this->artisan('crm:check-sla')->assertSuccessful();
        $this->artisan('schedule:list')->expectsOutputToContain('crm:check-sla')->assertSuccessful();
    });
});
