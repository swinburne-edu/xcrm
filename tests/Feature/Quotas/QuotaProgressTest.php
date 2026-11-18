<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Jobs\AggregateQuotaProgress;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Major\Models\QuotaSnapshot;
use App\Domain\Major\Services\QuotaProgress;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Notifications\QuotaAlert;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create(['status' => 'active', 'start_date' => now()->subDays(50)->toDateString(), 'end_date' => now()->addDays(50)->toDateString()]);

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->head = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->head->assignRole('admission_head');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');

    $this->major = Major::factory()->for($this->org)->create(['name' => 'Công nghệ thông tin']);
    $this->quota = fn (Major $m, int $total, ?int $target = null, int $direct = 0) => MajorQuota::query()->create([
        'organization_id' => $this->org->id, 'major_id' => $m->id, 'cycle_id' => $this->cycle->id, 'quota_total' => $total, 'quota_direct' => $direct, 'target_enrolled' => $target,
    ]);
    $this->method = CycleMethod::factory()->create(['cycle_id' => $this->cycle->id]);
    $this->round = AdmissionRound::factory()->create(['cycle_id' => $this->cycle->id]);

    /** A case (owned by $owner) with one nguyện vọng for $major in the given status. */
    $this->applied = function (Major $major, string $status, ?User $owner = null): AdmissionCase {
        $case = app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $this->cycle, ['owner_id' => ($owner ?? $this->admin)->id], $this->admin);
        Application::factory()->create(['organization_id' => $this->org->id, 'case_id' => $case->id, 'major_id' => $major->id, 'cycle_method_id' => $this->method->id, 'round_id' => $this->round->id, 'status' => $status]);

        return $case;
    };
    $this->enrolled = function (Major $major): AdmissionCase {
        $case = app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $this->cycle, ['owner_id' => $this->admin->id], $this->admin);
        $won = $case->pipeline->stages()->where('code', 'enrolled')->firstOrFail();
        AdmissionCase::query()->withoutDataScope()->whereKey($case->id)->update(['stage_id' => $won->id, 'status' => 'won', 'enrolled_major_id' => $major->id]);

        return $case;
    };
});

describe('QuotaProgress', function () {
    it('counts distinct cases per tier for each major, ignoring the data scope', function () {
        ($this->quota)($this->major, 100, 90, 10);
        $this->actingAs($this->counselor); // sees almost nothing of the cases, but the aggregates cover the organization

        ($this->applied)($this->major, 'draft');
        ($this->applied)($this->major, 'submitted');
        ($this->applied)($this->major, 'eligible');
        ($this->applied)($this->major, 'admitted');
        ($this->applied)($this->major, 'confirmed');
        ($this->applied)($this->major, 'rejected');
        ($this->applied)($this->major, 'withdrawn');
        ($this->enrolled)($this->major);

        $row = app(QuotaProgress::class)->rows($this->cycle)[0];

        expect($row)->toMatchArray([
            'major' => 'Công nghệ thông tin', 'quota' => 100, 'direct' => 10, 'target' => 90,
            'interested' => 7, // draft, submitted, eligible, admitted, confirmed, rejected + the enrolled case (withdrawn is not interest)
            'submitted' => 5, 'eligible' => 3, 'admitted' => 2, 'confirmed' => 1, 'enrolled' => 1, 'secured' => 3,
        ])->and($row['fill'])->toBe(3.3);
    });

    it('counts a case once even when it has several nguyện vọng for the same major', function () {
        ($this->quota)($this->major, 50);
        $case = ($this->applied)($this->major, 'admitted');
        Application::factory()->create(['organization_id' => $this->org->id, 'case_id' => $case->id, 'major_id' => $this->major->id, 'cycle_method_id' => $this->method->id, 'round_id' => $this->round->id, 'preference_order' => 2, 'status' => 'confirmed']);
        $won = $case->pipeline->stages()->where('code', 'enrolled')->firstOrFail();
        AdmissionCase::query()->whereKey($case->id)->update(['stage_id' => $won->id, 'status' => 'won', 'enrolled_major_id' => $this->major->id]);

        $row = app(QuotaProgress::class)->rows($this->cycle)[0];

        expect($row['secured'])->toBe(1)->and($row['admitted'])->toBe(1)->and($row['enrolled'])->toBe(1);
    });

    it('also counts primary and interested majors of a case as interest', function () {
        ($this->quota)($this->major, 50);
        $other = Major::factory()->for($this->org)->create();
        $case = app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $this->cycle, ['owner_id' => $this->admin->id, 'primary_major_id' => $this->major->id], $this->admin);
        DB::table('case_interested_majors')->insert(['case_id' => $case->id, 'major_id' => $other->id, 'priority' => 2]);
        ($this->quota)($other, 20);

        $rows = collect(app(QuotaProgress::class)->rows($this->cycle))->keyBy('major_id');

        expect($rows[$this->major->id]['interested'])->toBe(1)->and($rows[$other->id]['interested'])->toBe(1);
    });

    it('ignores another cycle', function () {
        ($this->quota)($this->major, 50);
        $otherCycle = AdmissionCycle::factory()->for($this->org)->create();
        $case = app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $otherCycle, [], $this->admin);
        Application::factory()->create(['organization_id' => $this->org->id, 'case_id' => $case->id, 'major_id' => $this->major->id, 'cycle_method_id' => CycleMethod::factory()->create(['cycle_id' => $otherCycle->id])->id, 'round_id' => AdmissionRound::factory()->create(['cycle_id' => $otherCycle->id])->id, 'status' => 'admitted']);

        expect(app(QuotaProgress::class)->rows($this->cycle)[0]['admitted'])->toBe(0);
    });

    it('is empty when no quota is declared', function () {
        expect(app(QuotaProgress::class)->rows($this->cycle))->toBe([]);
    });

    it('orders over, then short, then ok, then by name', function () {
        $a = Major::factory()->for($this->org)->create(['name' => 'A ok']);
        $b = Major::factory()->for($this->org)->create(['name' => 'B short']);
        $c = Major::factory()->for($this->org)->create(['name' => 'C over']);
        ($this->quota)($a, 1);
        ($this->quota)($b, 100);
        ($this->quota)($c, 1);
        ($this->applied)($a, 'admitted');
        ($this->applied)($c, 'admitted');
        ($this->applied)($c, 'admitted');

        expect(collect(app(QuotaProgress::class)->rows($this->cycle))->pluck('level', 'major')->all())->toBe(['C over' => 'over', 'B short' => 'short', 'A ok' => 'ok']);
    });
});

describe('warning levels', function () {
    it('over: more secured places than the quota', function () {
        expect(app(QuotaProgress::class)->level(101, 100, 90, 0.5))->toBe(['over', 1])->and(app(QuotaProgress::class)->level(100, 100, 90, 0.5)[0])->not->toBe('over');
    });

    it('short: behind target × time progress × 0.7, only once 25% of the cycle has passed', function () {
        $p = app(QuotaProgress::class);

        expect($p->level(10, 100, 100, 0.5))->toBe(['short', 25]) // expected 35
            ->and($p->level(35, 100, 100, 0.5))->toBe(['ok', 0])
            ->and($p->level(0, 100, 100, 0.2))->toBe(['ok', 0]) // too early
            ->and($p->level(0, 100, 100, null))->toBe(['ok', 0]); // no usable dates
    });

    it('measures time progress from the cycle dates; a closed cycle is complete', function () {
        $p = app(QuotaProgress::class);

        expect($p->progress($this->cycle))->toBeBetween(0.49, 0.51);

        $this->cycle->update(['start_date' => null]);
        expect($p->progress($this->cycle->refresh()))->toBeNull();

        $this->cycle->update(['status' => 'closed']);
        expect($p->progress($this->cycle->refresh()))->toBe(1.0);
    });

    it('summarises all majors', function () {
        ($this->quota)($this->major, 100, 80);
        ($this->applied)($this->major, 'admitted');
        ($this->applied)($this->major, 'confirmed');
        $p = app(QuotaProgress::class);

        $summary = $p->summary($p->rows($this->cycle));

        expect($summary)->toMatchArray(['quota' => 100, 'target' => 80, 'admitted' => 2, 'confirmed' => 1, 'secured' => 2, 'fill' => 2.5, 'short' => 1, 'over' => 0]);
    });

    it('shows the change since the snapshot of a week ago', function () {
        ($this->quota)($this->major, 100, 80);
        ($this->applied)($this->major, 'admitted');
        ($this->applied)($this->major, 'admitted');
        QuotaSnapshot::query()->create(['organization_id' => $this->org->id, 'cycle_id' => $this->cycle->id, 'major_id' => $this->major->id, 'snapshot_date' => now()->subDays(8)->toDateString(), 'secured' => 1]);

        expect(app(QuotaProgress::class)->rows($this->cycle)[0]['change_7d'])->toBe(1);
    });
});

describe('AggregateQuotaProgress', function () {
    it('stores today\'s snapshot and notifies cycle managers once when a major falls behind', function () {
        ($this->quota)($this->major, 100);

        AggregateQuotaProgress::dispatchSync();

        $snapshot = QuotaSnapshot::query()->firstOrFail();
        expect($snapshot->level)->toBe('short')->and($snapshot->quota_total)->toBe(100)->and($snapshot->snapshot_date)->toBe(now(config('app.display_timezone'))->toDateString());
        expect($this->admin->notifications()->count())->toBe(1)->and($this->head->notifications()->count())->toBe(1)->and($this->counselor->notifications()->count())->toBe(0);

        $data = $this->head->notifications()->first()->data;
        expect($data['kind'])->toBe('quota')->and($data['level'])->toBe('short')->and($data['title'])->toContain('Công nghệ thông tin')->and($data['url'])->toBe("/applications?filter[major_id]={$this->major->id}");

        AggregateQuotaProgress::dispatchSync(); // same level again: neither a second snapshot nor a second notification
        expect(QuotaSnapshot::query()->count())->toBe(1)->and($this->head->notifications()->count())->toBe(1);
    });

    it('notifies again when the level changes to over, and never for ok', function () {
        ($this->quota)($this->major, 1);
        ($this->applied)($this->major, 'admitted'); // 1 secured of a quota of 1: ok

        AggregateQuotaProgress::dispatchSync();
        expect($this->head->notifications()->count())->toBe(0)->and(QuotaSnapshot::query()->value('level'))->toBe('ok');

        ($this->applied)($this->major, 'admitted');
        AggregateQuotaProgress::dispatchSync();

        expect($this->head->notifications()->count())->toBe(1)->and($this->head->notifications()->first()->data['level'])->toBe('over')->and(QuotaSnapshot::query()->value('level'))->toBe('over');
    });

    it('only looks at active cycles and keeps organizations apart', function () {
        ($this->quota)($this->major, 100);
        $this->cycle->update(['status' => 'preparing']);
        $other = Organization::factory()->create();
        $stranger = User::factory()->for($other)->create(['data_scope' => DataScopeLevel::All]);
        $stranger->assignRole('admin');

        AggregateQuotaProgress::dispatchSync();

        expect(QuotaSnapshot::query()->withoutGlobalScopes()->count())->toBe(0)->and($stranger->notifications()->count())->toBe(0);
    });

    it('runs from the crm:aggregate-quotas command and the hourly schedule', function () {
        ($this->quota)($this->major, 100);

        $this->artisan('crm:aggregate-quotas')->assertSuccessful();

        expect(QuotaSnapshot::query()->count())->toBe(1);
        $this->artisan('schedule:list')->expectsOutputToContain('crm:aggregate-quotas')->assertSuccessful();
    });
});

describe('screen', function () {
    it('shows the rows, summary and cycle progress to anyone with major.view', function () {
        ($this->quota)($this->major, 100, 80);
        ($this->applied)($this->major, 'admitted');

        $this->actingAs($this->counselor)->get('/quotas')->assertInertia(fn (Assert $page) => $page->component('Quotas/Index')
            ->where('cycle.id', $this->cycle->id)->where('cycle.progress', fn ($p) => $p >= 49 && $p <= 51)
            ->has('rows', 1)->where('rows.0.admitted', 1)->where('rows.0.level', 'short')->where('summary.quota', 100)->where('level', 'all'));
    });

    it('filters by warning level', function () {
        $ok = Major::factory()->for($this->org)->create();
        ($this->quota)($ok, 1);
        ($this->applied)($ok, 'admitted');
        ($this->quota)($this->major, 100);

        $this->actingAs($this->admin);
        $this->get('/quotas?level=alert')->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.level', 'short')->where('level', 'alert'));
        $this->get('/quotas?level=over')->assertInertia(fn (Assert $page) => $page->has('rows', 0));
        $this->get('/quotas?level=hack')->assertInertia(fn (Assert $page) => $page->has('rows', 2)->where('level', 'all'));
    });

    it('needs major.view', function () {
        $this->actingAs(User::factory()->for($this->org)->create())->get('/quotas')->assertForbidden();
    });

    it('renders without a cycle', function () {
        $user = User::factory()->for(Organization::factory()->create())->create();
        $user->givePermissionTo('major.view');

        $this->actingAs($user)->get('/quotas')->assertOk()->assertInertia(fn (Assert $page) => $page->where('cycle', null)->has('rows', 0));
    });
});

describe('bell', function () {
    it('lists the user\'s own notifications with the unread count', function () {
        ($this->quota)($this->major, 100);
        AggregateQuotaProgress::dispatchSync();

        $this->actingAs($this->head)->getJson('/notifications')->assertOk()->assertJson(['unread' => 1])
            ->assertJsonPath('items.0.kind', 'quota')->assertJsonPath('items.0.read', false)->assertJsonPath('items.0.level', 'short');
    });

    it('marks one or all as read, only for the owner', function () {
        ($this->quota)($this->major, 100);
        AggregateQuotaProgress::dispatchSync();
        $mine = $this->head->notifications()->first();

        $this->actingAs($this->counselor)->postJson("/notifications/{$mine->id}/read")->assertNotFound();
        expect($mine->refresh()->read_at)->toBeNull();

        $this->actingAs($this->head)->postJson("/notifications/{$mine->id}/read")->assertOk()->assertJson(['unread' => 0]);
        expect($mine->refresh()->read_at)->not->toBeNull();

        $this->head->notify(new QuotaAlert(['major_id' => 1, 'major' => 'X', 'level' => 'over', 'gap' => 1, 'secured' => 2, 'quota' => 1, 'target' => 1], 'Mùa'));
        $this->actingAs($this->head)->postJson('/notifications/read-all')->assertOk()->assertJson(['unread' => 0]);
        expect($this->head->unreadNotifications()->count())->toBe(0);
    });

    it('requires login', function () {
        $this->getJson('/notifications')->assertUnauthorized();
    });
});
