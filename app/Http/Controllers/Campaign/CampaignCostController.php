<?php

namespace App\Http\Controllers\Campaign;

use App\Domain\Campaign\Actions\SaveCampaignCost;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignCost;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\CampaignCostRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Daily costs of a campaign: /campaigns/{id}/costs. Authorization: CampaignPolicy::manageCosts. */
class CampaignCostController extends Controller
{
    public function store(CampaignCostRequest $request, int $id, SaveCampaignCost $save): RedirectResponse
    {
        $campaign = $this->campaign($id);
        $save->handle($campaign, null, $request->validated());

        return $this->toast('Đã thêm chi phí.');
    }

    public function update(CampaignCostRequest $request, int $id, int $costId, SaveCampaignCost $save): RedirectResponse
    {
        $campaign = $this->campaign($id);
        $save->handle($campaign, $this->cost($campaign, $costId), $request->validated());

        return $this->toast('Đã cập nhật chi phí.');
    }

    public function destroy(int $id, int $costId, SaveCampaignCost $save): RedirectResponse
    {
        $campaign = $this->campaign($id);
        $save->delete($campaign, $this->cost($campaign, $costId));

        return $this->toast('Đã xoá chi phí.');
    }

    private function campaign(int $id): Campaign
    {
        $campaign = Campaign::query()->with('cycle')->findOrFail($id);
        $this->authorize('manageCosts', $campaign);

        return $campaign;
    }

    private function cost(Campaign $campaign, int $costId): CampaignCost
    {
        return CampaignCost::query()->where('campaign_id', $campaign->id)->findOrFail($costId);
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
