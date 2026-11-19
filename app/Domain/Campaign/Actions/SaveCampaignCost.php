<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignCost;

/** Adds or edits a daily cost, then keeps campaigns.actual_cost equal to the sum of all costs. */
class SaveCampaignCost
{
    /** @param  array{date: string, amount: int|string, note?: string|null}  $data */
    public function handle(Campaign $campaign, ?CampaignCost $cost, array $data): CampaignCost
    {
        $cost ??= new CampaignCost(['campaign_id' => $campaign->id]);
        $cost->fill(['date' => $data['date'], 'amount' => $data['amount'], 'note' => $data['note'] ?? null])->save();

        $this->sync($campaign);

        return $cost;
    }

    public function delete(Campaign $campaign, CampaignCost $cost): void
    {
        $cost->delete();
        $this->sync($campaign);
    }

    /** actual_cost = sum of the rows, or null again when none are left. */
    private function sync(Campaign $campaign): void
    {
        $count = CampaignCost::query()->where('campaign_id', $campaign->id)->count();
        $campaign->actual_cost = $count === 0 ? null : (string) CampaignCost::query()->where('campaign_id', $campaign->id)->sum('amount');
        $campaign->save();
    }
}
