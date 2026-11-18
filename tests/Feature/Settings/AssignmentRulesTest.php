<?php

use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Organization\Actions\SaveTeam;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Platform\Models\AssignmentRule;
use App\Domain\Platform\Services\AssignmentSettings;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');
    $this->viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->viewer->givePermissionTo('assignment_rule.view');
    $this->u1 = User::factory()->for($this->org)->create();
    $this->u2 = User::factory()->for($this->org)->create();
    $this->actingAs($this->admin);
    $this->team = app(SaveTeam::class)->handle(null, ['name' => 'Nhóm Hà Nội', 'leader_id' => $this->u1->id, 'member_ids' => [$this->u2->id]]);

    $this->payload = fn (array $extra = []) => $extra + ['name' => 'Facebook về nhóm Hà Nội', 'priority' => 10, 'strategy' => 'round_robin', 'is_active' => true, 'user_ids' => [$this->u1->id, $this->u2->id]];
});

it('lists the rules in priority order with readable conditions and people', function () {
    $facebook = LeadSource::query()->where('code', 'facebook_ads')->value('id');
    AssignmentRule::factory()->create(['organization_id' => $this->org->id, 'name' => 'Sau', 'priority' => 50, 'strategy' => 'fixed', 'user_ids' => [$this->u1->id]]);
    AssignmentRule::factory()->create(['organization_id' => $this->org->id, 'name' => 'Trước', 'priority' => 5, 'strategy' => 'round_robin', 'user_ids' => [$this->u1->id, $this->u2->id], 'team_id' => $this->team->id,
        'conditions' => ['source_ids' => [$facebook], 'school_tiers' => ['A']]]);

    $this->get('/settings/assignment-rules')->assertOk()->assertInertia(fn (Assert $page) => $page->component('AssignmentRules/Index')
        ->where('rules.0.name', 'Trước')->where('rules.1.name', 'Sau')
        ->where('rules.0.strategy_label', 'Xoay vòng')->where('rules.0.team.label', 'Nhóm Hà Nội')->has('rules.0.users', 2)
        ->where('rules.0.condition_labels.0.title', 'Nguồn')->where('rules.0.condition_labels.0.values.0', 'Facebook Ads')->where('rules.0.condition_labels.1.values.0', 'Hạng A')
        ->where('rules.1.condition_labels', [])->has('options.strategies', 4)->where('options.sources', fn ($s) => count($s) > 3)
        ->where('settings', ['sla_alerts' => true, 'recall_after_days' => null])->where('can.manage', true)
        ->where('stages', fn ($stages) => collect($stages)->firstWhere('name', 'Mới')['sla_hours'] === 24));
});

it('shows the screen read-only to someone who may only view', function () {
    $this->actingAs($this->viewer)->get('/settings/assignment-rules')->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
    $this->actingAs($this->viewer)->post('/settings/assignment-rules', ($this->payload)())->assertForbidden();
    $this->actingAs($this->viewer)->put('/settings/assignment-rules/settings', ['sla_alerts' => true])->assertForbidden();
});

it('needs assignment_rule.view', function () {
    $this->actingAs($this->counselor)->get('/settings/assignment-rules')->assertForbidden();
});

it('creates a rule and keeps only clean conditions', function () {
    $facebook = LeadSource::query()->where('code', 'facebook_ads')->value('id');

    $this->post('/settings/assignment-rules', ($this->payload)(['conditions' => [
        'source_ids' => [$facebook, (string) $facebook, 'abc', 0], 'school_tiers' => ['B', 'A', 'B'], 'province_ids' => [], 'major_ids' => null, 'hack' => [1],
    ], 'user_ids' => [$this->u1->id, $this->u1->id]]))->assertSessionHasNoErrors();

    $rule = AssignmentRule::query()->firstOrFail();
    expect($rule->name)->toBe('Facebook về nhóm Hà Nội')->and($rule->priority)->toBe(10)->and($rule->strategy->value)->toBe('round_robin')->and($rule->is_active)->toBeTrue()
        ->and($rule->user_ids)->toBe([$this->u1->id])->and($rule->conditions)->toBe(['source_ids' => [$facebook], 'school_tiers' => ['B', 'A']])
        ->and($rule->created_by)->toBe($this->admin->id);
});

it('stores no conditions when none are chosen, and a default priority', function () {
    $this->post('/settings/assignment-rules', ($this->payload)(['priority' => null, 'conditions' => ['source_ids' => []]]))->assertSessionHasNoErrors();

    $rule = AssignmentRule::query()->firstOrFail();
    expect($rule->conditions)->toBeNull()->and($rule->priority)->toBe(100);
});

it('a territory rule carries no people or team', function () {
    $this->post('/settings/assignment-rules', ['name' => 'Theo vùng', 'strategy' => 'territory', 'user_ids' => [$this->u1->id], 'team_id' => $this->team->id])->assertSessionHasNoErrors();

    $rule = AssignmentRule::query()->firstOrFail();
    expect($rule->user_ids)->toBe([])->and($rule->team_id)->toBeNull();
});

it('a team alone is enough for round robin and load balance, but not for a fixed rule', function () {
    $this->post('/settings/assignment-rules', ['name' => 'Nhóm', 'strategy' => 'round_robin', 'team_id' => $this->team->id])->assertSessionHasNoErrors();
    $this->post('/settings/assignment-rules', ['name' => 'Tải', 'strategy' => 'load_balance', 'team_id' => $this->team->id])->assertSessionHasNoErrors();
    $this->post('/settings/assignment-rules', ['name' => 'Cố định', 'strategy' => 'fixed', 'team_id' => $this->team->id])->assertSessionHasErrors('user_ids');
    $this->post('/settings/assignment-rules', ['name' => 'Trống', 'strategy' => 'round_robin'])->assertSessionHasErrors('user_ids');

    expect(AssignmentRule::query()->count())->toBe(2);
});

it('validates the form', function () {
    $this->post('/settings/assignment-rules', [])->assertSessionHasErrors(['name', 'strategy']);
    $this->post('/settings/assignment-rules', ($this->payload)(['strategy' => 'magic']))->assertSessionHasErrors('strategy');
    $this->post('/settings/assignment-rules', ($this->payload)(['priority' => 0]))->assertSessionHasErrors('priority');
    $this->post('/settings/assignment-rules', ($this->payload)(['priority' => 10000]))->assertSessionHasErrors('priority');
    $this->post('/settings/assignment-rules', ($this->payload)(['conditions' => ['school_tiers' => ['Z']]]))->assertSessionHasErrors('conditions.school_tiers.0');
    $this->post('/settings/assignment-rules', ($this->payload)(['user_ids' => [999999]]))->assertSessionHasErrors('user_ids.0');
    $this->post('/settings/assignment-rules', ($this->payload)(['team_id' => 999999]))->assertSessionHasErrors('team_id');

    expect(AssignmentRule::query()->count())->toBe(0);
});

it('refuses people and teams of another organization', function () {
    $other = Organization::factory()->create();
    $stranger = User::factory()->for($other)->create();

    $this->post('/settings/assignment-rules', ($this->payload)(['user_ids' => [$stranger->id]]))->assertSessionHasErrors('user_ids.0');
});

it('edits a rule and switches it off', function () {
    $rule = AssignmentRule::factory()->create(['organization_id' => $this->org->id, 'strategy' => 'fixed', 'user_ids' => [$this->u1->id]]);

    $this->put("/settings/assignment-rules/{$rule->id}", ($this->payload)(['name' => 'Đã sửa', 'strategy' => 'load_balance', 'is_active' => false]))->assertSessionHasNoErrors();

    $rule->refresh();
    expect($rule->name)->toBe('Đã sửa')->and($rule->strategy->value)->toBe('load_balance')->and($rule->is_active)->toBeFalse()->and($rule->updated_by)->toBe($this->admin->id);
});

it('deletes a rule softly', function () {
    $rule = AssignmentRule::factory()->create(['organization_id' => $this->org->id, 'strategy' => 'fixed', 'user_ids' => [$this->u1->id]]);

    $this->delete("/settings/assignment-rules/{$rule->id}")->assertSessionHasNoErrors();

    expect(AssignmentRule::query()->count())->toBe(0)->and(AssignmentRule::query()->withTrashed()->count())->toBe(1);
});

it('cannot touch the rules of another organization', function () {
    $other = Organization::factory()->create();
    $rule = AssignmentRule::factory()->create(['organization_id' => $other->id, 'strategy' => 'fixed', 'user_ids' => [1]]);

    $this->put("/settings/assignment-rules/{$rule->id}", ($this->payload)())->assertNotFound();
    $this->delete("/settings/assignment-rules/{$rule->id}")->assertNotFound();
    $this->get('/settings/assignment-rules')->assertInertia(fn (Assert $page) => $page->has('rules', 0));
});

it('saves the SLA and recall settings, and validates them', function () {
    $this->put('/settings/assignment-rules/settings', ['sla_alerts' => false, 'recall_after_days' => 21])->assertSessionHasNoErrors();
    expect(app(AssignmentSettings::class)->all($this->org->refresh()))->toBe(['sla_alerts' => false, 'recall_after_days' => 21]);

    $this->put('/settings/assignment-rules/settings', ['sla_alerts' => true, 'recall_after_days' => null])->assertSessionHasNoErrors();
    expect(app(AssignmentSettings::class)->all($this->org->refresh()))->toBe(['sla_alerts' => true, 'recall_after_days' => null]);

    $this->put('/settings/assignment-rules/settings', ['sla_alerts' => true, 'recall_after_days' => 0])->assertSessionHasErrors('recall_after_days');
    $this->put('/settings/assignment-rules/settings', ['sla_alerts' => true, 'recall_after_days' => 400])->assertSessionHasErrors('recall_after_days');
    $this->put('/settings/assignment-rules/settings', ['recall_after_days' => 5])->assertSessionHasErrors('sla_alerts');
});

it('is in the menu for people who may view it', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($nav) => str_contains(json_encode($nav, JSON_UNESCAPED_SLASHES), '/settings/assignment-rules')));
    $this->actingAs($this->counselor)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($nav) => ! str_contains(json_encode($nav, JSON_UNESCAPED_SLASHES), '/settings/assignment-rules')));
});
