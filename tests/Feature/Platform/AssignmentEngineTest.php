<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\Province;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Intake\Actions\IntakeLead;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Actions\SaveTeam;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Models\TerritoryAssignment;
use App\Domain\Platform\Models\AssignmentRule;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    foreach ([1, 2, 3] as $i) {
        $this->{"u{$i}"} = User::factory()->for($this->org)->create(['name' => "Tư vấn {$i}", 'data_scope' => DataScopeLevel::Own]);
        $this->{"u{$i}"}->assignRole('counselor');
    }
    $this->gone = User::factory()->for($this->org)->create(['is_active' => false]);
    $this->source = fn (string $code) => LeadSource::query()->where('code', $code)->value('id');

    /** A new case for a fresh applicant; $data are the CreateAdmissionCase options. */
    $this->open = fn (array $applicant = [], array $data = []): AdmissionCase => app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create($applicant), $this->cycle, $data, $this->admin);
    $this->rule = fn (array $attributes = []) => AssignmentRule::factory()->create(['organization_id' => $this->org->id] + $attributes);
});

it('leaves a case in the pool when there are no rules', function () {
    $case = ($this->open)();

    expect($case->owner_id)->toBeNull()->and($case->assigned_at)->toBeNull();
});

it('fixed: the first active user of the list', function () {
    ($this->rule)(['strategy' => 'fixed', 'user_ids' => [$this->gone->id, $this->u2->id, $this->u1->id]]);

    $case = ($this->open)();

    expect($case->owner_id)->toBe($this->u2->id)->and($case->assigned_at)->not->toBeNull();
});

it('round robin: hands out cases in turn to whoever was given one longest ago', function () {
    ($this->rule)(['strategy' => 'round_robin', 'user_ids' => [$this->u1->id, $this->u2->id, $this->u3->id, $this->gone->id]]);
    $owners = [];

    foreach (range(1, 7) as $i) {
        $this->travel(1)->minutes();
        $owners[] = ($this->open)()->owner_id;
    }

    expect($owners)->toBe([$this->u1->id, $this->u2->id, $this->u3->id, $this->u1->id, $this->u2->id, $this->u3->id, $this->u1->id]);
});

it('round robin skips someone who gets deactivated', function () {
    ($this->rule)(['strategy' => 'round_robin', 'user_ids' => [$this->u1->id, $this->u2->id]]);
    $first = ($this->open)()->owner_id;
    $this->u2->update(['is_active' => false]);
    $this->travel(1)->minutes();

    expect($first)->toBe($this->u1->id)->and(($this->open)()->owner_id)->toBe($this->u1->id);
});

it('load balance: the user with the fewest open cases; closed cases do not count', function () {
    ($this->rule)(['strategy' => 'load_balance', 'user_ids' => [$this->u1->id, $this->u2->id, $this->u3->id]]);
    $this->travel(1)->minutes();

    foreach ([[$this->u1, 'open'], [$this->u1, 'open'], [$this->u2, 'open'], [$this->u3, 'lost'], [$this->u3, 'won']] as [$user, $status]) {
        $case = ($this->open)([], ['owner_id' => $user->id]);
        AdmissionCase::query()->withoutGlobalScopes()->whereKey($case->id)->update(['status' => $status]);
    }

    // open cases: u1 = 2, u2 = 1, u3 = 0 (a lost and a won one do not count)
    expect(($this->open)()->owner_id)->toBe($this->u3->id)
        ->and(($this->open)()->owner_id)->toBe($this->u2->id) // u3 now has 1 open like u2: the tie goes to whoever was given a case longest ago
        ->and(AdmissionCase::query()->where('owner_id', $this->u1->id)->count())->toBe(2);
});

it('tries rules by priority and only those whose conditions match', function () {
    $facebook = ($this->source)('facebook_ads');
    $web = ($this->source)('website');
    ($this->rule)(['name' => 'catch-all', 'priority' => 50, 'strategy' => 'fixed', 'user_ids' => [$this->u3->id]]);
    ($this->rule)(['name' => 'facebook', 'priority' => 10, 'strategy' => 'fixed', 'user_ids' => [$this->u1->id], 'conditions' => ['source_ids' => [$facebook]]]);
    ($this->rule)(['name' => 'off', 'priority' => 1, 'strategy' => 'fixed', 'user_ids' => [$this->u2->id], 'is_active' => false]);

    expect(($this->open)([], ['source_id' => $facebook])->owner_id)->toBe($this->u1->id)
        ->and(($this->open)([], ['source_id' => $web])->owner_id)->toBe($this->u3->id)
        ->and(($this->open)()->owner_id)->toBe($this->u3->id);
});

it('matches on province, school, school tier, campaign and major', function () {
    $hanoi = Province::factory()->create();
    $school = HighSchool::factory()->for($this->org)->create(['tier' => 'A', 'province_id' => $hanoi->id]);
    $other = HighSchool::factory()->for($this->org)->create(['tier' => 'C']);
    $major = Major::factory()->for($this->org)->create();
    ($this->rule)(['priority' => 1, 'strategy' => 'fixed', 'user_ids' => [$this->u1->id], 'conditions' => ['school_tiers' => ['A']]]);
    ($this->rule)(['priority' => 2, 'strategy' => 'fixed', 'user_ids' => [$this->u2->id], 'conditions' => ['province_ids' => [$hanoi->id + 9999]]]);
    ($this->rule)(['priority' => 3, 'strategy' => 'fixed', 'user_ids' => [$this->u3->id], 'conditions' => ['major_ids' => [$major->id]]]);

    expect(($this->open)(['high_school_id' => $school->id])->owner_id)->toBe($this->u1->id)
        ->and(($this->open)(['high_school_id' => $other->id], ['primary_major_id' => $major->id])->owner_id)->toBe($this->u3->id)
        ->and(($this->open)(['high_school_id' => $other->id])->owner_id)->toBeNull();

    ($this->rule)(['priority' => 4, 'strategy' => 'fixed', 'user_ids' => [$this->u2->id], 'conditions' => ['high_school_ids' => [$other->id], 'province_ids' => []]]);
    expect(($this->open)(['high_school_id' => $other->id])->owner_id)->toBe($this->u2->id);
});

it('needs every condition key to match, any value within a key', function () {
    $facebook = ($this->source)('facebook_ads');
    $tiktok = ($this->source)('tiktok_ads');
    $school = HighSchool::factory()->for($this->org)->create(['tier' => 'B']);
    ($this->rule)(['strategy' => 'fixed', 'user_ids' => [$this->u1->id], 'conditions' => ['source_ids' => [$facebook, $tiktok], 'school_tiers' => ['A', 'B']]]);

    expect(($this->open)(['high_school_id' => $school->id], ['source_id' => $tiktok])->owner_id)->toBe($this->u1->id)
        ->and(($this->open)(['high_school_id' => $school->id], ['source_id' => ($this->source)('website')])->owner_id)->toBeNull()
        ->and(($this->open)([], ['source_id' => $facebook])->owner_id)->toBeNull();
});

it('skips a matching rule that finds nobody and goes on to the next rule', function () {
    ($this->rule)(['priority' => 1, 'strategy' => 'fixed', 'user_ids' => [$this->gone->id]]);
    ($this->rule)(['priority' => 2, 'strategy' => 'round_robin', 'user_ids' => []]);
    ($this->rule)(['priority' => 3, 'strategy' => 'fixed', 'user_ids' => [$this->u2->id]]);

    expect(($this->open)()->owner_id)->toBe($this->u2->id);
});

it('team: a rule with a team and no users picks among the team members and keeps the team', function () {
    $team = app(SaveTeam::class)->handle(null, ['name' => 'Nhóm Hà Nội', 'leader_id' => $this->u1->id, 'member_ids' => [$this->u2->id, $this->u3->id]]);
    ($this->rule)(['strategy' => 'round_robin', 'user_ids' => [], 'team_id' => $team->id]);

    $a = ($this->open)();
    $this->travel(1)->minutes();
    $b = ($this->open)();

    // the leader is a member of the team too
    expect([$a->owner_id, $b->owner_id])->toBe([$this->u1->id, $this->u2->id])->and($a->team_id)->toBe($team->id)->and($b->team_id)->toBe($team->id);
});

it('takes the team of the chosen user when the rule names none', function () {
    $team = app(SaveTeam::class)->handle(null, ['name' => 'Nhóm A', 'leader_id' => $this->u1->id, 'member_ids' => [$this->u2->id]]);
    ($this->rule)(['strategy' => 'fixed', 'user_ids' => [$this->u2->id]]);

    expect(($this->open)()->team_id)->toBe($team->id);
});

it('territory strategy follows the territory of the school, and falls through when there is none', function () {
    $school = HighSchool::factory()->for($this->org)->create();
    $bare = HighSchool::factory()->for($this->org)->create();
    $territory = Territory::factory()->for($this->org)->create(['user_id' => $this->u3->id]);
    TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'high_school', 'assignable_id' => $school->id]);
    ($this->rule)(['priority' => 1, 'strategy' => 'territory']);
    ($this->rule)(['priority' => 2, 'strategy' => 'fixed', 'user_ids' => [$this->u1->id]]);

    expect(($this->open)(['high_school_id' => $school->id])->owner_id)->toBe($this->u3->id)
        ->and(($this->open)(['high_school_id' => $bare->id])->owner_id)->toBe($this->u1->id);
});

it('an explicit owner (or the explicit pool) beats the rules', function () {
    ($this->rule)(['strategy' => 'fixed', 'user_ids' => [$this->u1->id]]);

    expect(($this->open)([], ['owner_id' => $this->u2->id])->owner_id)->toBe($this->u2->id)
        ->and(($this->open)([], ['owner_id' => null])->owner_id)->toBeNull();
});

it('ignores deleted rules and the rules of another organization', function () {
    $other = Organization::factory()->create();
    AssignmentRule::factory()->create(['organization_id' => $other->id, 'strategy' => 'fixed', 'user_ids' => [$this->u1->id]]);
    ($this->rule)(['strategy' => 'fixed', 'user_ids' => [$this->u2->id]])->delete();

    expect(($this->open)()->owner_id)->toBeNull();
});

it('applies to web leads too (IntakeLead)', function () {
    ($this->rule)(['strategy' => 'fixed', 'user_ids' => [$this->u2->id]]);

    app(CurrentOrganization::class)->run($this->org->id, fn () => app(IntakeLead::class)->handle($this->org->id, ['full_name' => 'Nguyễn Văn Thử', 'phone' => '0912345678']));

    $case = AdmissionCase::query()->withoutGlobalScopes()->where('owner_id', $this->u2->id)->first();
    expect($case)->not->toBeNull()->and($case->applicant->full_name)->toBe('Nguyễn Văn Thử');
});
