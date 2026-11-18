<?php

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Models\Province;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
});

function campaignPayload(array $overrides = []): array
{
    return ['cycle_id' => test()->cycle->id, 'code' => 'Tuyen-Sinh-2027', 'name' => 'Tuyển sinh 2027 Facebook', 'type' => 'digital_ads', 'channel' => 'facebook', ...$overrides];
}

function ownCampaign(array $attrs = []): Campaign
{
    return Campaign::factory()->for(test()->org)->create(['cycle_id' => test()->cycle->id, ...$attrs]);
}

/** A user with only the given campaign permissions. */
function campaignUser(array $permissions): User
{
    $user = User::factory()->for(test()->org)->create(['data_scope' => DataScopeLevel::All]);
    $user->givePermissionTo($permissions);

    return $user;
}

describe('list and permissions', function () {
    it('lists the campaigns of the selected cycle in the own organization', function () {
        $mine = ownCampaign();
        Campaign::factory()->for($this->org)->create(['cycle_id' => AdmissionCycle::factory()->for($this->org)->create()->id]);
        Campaign::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->get('/campaigns')
            ->assertInertia(fn (Assert $page) => $page->component('Campaigns/Index')->has('campaigns.data', 1)
                ->where('campaigns.data.0.id', $mine->id)->where('cycle.id', $this->cycle->id)->where('can.create', true));
    });

    it('filters by status, type, channel, owner and search', function () {
        $owner = User::factory()->for($this->org)->create();
        $a = ownCampaign(['name' => 'Facebook mùa hè', 'code' => 'fb-he', 'status' => 'running', 'type' => 'digital_ads', 'channel' => 'facebook', 'owner_id' => $owner->id]);
        ownCampaign(['name' => 'Ngày hội', 'type' => 'open_day', 'channel' => 'offline']);

        foreach (['filter[status]=running', 'filter[type]=digital_ads', 'filter[channel]=facebook', "filter[owner_id]={$owner->id}", 'filter[q]=fb-he'] as $q) {
            $this->actingAs($this->admin)->get("/campaigns?{$q}")
                ->assertInertia(fn (Assert $page) => $page->has('campaigns.data', 1)->where('campaigns.data.0.id', $a->id));
        }
    });

    it('is limited by campaign permissions', function () {
        $campaign = ownCampaign();
        $nobody = campaignUser([]);
        $viewer = campaignUser(['campaign.view']);

        $this->actingAs($nobody)->get('/campaigns')->assertForbidden();
        $this->actingAs($viewer)->get('/campaigns')->assertInertia(fn (Assert $page) => $page->where('can.create', false));
        $this->actingAs($viewer)->post('/campaigns', campaignPayload())->assertForbidden();
        $this->actingAs($viewer)->put("/campaigns/{$campaign->id}", campaignPayload())->assertForbidden();
        $this->actingAs($viewer)->delete("/campaigns/{$campaign->id}")->assertForbidden();
        $this->actingAs($viewer)->get("/campaigns/{$campaign->id}")->assertInertia(fn (Assert $page) => $page->where('can.update', false)->has('transitions', 0));
    });

    it('hides a campaign of another organization and shows the menu item', function () {
        $foreign = Campaign::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->get("/campaigns/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->admin)->get('/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($nav) => str_contains(json_encode($nav, JSON_UNESCAPED_SLASHES), '/campaigns')));
    });

    it('feeds the campaigns lookup of the own organization', function () {
        ownCampaign(['name' => 'Học bổng 2027']);
        Campaign::factory()->for($this->other)->create(['name' => 'Học bổng khác']);

        $this->actingAs($this->admin)->getJson('/lookups/campaigns?q=Học bổng')->assertJsonCount(1, 'data');
    });
});

describe('create and update', function () {
    it('creates a draft with a lower-case code, the creator as owner and its targets', function () {
        $major = Major::factory()->for($this->org)->create();
        $province = Province::factory()->create();
        $school = HighSchool::factory()->for($this->org)->create();

        $response = $this->actingAs($this->admin)->post('/campaigns', campaignPayload([
            'budget' => 50000000, 'target_leads' => 500, 'landing_url' => 'https://xcrm.test/dang-ky',
            'start_date' => '2026-12-01', 'end_date' => '2027-03-31',
            'targets' => ['majors' => [$major->id], 'provinces' => [$province->id], 'high_schools' => [$school->id]],
        ]));

        $campaign = Campaign::query()->where('code', 'tuyen-sinh-2027')->firstOrFail();
        $response->assertRedirect("/campaigns/{$campaign->id}");
        expect($campaign->status)->toBe(CampaignStatus::Draft)->and($campaign->owner_id)->toBe($this->admin->id)
            ->and($campaign->organization_id)->toBe($this->org->id)->and((int) $campaign->budget)->toBe(50000000)
            ->and(DB::table('campaign_targets')->where('campaign_id', $campaign->id)->pluck('targetable_type')->sort()->values()->all())->toBe(['high_school', 'major', 'province']);

        $this->actingAs($this->admin)->get("/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('campaign.targets.majors.0.id', $major->id)->where('campaign.targets.provinces.0.label', $province->name)
                ->where('campaign.targets.high_schools.0.label', $school->name)->where('kpi.available', false));
    });

    it('validates the code, enums, dates and organization-bound references', function () {
        ownCampaign(['code' => 'trung-ma']);
        $foreignMajor = Major::factory()->for($this->other)->create();
        $foreignUser = User::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['code' => 'Có dấu!']))->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['code' => 'TRUNG-MA']))->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['type' => 'x']))->assertSessionHasErrors('type');
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['start_date' => '2027-03-01', 'end_date' => '2027-01-01']))->assertSessionHasErrors('end_date');
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['budget' => -5]))->assertSessionHasErrors('budget');
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['landing_url' => 'không-phải-url']))->assertSessionHasErrors('landing_url');
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['owner_id' => $foreignUser->id]))->assertSessionHasErrors('owner_id');
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['targets' => ['majors' => [$foreignMajor->id]]]))->assertSessionHasErrors('targets.majors.0');
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['cycle_id' => AdmissionCycle::factory()->for($this->other)->create()->id]))->assertSessionHasErrors('cycle_id');
    });

    it('refuses to create in a closed cycle (R8)', function () {
        $closed = AdmissionCycle::factory()->for($this->org)->closed()->create();

        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['cycle_id' => $closed->id]))->assertSessionHasErrors('cycle_id');
    });

    it('keeps the cycle fixed and replaces the targets on update', function () {
        $m1 = Major::factory()->for($this->org)->create();
        $m2 = Major::factory()->for($this->org)->create();
        $campaign = ownCampaign(['code' => 'cd-1']);
        $this->actingAs($this->admin)->put("/campaigns/{$campaign->id}", ['code' => 'cd-1', 'name' => 'A', 'targets' => ['majors' => [$m1->id]]]);
        $this->actingAs($this->admin)->put("/campaigns/{$campaign->id}", ['code' => 'cd-1', 'name' => 'B', 'targets' => ['majors' => [$m2->id]]]);

        expect($campaign->fresh()->name)->toBe('B')->and(DB::table('campaign_targets')->where('campaign_id', $campaign->id)->pluck('targetable_id')->all())->toBe([$m2->id]);

        $this->actingAs($this->admin)->put("/campaigns/{$campaign->id}", ['cycle_id' => $this->cycle->id, 'code' => 'cd-1', 'name' => 'C'])->assertSessionHasErrors('cycle_id');
    });

    it('fixes the code once the campaign leaves draft (utm_campaign)', function () {
        $campaign = ownCampaign(['code' => 'cd-1', 'status' => 'paused']);

        $this->actingAs($this->admin)->put("/campaigns/{$campaign->id}", ['code' => 'cd-2', 'name' => 'Đổi mã'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->put("/campaigns/{$campaign->id}", ['code' => 'cd-1', 'name' => 'Đổi tên'])->assertSessionHasNoErrors();
        expect($campaign->fresh())->code->toBe('cd-1')->name->toBe('Đổi tên');
    });

    it('cannot be edited while pending approval, once ended, or in a closed cycle', function () {
        $pending = ownCampaign(['status' => 'pending_approval']);
        $ended = ownCampaign(['status' => 'ended']);
        $inClosed = Campaign::factory()->for($this->org)->create(['cycle_id' => AdmissionCycle::factory()->for($this->org)->closed()->create()->id]);

        foreach ([$pending, $ended, $inClosed] as $campaign) {
            $this->actingAs($this->admin)->put("/campaigns/{$campaign->id}", ['code' => $campaign->code, 'name' => 'Sửa'])->assertForbidden();
        }
    });

    it('validates the parent: same cycle, not itself, not a descendant', function () {
        $parent = ownCampaign(['code' => 'cha']);
        $child = ownCampaign(['code' => 'con', 'parent_id' => $parent->id]);
        $otherCycle = Campaign::factory()->for($this->org)->create(['cycle_id' => AdmissionCycle::factory()->for($this->org)->create()->id]);

        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['code' => 'moi', 'parent_id' => $parent->id]))->assertSessionDoesntHaveErrors();
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['code' => 'sai', 'parent_id' => $otherCycle->id]))->assertSessionHasErrors('parent_id');
        $this->actingAs($this->admin)->put("/campaigns/{$parent->id}", ['code' => 'cha', 'name' => 'Cha', 'parent_id' => $parent->id])->assertSessionHasErrors('parent_id');
        $this->actingAs($this->admin)->put("/campaigns/{$parent->id}", ['code' => 'cha', 'name' => 'Cha', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');
    });
});

describe('approval flow', function () {
    it('walks draft → pending → running → paused → running → ended', function () {
        $campaign = ownCampaign();
        $step = fn (string $action) => $this->actingAs($this->admin)->post("/campaigns/{$campaign->id}/transition", ['action' => $action]);

        $step('submit')->assertSessionHasNoErrors();
        expect($campaign->fresh()->status)->toBe(CampaignStatus::PendingApproval)->and($campaign->fresh()->approved_by)->toBeNull();

        $step('approve')->assertSessionHasNoErrors();
        expect($campaign->fresh())->status->toBe(CampaignStatus::Running)->approved_by->toBe($this->admin->id)->approved_at->not->toBeNull();

        $step('pause');
        expect($campaign->fresh()->status)->toBe(CampaignStatus::Paused);
        $step('resume');
        expect($campaign->fresh()->status)->toBe(CampaignStatus::Running);
        $step('end');
        expect($campaign->fresh()->status)->toBe(CampaignStatus::Ended);

        $step('resume')->assertStatus(422);
        expect($campaign->fresh()->status)->toBe(CampaignStatus::Ended);
    });

    it('lets the approver send a pending campaign back to draft, clearing the approval', function () {
        $campaign = ownCampaign(['status' => 'pending_approval']);

        $this->actingAs($this->admin)->post("/campaigns/{$campaign->id}/transition", ['action' => 'reject']);

        expect($campaign->fresh())->status->toBe(CampaignStatus::Draft)->approved_by->toBeNull();
    });

    it('rejects actions that do not fit the current status, and unknown actions', function () {
        $draft = ownCampaign();

        foreach (['approve', 'reject', 'pause', 'resume', 'end'] as $action) {
            $this->actingAs($this->admin)->post("/campaigns/{$draft->id}/transition", ['action' => $action])->assertStatus(422);
        }
        $this->actingAs($this->admin)->post("/campaigns/{$draft->id}/transition", ['action' => 'explode'])->assertSessionHasErrors('action');
        expect($draft->fresh()->status)->toBe(CampaignStatus::Draft);
    });

    it('needs campaign.approve to approve or reject, campaign.update for the rest', function () {
        $editor = campaignUser(['campaign.view', 'campaign.update']);
        $approver = campaignUser(['campaign.view', 'campaign.approve']);
        $campaign = ownCampaign();

        $this->actingAs($approver)->post("/campaigns/{$campaign->id}/transition", ['action' => 'submit'])->assertForbidden();
        $this->actingAs($editor)->post("/campaigns/{$campaign->id}/transition", ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->actingAs($editor)->post("/campaigns/{$campaign->id}/transition", ['action' => 'approve'])->assertForbidden();
        $this->actingAs($editor)->post("/campaigns/{$campaign->id}/transition", ['action' => 'reject'])->assertForbidden();
        expect($campaign->fresh()->status)->toBe(CampaignStatus::PendingApproval);

        $this->actingAs($approver)->post("/campaigns/{$campaign->id}/transition", ['action' => 'approve'])->assertSessionHasNoErrors();
        expect($campaign->fresh())->status->toBe(CampaignStatus::Running)->approved_by->toBe($approver->id);
    });

    it('offers only the actions the user may take on the detail page', function () {
        $editor = campaignUser(['campaign.view', 'campaign.update']);
        $campaign = ownCampaign(['status' => 'pending_approval']);

        $this->actingAs($editor)->get("/campaigns/{$campaign->id}")->assertInertia(fn (Assert $page) => $page->has('transitions', 0));
        $this->actingAs($this->admin)->get("/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('transitions.0.action', 'approve')->where('transitions.1.action', 'reject')->has('transitions', 2));

        $running = ownCampaign(['status' => 'running']);
        $this->actingAs($this->admin)->get("/campaigns/{$running->id}")
            ->assertInertia(fn (Assert $page) => $page->where('transitions.0.action', 'pause')->where('transitions.1.action', 'end'));
    });

    it('R8: nothing moves in a closed cycle', function () {
        $closed = AdmissionCycle::factory()->for($this->org)->closed()->create();
        $campaign = Campaign::factory()->for($this->org)->create(['cycle_id' => $closed->id, 'status' => 'running']);

        $this->actingAs($this->admin)->post("/campaigns/{$campaign->id}/transition", ['action' => 'pause'])->assertForbidden();
        expect($campaign->fresh()->status)->toBe(CampaignStatus::Running);
    });
});

describe('delete and KPI', function () {
    it('deletes only drafts, for good (the code is free again)', function () {
        $draft = ownCampaign(['code' => 'xoa-duoc']);
        $running = ownCampaign(['status' => 'running']);

        $this->actingAs($this->admin)->delete("/campaigns/{$running->id}")->assertForbidden();
        $this->actingAs($this->admin)->delete("/campaigns/{$draft->id}")->assertRedirect('/campaigns');

        expect(Campaign::withoutGlobalScopes()->withTrashed()->find($draft->id))->toBeNull()->and(Campaign::find($running->id))->not->toBeNull();
        $this->actingAs($this->admin)->post('/campaigns', campaignPayload(['code' => 'xoa-duoc']))->assertSessionDoesntHaveErrors();
    });

    it('keeps sub-campaigns when the parent is deleted', function () {
        $parent = ownCampaign(['code' => 'cha']);
        $child = ownCampaign(['code' => 'con', 'parent_id' => $parent->id]);

        $this->actingAs($this->admin)->delete("/campaigns/{$parent->id}");

        expect($child->fresh()->parent_id)->toBeNull();
    });

    it('reports KPIs as unavailable until admission cases exist', function () {
        $campaign = ownCampaign(['budget' => 1000000, 'actual_cost' => 400000, 'target_leads' => 100]);
        $child = ownCampaign(['code' => 'con', 'parent_id' => $campaign->id]);

        $this->actingAs($this->admin)->get("/campaigns/{$campaign->id}")
            ->assertInertia(fn (Assert $page) => $page->where('kpi.available', false)->where('kpi.leads', null)
                ->where('campaign.target_leads', 100)->where('campaign.budget', 1000000)->where('children.0.id', $child->id));
    });
});
