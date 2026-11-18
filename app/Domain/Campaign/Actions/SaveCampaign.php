<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignTarget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a campaign (CampaignRequest) with its targets. The cycle is chosen once, at
 * creation; a new campaign without an owner belongs to its creator.
 */
class SaveCampaign
{
    /** form key => morph alias */
    private const TARGET_KEYS = ['majors' => 'major', 'provinces' => 'province', 'high_schools' => 'high_school'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Campaign $campaign, array $data): Campaign
    {
        return DB::transaction(function () use ($campaign, $data): Campaign {
            $isNew = $campaign === null;
            $campaign ??= new Campaign(['cycle_id' => $data['cycle_id']]);

            $campaign->fill([
                'parent_id' => $data['parent_id'] ?? null,
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'] ?? null,
                'channel' => $data['channel'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'budget' => $data['budget'] ?? null,
                'actual_cost' => $data['actual_cost'] ?? null,
                'target_leads' => $data['target_leads'] ?? null,
                'target_applications' => $data['target_applications'] ?? null,
                'target_enrolled' => $data['target_enrolled'] ?? null,
                'owner_id' => $data['owner_id'] ?? ($isNew ? Auth::id() : null),
                'landing_url' => $data['landing_url'] ?? null,
                'description' => $data['description'] ?? null,
            ])->save();

            if (array_key_exists('targets', $data)) {
                $this->syncTargets($campaign, $data['targets'] ?? []);
            }

            return $campaign->refresh();
        });
    }

    /**
     * @param  array<string, list<int|string>>  $targets
     */
    private function syncTargets(Campaign $campaign, array $targets): void
    {
        $campaign->targets()->delete();

        foreach (self::TARGET_KEYS as $key => $alias) {
            foreach (array_unique($targets[$key] ?? []) as $id) {
                CampaignTarget::query()->create(['campaign_id' => $campaign->id, 'targetable_type' => $alias, 'targetable_id' => (int) $id]);
            }
        }
    }
}
