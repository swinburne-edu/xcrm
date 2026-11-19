<?php

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignCost;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->campaign = Campaign::factory()->for($this->org)->create(['cycle_id' => $this->cycle->id]);
});

function costUrl(Campaign $c, ?CampaignCost $cost = null): string
{
    return "/campaigns/{$c->id}/costs".($cost ? "/{$cost->id}" : '');
}

it('adds, edits and deletes costs and keeps actual_cost equal to their sum', function () {
    $this->actingAs($this->admin)->post(costUrl($this->campaign), ['date' => '2027-01-05', 'amount' => 1000000, 'note' => 'Quảng cáo'])->assertRedirect();
    $this->actingAs($this->admin)->post(costUrl($this->campaign), ['date' => '2027-01-06', 'amount' => 500000])->assertRedirect();

    expect((int) $this->campaign->fresh()->actual_cost)->toBe(1500000);

    $first = CampaignCost::query()->where('amount', 1000000)->firstOrFail();
    $this->actingAs($this->admin)->put(costUrl($this->campaign, $first), ['date' => '2027-01-05', 'amount' => 2000000])->assertRedirect();
    expect((int) $this->campaign->fresh()->actual_cost)->toBe(2500000);

    $this->actingAs($this->admin)->delete(costUrl($this->campaign, $first))->assertRedirect();
    expect((int) $this->campaign->fresh()->actual_cost)->toBe(500000);

    $this->actingAs($this->admin)->delete(costUrl($this->campaign, CampaignCost::query()->firstOrFail()));
    expect($this->campaign->fresh()->actual_cost)->toBeNull();
});

it('validates the cost', function () {
    $this->actingAs($this->admin)->post(costUrl($this->campaign), ['date' => 'x', 'amount' => -5])->assertSessionHasErrors(['date', 'amount']);
});

it('shows the costs on the campaign page', function () {
    CampaignCost::query()->create(['campaign_id' => $this->campaign->id, 'date' => '2027-02-01', 'amount' => 700000]);

    $this->actingAs($this->admin)->get("/campaigns/{$this->campaign->id}")->assertInertia(
        fn (Assert $p) => $p->component('Campaigns/Show')->has('costs', 1)->where('costs.0.amount', 700000)->where('can.costs', true),
    );
});

it('forbids users without campaign.update and costs of a closed cycle', function () {
    $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $viewer->givePermissionTo('campaign.view');

    $this->actingAs($viewer)->post(costUrl($this->campaign), ['date' => '2027-01-05', 'amount' => 1])->assertForbidden();

    $this->cycle->update(['status' => 'closed']);
    $this->actingAs($this->admin)->post(costUrl($this->campaign), ['date' => '2027-01-05', 'amount' => 1])->assertForbidden();
});

it('does not reach costs or campaigns of another organization', function () {
    $other = Campaign::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($this->admin)->post(costUrl($other), ['date' => '2027-01-05', 'amount' => 1])->assertNotFound();
});

it('compares cost per lead, per enrolled and roi across cycles and campaigns', function () {
    CampaignCost::query()->create(['campaign_id' => $this->campaign->id, 'date' => '2027-01-05', 'amount' => 1000000]);
    $this->campaign->update(['actual_cost' => 1000000]);

    $cases = AdmissionCase::factory()->count(4)->for($this->org)->create(['cycle_id' => $this->cycle->id, 'campaign_id' => $this->campaign->id]);
    $cases->take(2)->each->update(['status' => 'won']);

    $this->actingAs($this->admin)->get('/campaigns/performance?revenue=5000000')->assertInertia(
        fn (Assert $p) => $p->component('Campaigns/Performance')
            ->where('cycles.0.cost', 1000000)
            ->where('cycles.0.leads', 4)
            ->where('cycles.0.enrolled', 2)
            ->where('cycles.0.cost_per_lead', 250000)
            ->where('cycles.0.cost_per_enrolled', 500000)
            ->where('cycles.0.conversion', 50)
            ->where('cycles.0.roi', 900)
            ->where('campaigns.0.leads', 4),
    );
});

it('leaves ratios empty when there is no cost or no lead', function () {
    $this->actingAs($this->admin)->get('/campaigns/performance')->assertInertia(
        fn (Assert $p) => $p->where('cycles.0.cost_per_lead', null)->where('cycles.0.cost_per_enrolled', null)->where('cycles.0.roi', null),
    );
});
