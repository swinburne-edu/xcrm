<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Actions\MoveCaseStage;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\LostReason;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Tag;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    $this->stage = fn (string $code) => PipelineStage::query()->whereHas('pipeline', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $this->org->id))->where('code', $code)->firstOrFail();
    $this->makeCase = fn (array $applicant = [], array $data = [], ?AdmissionCycle $cycle = null) => app(CreateAdmissionCase::class)->handle(
        Applicant::factory()->for($this->org)->create($applicant), $cycle ?? $this->cycle, $data + ['owner_id' => $this->admin->id], $this->admin,
    );
    $this->counselor = function (): User {
        $user = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        $user->assignRole('counselor');

        return $user;
    };
});

describe('board and table', function () {
    it('shows one column per stage with counts and the first cards, only for the selected cycle and organization', function () {
        ($this->makeCase)(['full_name' => 'Nguyễn An']);
        $b = ($this->makeCase)(['full_name' => 'Trần Bình']);
        app(MoveCaseStage::class)->handle($b, ($this->stage)('counseling'), $this->admin);
        ($this->makeCase)([], [], AdmissionCycle::factory()->for($this->org)->create());
        app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->other)->create(), AdmissionCycle::factory()->for($this->other)->current()->create());

        $this->actingAs($this->admin)->get('/cases')
            ->assertInertia(fn (Assert $page) => $page->component('Cases/Index')->where('mode', 'kanban')->where('cycle.id', $this->cycle->id)
                ->where('board.pipeline.name', 'Đại học chính quy')->has('board.columns', 10)
                ->where('board.columns.0.name', 'Mới')->where('board.columns.0.total', 1)->where('board.columns.0.cards.0.applicant_name', 'Nguyễn An')
                ->where('board.columns.2.name', 'Đang tư vấn')->where('board.columns.2.total', 1)->where('board.columns.2.cards.0.id', $b->uuid)
                ->where('board.columns.9.total', 0)->where('can.create', true)->has('options.stages', 10));
    });

    it('shows the same cases in the table with paging metadata', function () {
        foreach (range(1, 3) as $i) {
            ($this->makeCase)(['full_name' => "Thí sinh {$i}"]);
        }

        $this->actingAs($this->admin)->get('/cases/table')
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'table')->where('board', null)->where('cases.meta.total', 3)->has('cases.data', 3)
                ->where('cases.data.0.id', fn ($id) => strlen($id) === 36));
    });

    it('puts a case card together: applicant, school, major, owner, temperature, overdue action', function () {
        $school = HighSchool::factory()->for($this->org)->create(['name' => 'THPT Chu Văn An']);
        $major = Major::factory()->for($this->org)->create(['name' => 'Khoa học máy tính']);
        $case = ($this->makeCase)(['full_name' => 'Lê Minh', 'high_school_id' => $school->id], ['primary_major_id' => $major->id, 'temperature' => 'hot']);
        $case->update(['next_action_at' => now()->subDay()]);

        $this->actingAs($this->admin)->get('/cases')
            ->assertInertia(fn (Assert $page) => $page->where('board.columns.0.cards.0', fn ($card) => $card['high_school_name'] === 'THPT Chu Văn An' && $card['major_name'] === 'Khoa học máy tính'
                && $card['temperature'] === 'hot' && $card['overdue'] === true && $card['owner_name'] === $this->admin->name && $card['in_pool'] === false && $card['status'] === 'open'));
    });

    it('is limited by case permissions and shows in the menu', function () {
        $nobody = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);

        $this->actingAs($nobody)->get('/cases')->assertForbidden();
        $this->actingAs($nobody)->get('/cases/table')->assertForbidden();
        $this->actingAs($this->admin)->get('/cases')
            ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($nav) => str_contains(json_encode($nav, JSON_UNESCAPED_SLASHES), '/cases')));
    });

    it('copes with no cycle at all', function () {
        $this->cycle->forceDelete();

        $this->actingAs($this->admin)->get('/cases')
            ->assertInertia(fn (Assert $page) => $page->where('cycle', null)->where('board.pipeline', null)->where('can.create', false));
    });
});

describe('filters', function () {
    it('filters by search text (name, code, phone digits, e-mail)', function () {
        $a = ($this->makeCase)(['full_name' => 'Phạm Quang Huy', 'phone' => '0912345678', 'email' => 'huy@x.vn']);
        ($this->makeCase)(['full_name' => 'Ngô Thu Hà']);

        foreach (['Quang', $a->applicant->code, '0912345', '84912345678', 'huy@x'] as $term) {
            $this->actingAs($this->admin)->get('/cases/table?filter[q]='.urlencode($term))
                ->assertInertia(fn (Assert $page) => $page->has('cases.data', 1)->where('cases.data.0.uuid', $a->uuid));
        }
    });

    it('filters by owner: mine, the pool, a person', function () {
        $other = User::factory()->for($this->org)->create();
        $mine = ($this->makeCase)();
        $theirs = ($this->makeCase)([], ['owner_id' => $other->id]);
        $pool = ($this->makeCase)([], ['owner_id' => null]);

        $get = fn (string $q) => $this->actingAs($this->admin)->get("/cases/table?{$q}");
        $get('filter[owner]=mine')->assertInertia(fn (Assert $p) => $p->has('cases.data', 1)->where('cases.data.0.uuid', $mine->uuid));
        $get('filter[owner]=pool')->assertInertia(fn (Assert $p) => $p->has('cases.data', 1)->where('cases.data.0.uuid', $pool->uuid)->where('cases.data.0.in_pool', true));
        $get("filter[owner]={$other->id}")->assertInertia(fn (Assert $p) => $p->has('cases.data', 1)->where('cases.data.0.uuid', $theirs->uuid));
        $get('filter[owner]=all')->assertInertia(fn (Assert $p) => $p->has('cases.data', 3)->where('cases.query.filters.owner', 'all'));
        $get('filter[owner]=bogus')->assertInertia(fn (Assert $p) => $p->has('cases.data', 3));
    });

    it('filters by stage, status, temperature, source, campaign, province, school, major and tag', function () {
        $province = Province::factory()->create();
        $school = HighSchool::factory()->for($this->org)->create();
        $major = Major::factory()->for($this->org)->create();
        $source = LeadSource::query()->where('code', 'hotline')->firstOrFail();
        $campaign = Campaign::factory()->for($this->org)->create(['cycle_id' => $this->cycle->id]);
        $tag = Tag::factory()->for($this->org)->create();

        $target = ($this->makeCase)(['province_id' => $province->id, 'high_school_id' => $school->id], ['temperature' => 'warm', 'source_id' => $source->id, 'campaign_id' => $campaign->id, 'primary_major_id' => $major->id]);
        $target->tags()->attach($tag->id);
        app(MoveCaseStage::class)->handle($target, ($this->stage)('enrolled'), $this->admin);
        ($this->makeCase)(['full_name' => 'Nhiễu 1']);
        ($this->makeCase)(['full_name' => 'Nhiễu 2'], ['temperature' => 'cold']);

        $filters = [
            'stage_id' => ($this->stage)('enrolled')->id, 'status' => 'won', 'temperature' => 'warm', 'source_id' => $source->id, 'campaign_id' => $campaign->id,
            'province_id' => $province->id, 'high_school_id' => $school->id, 'primary_major_id' => $major->id, 'tag_id' => $tag->id,
        ];

        foreach ($filters as $key => $value) {
            $this->actingAs($this->admin)->get("/cases/table?filter[{$key}]={$value}")
                ->assertInertia(fn (Assert $page) => $page->has('cases.data', 1)->where('cases.data.0.uuid', $target->uuid));
        }
    });

    it('filters overdue open cases', function () {
        $late = ($this->makeCase)();
        $late->update(['next_action_at' => now()->subHours(3)]);
        $soon = ($this->makeCase)();
        $soon->update(['next_action_at' => now()->addDay()]);
        ($this->makeCase)();

        $this->actingAs($this->admin)->get('/cases/table?filter[overdue]=1')
            ->assertInertia(fn (Assert $page) => $page->has('cases.data', 1)->where('cases.data.0.uuid', $late->uuid));
    });

    it('applies the filters to the Kanban counts and cards too', function () {
        ($this->makeCase)([], ['temperature' => 'hot']);
        ($this->makeCase)([], ['temperature' => 'cold']);

        $this->actingAs($this->admin)->get('/cases?filter[temperature]=hot')
            ->assertInertia(fn (Assert $page) => $page->where('board.columns.0.total', 1)->has('board.columns.0.cards', 1)->where('board.columns.0.cards.0.temperature', 'hot'));
    });

    it('starts a counselor on "Của tôi" and lets them look at everything they may see', function () {
        $counselor = ($this->counselor)();
        $team = Team::factory()->for($this->org)->create();
        $team->members()->attach($counselor);
        $mine = ($this->makeCase)([], ['owner_id' => $counselor->id, 'team_id' => $team->id]);
        $pool = ($this->makeCase)([], ['owner_id' => null, 'team_id' => $team->id]);
        ($this->makeCase)([], ['owner_id' => $this->admin->id]);

        $this->actingAs($counselor)->get('/cases/table')
            ->assertInertia(fn (Assert $p) => $p->has('cases.data', 1)->where('cases.data.0.uuid', $mine->uuid)->where('cases.query.filters.owner', 'mine'));
        $this->actingAs($counselor)->get('/cases/table?filter[owner]=all')
            ->assertInertia(fn (Assert $p) => $p->has('cases.data', 2)->where('cases.query.filters.owner', 'all'));
        $this->actingAs($counselor)->get('/cases/table?filter[owner]=pool')
            ->assertInertia(fn (Assert $p) => $p->has('cases.data', 1)->where('cases.data.0.uuid', $pool->uuid));
        $this->actingAs($this->admin)->get('/cases/table')->assertInertia(fn (Assert $p) => $p->has('cases.data', 3));
    });
});

describe('load more', function () {
    it('pages through one column with the same filters', function () {
        foreach (range(1, 25) as $i) {
            ($this->makeCase)(['full_name' => "Thí sinh {$i}"], ['temperature' => 'hot']);
        }
        ($this->makeCase)([], ['temperature' => 'cold']);
        $stageId = ($this->stage)('new')->id;

        $this->actingAs($this->admin)->get('/cases')->assertInertia(fn (Assert $p) => $p->where('board.columns.0.total', 26)->has('board.columns.0.cards', 20));

        $first = $this->actingAs($this->admin)->getJson("/cases/column?stage_id={$stageId}&offset=20&filter[temperature]=hot")->assertOk();
        expect($first->json('total'))->toBe(25)->and($first->json('cards'))->toHaveCount(5);

        $head = $this->actingAs($this->admin)->getJson("/cases/column?stage_id={$stageId}&offset=0");
        $tail = $this->actingAs($this->admin)->getJson("/cases/column?stage_id={$stageId}&offset=20");
        expect($head->json('total'))->toBe(26)->and($head->json('cards'))->toHaveCount(20)
            ->and(array_unique([...$head->json('cards.*.uuid'), ...$tail->json('cards.*.uuid')]))->toHaveCount(26);
    });

    it('needs permission', function () {
        $this->actingAs(User::factory()->for($this->org)->create())->getJson('/cases/column?stage_id=1')->assertForbidden();
    });
});

describe('quick view', function () {
    it('returns the case with stepper, history, permissions and a masked phone for limited users', function () {
        $case = ($this->makeCase)(['full_name' => 'Võ Thanh', 'phone' => '0912345678', 'email' => 'thanh@x.vn']);
        app(MoveCaseStage::class)->handle($case, ($this->stage)('contacted'), $this->admin);

        $this->actingAs($this->admin)->getJson("/cases/{$case->uuid}")->assertOk()
            ->assertJsonPath('case.applicant_name', 'Võ Thanh')->assertJsonPath('case.phone', '0912345678')->assertJsonPath('case.stage_name', 'Đã liên hệ')
            ->assertJsonPath('case.can.update', true)->assertJsonPath('case.can.assign', true)->assertJsonPath('case.can.claim', false)
            ->assertJsonCount(10, 'case.stages')->assertJsonCount(2, 'case.history')->assertJsonPath('case.history.0.to', 'Đã liên hệ')
            ->assertJsonPath('case.cycle_closed', false)->assertJsonPath('case.applicant_uuid', $case->applicant->uuid);

        $limited = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $limited->givePermissionTo(['case.view', 'applicant.view']);
        $this->actingAs($limited)->getJson("/cases/{$case->uuid}")->assertJsonPath('case.phone', '09******78')->assertJsonPath('case.can.update', false)->assertJsonPath('case.can.assign', false);
    });

    it('offers claiming for a pool case, and hides cases outside the scope or organization', function () {
        $counselor = ($this->counselor)();
        $team = Team::factory()->for($this->org)->create();
        $team->members()->attach($counselor);
        $pool = ($this->makeCase)([], ['owner_id' => null, 'team_id' => $team->id]);
        $notMine = ($this->makeCase)();
        $foreign = app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->other)->create(), AdmissionCycle::factory()->for($this->other)->current()->create());

        $this->actingAs($counselor)->getJson("/cases/{$pool->uuid}")->assertOk()->assertJsonPath('case.can.claim', true)->assertJsonPath('case.in_pool', true);
        $this->actingAs($counselor)->getJson("/cases/{$notMine->uuid}")->assertNotFound();
        $this->actingAs($this->admin)->getJson("/cases/{$foreign->uuid}")->assertNotFound();
    });

    it('shows a closed cycle as read-only', function () {
        $case = ($this->makeCase)();
        $this->cycle->forceFill(['status' => 'closed', 'is_current' => false])->save();

        $this->actingAs($this->admin)->getJson("/cases/{$case->uuid}")->assertJsonPath('case.cycle_closed', true)->assertJsonPath('case.can.update', false)->assertJsonPath('case.can.assign', false);
    });
});

describe('create', function () {
    it('opens a case for a visible applicant in the selected cycle', function () {
        $applicant = Applicant::factory()->for($this->org)->create(['full_name' => 'Đặng Khoa']);
        $source = LeadSource::query()->where('code', 'website')->firstOrFail();

        $this->actingAs($this->admin)->post('/cases', ['applicant_id' => $applicant->id, 'source_id' => $source->id, 'temperature' => 'hot', 'owner_id' => $this->admin->id])->assertSessionHasNoErrors();

        $case = AdmissionCase::query()->where('applicant_id', $applicant->id)->firstOrFail();
        expect($case)->cycle_id->toBe($this->cycle->id)->source_id->toBe($source->id)->owner_id->toBe($this->admin->id)->and($case->stage->code)->toBe('new');
    });

    it('R5: refuses a second case for the same applicant and cycle', function () {
        $case = ($this->makeCase)();

        $this->actingAs($this->admin)->post('/cases', ['applicant_id' => $case->applicant_id])->assertSessionHasErrors('applicant_id');
        expect(AdmissionCase::query()->count())->toBe(1);
    });

    it('lets only users with case.assign choose the owner; others follow the territory or the pool', function () {
        $counselor = ($this->counselor)();
        $applicant = Applicant::factory()->for($this->org)->create(['created_by' => $counselor->id]);

        $this->actingAs($counselor)->post('/cases', ['applicant_id' => $applicant->id, 'owner_id' => $counselor->id])->assertSessionHasNoErrors();

        expect(AdmissionCase::query()->withoutDataScope()->where('applicant_id', $applicant->id)->value('owner_id'))->toBeNull();
    });

    it('validates the applicant, the owner and the cycle', function () {
        $foreign = Applicant::factory()->for($this->other)->create();
        $foreignUser = User::factory()->for($this->other)->create();
        $own = Applicant::factory()->for($this->org)->create();

        $this->actingAs($this->admin)->post('/cases', ['applicant_id' => $foreign->id])->assertSessionHasErrors('applicant_id');
        $this->actingAs($this->admin)->post('/cases', ['applicant_id' => 999999])->assertSessionHasErrors('applicant_id');
        $this->actingAs($this->admin)->post('/cases', [])->assertSessionHasErrors('applicant_id');
        $this->actingAs($this->admin)->post('/cases', ['applicant_id' => $own->id, 'owner_id' => $foreignUser->id])->assertSessionHasErrors('owner_id');
        $this->actingAs($this->admin)->post('/cases', ['applicant_id' => $own->id, 'temperature' => 'boiling'])->assertSessionHasErrors('temperature');

        $this->cycle->forceFill(['status' => 'closed', 'is_current' => false])->save();
        $this->actingAs($this->admin)->post('/cases', ['applicant_id' => $own->id])->assertSessionHasErrors('cycle_id');
        expect(AdmissionCase::query()->count())->toBe(0);
    });

    it('is limited by case.create', function () {
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('case.view');

        $this->actingAs($viewer)->post('/cases', ['applicant_id' => Applicant::factory()->for($this->org)->create()->id])->assertForbidden();
    });
});

describe('update, move, assign, claim', function () {
    it('updates the day-to-day fields, reading the next action in Vietnam time', function () {
        $case = ($this->makeCase)();
        $major = Major::factory()->for($this->org)->create();

        $this->actingAs($this->admin)->put("/cases/{$case->uuid}", ['temperature' => 'hot', 'next_action_at' => '2027-03-01T09:30', 'primary_major_id' => $major->id])->assertSessionHasNoErrors();

        expect($case->fresh())->temperature->value->toBe('hot')->primary_major_id->toBe($major->id)
            ->and($case->fresh()->next_action_at->utc()->format('Y-m-d H:i'))->toBe('2027-03-01 02:30');

        $this->actingAs($this->admin)->getJson("/cases/{$case->uuid}")->assertJsonPath('case.next_action_input', '2027-03-01T09:30');
        $this->actingAs($this->admin)->put("/cases/{$case->uuid}", ['next_action_at' => null, 'temperature' => null]);
        expect($case->fresh())->next_action_at->toBeNull()->temperature->toBeNull();
    });

    it('validates the update and respects permissions, scope and a closed cycle', function () {
        $case = ($this->makeCase)();
        $foreignMajor = Major::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->put("/cases/{$case->uuid}", ['temperature' => 'boiling'])->assertSessionHasErrors('temperature');
        $this->actingAs($this->admin)->put("/cases/{$case->uuid}", ['primary_major_id' => $foreignMajor->id])->assertSessionHasErrors('primary_major_id');
        $this->actingAs($this->admin)->put("/cases/{$case->uuid}", ['next_action_at' => 'tomorrow'])->assertSessionHasErrors('next_action_at');

        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('case.view');
        $this->actingAs($viewer)->put("/cases/{$case->uuid}", ['temperature' => 'hot'])->assertForbidden();
        $this->actingAs(($this->counselor)())->put("/cases/{$case->uuid}", ['temperature' => 'hot'])->assertForbidden();

        $this->cycle->forceFill(['status' => 'closed', 'is_current' => false])->save();
        $this->actingAs($this->admin)->put("/cases/{$case->uuid}", ['temperature' => 'hot'])->assertForbidden();
    });

    it('moves a case and reports rule violations as errors on the form (R6, required fields, same stage)', function () {
        $case = ($this->makeCase)();
        $url = "/cases/{$case->uuid}/move";

        $this->actingAs($this->admin)->post($url, ['stage_id' => ($this->stage)('counseling')->id])->assertSessionHasNoErrors();
        expect($case->fresh()->stage->code)->toBe('counseling');

        $this->actingAs($this->admin)->post($url, ['stage_id' => ($this->stage)('lost')->id])->assertSessionHasErrors('lost_reason_id');
        $this->actingAs($this->admin)->post($url, ['stage_id' => ($this->stage)('counseling')->id])->assertSessionHasErrors('stage_id');
        $this->actingAs($this->admin)->post($url, ['stage_id' => 999999])->assertSessionHasErrors('stage_id');
        $this->actingAs($this->admin)->post($url, [])->assertSessionHasErrors('stage_id');

        ($this->stage)('eligible')->update(['required_fields' => ['primary_major_id']]);
        $this->actingAs($this->admin)->post($url, ['stage_id' => ($this->stage)('eligible')->id])->assertSessionHasErrors('stage_id');

        $reason = LostReason::query()->first();
        $this->actingAs($this->admin)->post($url, ['stage_id' => ($this->stage)('lost')->id, 'lost_reason_id' => $reason->id, 'lost_note' => 'Đi du học'])->assertSessionHasNoErrors();
        expect($case->fresh())->status->value->toBe('lost')->lost_reason_id->toBe($reason->id)->lost_note->toBe('Đi du học');
    });

    it('needs case.update to move and a case inside the scope', function () {
        $case = ($this->makeCase)();
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('case.view');

        $this->actingAs($viewer)->post("/cases/{$case->uuid}/move", ['stage_id' => ($this->stage)('contacted')->id])->assertForbidden();
        $this->actingAs(($this->counselor)())->post("/cases/{$case->uuid}/move", ['stage_id' => ($this->stage)('contacted')->id])->assertForbidden();
        expect($case->fresh()->stage->code)->toBe('new');
    });

    it('assigns a case, returns it to the pool, and needs case.assign', function () {
        $case = ($this->makeCase)();
        $owner = User::factory()->for($this->org)->create();
        $counselor = ($this->counselor)();

        $this->actingAs($this->admin)->post("/cases/{$case->uuid}/assign", ['owner_id' => $owner->id])->assertSessionHasNoErrors();
        expect($case->fresh()->owner_id)->toBe($owner->id);

        $this->actingAs($this->admin)->post("/cases/{$case->uuid}/assign", ['owner_id' => null])->assertSessionHasNoErrors();
        expect($case->fresh()->owner_id)->toBeNull();

        $this->actingAs($this->admin)->post("/cases/{$case->uuid}/assign", ['owner_id' => User::factory()->for($this->other)->create()->id])->assertSessionHasErrors('owner_id');
        $own = ($this->makeCase)([], ['owner_id' => $counselor->id]);
        $this->actingAs($counselor)->post("/cases/{$own->uuid}/assign", ['owner_id' => $owner->id])->assertForbidden();
        expect($own->fresh()->owner_id)->toBe($counselor->id);
    });

    it('lets a counselor claim a pool case of their team, once', function () {
        $counselor = ($this->counselor)();
        $rival = ($this->counselor)();
        $team = Team::factory()->for($this->org)->create();
        $team->members()->attach([$counselor->id, $rival->id]);
        $case = ($this->makeCase)([], ['owner_id' => null, 'team_id' => $team->id]);

        $this->actingAs($counselor)->post("/cases/{$case->uuid}/claim")->assertSessionHasNoErrors();
        expect($case->fresh())->owner_id->toBe($counselor->id);

        $this->actingAs($rival)->post("/cases/{$case->uuid}/claim")->assertNotFound(); // no longer in the rival's scope
        expect($case->fresh()->owner_id)->toBe($counselor->id);
    });

    it('refuses to claim a case that has an owner or sits in a closed cycle', function () {
        $owned = ($this->makeCase)();
        $pool = ($this->makeCase)([], ['owner_id' => null]);
        $this->cycle->forceFill(['status' => 'closed', 'is_current' => false])->save();

        $this->actingAs($this->admin)->post("/cases/{$owned->uuid}/claim")->assertForbidden();
        $this->actingAs($this->admin)->post("/cases/{$pool->uuid}/claim")->assertForbidden();
    });
});

describe('bulk actions', function () {
    it('assigns, moves, tags and untags many cases', function () {
        $cases = collect(range(1, 3))->map(fn () => ($this->makeCase)());
        $ids = $cases->pluck('uuid')->all();
        $owner = User::factory()->for($this->org)->create();
        $tag = Tag::factory()->for($this->org)->create();
        $post = fn (array $data) => $this->actingAs($this->admin)->post('/cases/bulk', ['ids' => $ids] + $data);

        $post(['action' => 'assign', 'owner_id' => $owner->id])->assertSessionHasNoErrors();
        expect(AdmissionCase::query()->whereIn('uuid', $ids)->pluck('owner_id')->unique()->all())->toBe([$owner->id]);

        $post(['action' => 'assign', 'owner_id' => null]);
        expect(AdmissionCase::query()->whereIn('uuid', $ids)->whereNull('owner_id')->count())->toBe(3);

        $post(['action' => 'move', 'stage_id' => ($this->stage)('counseling')->id]);
        expect(AdmissionCase::query()->whereIn('uuid', $ids)->get()->every(fn ($c) => $c->stage->code === 'counseling'))->toBeTrue()
            ->and($cases->first()->stageHistories()->count())->toBe(2);

        $post(['action' => 'tag', 'tag_id' => $tag->id]);
        expect(DB::table('taggables')->where('tag_id', $tag->id)->count())->toBe(3);
        $post(['action' => 'tag', 'tag_id' => $tag->id]);
        expect(DB::table('taggables')->where('tag_id', $tag->id)->count())->toBe(3);

        $post(['action' => 'untag', 'tag_id' => $tag->id]);
        expect(DB::table('taggables')->where('tag_id', $tag->id)->count())->toBe(0);
    });

    it('processes the cases that can be processed and skips the rest', function () {
        $ok = ($this->makeCase)();
        $already = ($this->makeCase)();
        app(MoveCaseStage::class)->handle($already, ($this->stage)('counseling'), $this->admin);

        $this->actingAs($this->admin)->post('/cases/bulk', ['action' => 'move', 'ids' => [$ok->uuid, $already->uuid, (string) Str::uuid()], 'stage_id' => ($this->stage)('counseling')->id])
            ->assertSessionHasNoErrors();

        expect($ok->fresh()->stage->code)->toBe('counseling')->and($ok->stageHistories()->count())->toBe(2)
            ->and($already->stageHistories()->count())->toBe(2); // the one already there is refused, not duplicated
    });

    it('applies the lost-reason rule per case', function () {
        $cases = collect(range(1, 2))->map(fn () => ($this->makeCase)());
        $ids = $cases->pluck('uuid')->all();

        $this->actingAs($this->admin)->post('/cases/bulk', ['action' => 'move', 'ids' => $ids, 'stage_id' => ($this->stage)('lost')->id]);
        expect(AdmissionCase::query()->whereIn('uuid', $ids)->where('status', 'lost')->count())->toBe(0);

        $this->actingAs($this->admin)->post('/cases/bulk', ['action' => 'move', 'ids' => $ids, 'stage_id' => ($this->stage)('lost')->id, 'lost_reason_id' => LostReason::query()->first()->id]);
        expect(AdmissionCase::query()->whereIn('uuid', $ids)->where('status', 'lost')->count())->toBe(2);
    });

    it('checks permissions and scope for every case', function () {
        $counselor = ($this->counselor)();
        $mine = ($this->makeCase)([], ['owner_id' => $counselor->id]);
        $notMine = ($this->makeCase)();
        $other = User::factory()->for($this->org)->create();
        $tag = Tag::factory()->for($this->org)->create();

        // counselors may not assign
        $this->actingAs($counselor)->post('/cases/bulk', ['action' => 'assign', 'ids' => [$mine->uuid], 'owner_id' => $other->id])->assertSessionHasNoErrors();
        expect($mine->fresh()->owner_id)->toBe($counselor->id);

        // they may tag their own cases, but not one outside their scope
        $this->actingAs($counselor)->post('/cases/bulk', ['action' => 'tag', 'ids' => [$mine->uuid, $notMine->uuid], 'tag_id' => $tag->id]);
        expect($mine->fresh()->tags()->count())->toBe(1)->and($notMine->fresh()->tags()->count())->toBe(0);
    });

    it('validates the request', function () {
        $case = ($this->makeCase)();

        $this->actingAs($this->admin)->post('/cases/bulk', ['action' => 'explode', 'ids' => [$case->uuid]])->assertSessionHasErrors('action');
        $this->actingAs($this->admin)->post('/cases/bulk', ['action' => 'tag', 'ids' => []])->assertSessionHasErrors('ids');
        $this->actingAs($this->admin)->post('/cases/bulk', ['action' => 'tag', 'ids' => ['not-a-uuid']])->assertSessionHasErrors('ids.0');
        $this->actingAs($this->admin)->post('/cases/bulk', ['action' => 'tag', 'ids' => array_fill(0, 201, (string) Str::uuid())])->assertSessionHasErrors('ids');
        $this->actingAs(User::factory()->for($this->org)->create())->post('/cases/bulk', ['action' => 'tag', 'ids' => [$case->uuid]])->assertForbidden();
    });
});
