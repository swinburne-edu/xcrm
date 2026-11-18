<?php

namespace App\Domain\Campaign\Services;

use App\Domain\Campaign\Models\Campaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provisional KPIs of a campaign (and its sub-campaigns), counted from admission_cases.
 * Until that table exists (task 1.11) `available` is false and the counters are null.
 * Applications and enrolled counts need the case stages and arrive with the reports (tasks 1.11+); they stay null here.
 */
class CampaignKpi
{
    /**
     * @return array{available: bool, leads: int|null, applications: int|null, enrolled: int|null, cost_per_lead: float|null, cost_per_enrolled: float|null}
     */
    public function for(Campaign $campaign): array
    {
        $empty = ['available' => false, 'leads' => null, 'applications' => null, 'enrolled' => null, 'cost_per_lead' => null, 'cost_per_enrolled' => null];

        if (! Schema::hasTable('admission_cases') || ! Schema::hasColumn('admission_cases', 'campaign_id')) {
            return $empty;
        }

        $ids = $campaign->children()->pluck('id')->push($campaign->id);
        $leads = DB::table('admission_cases')->whereIn('campaign_id', $ids)->count();
        $cost = $campaign->actual_cost === null ? null : (float) $campaign->actual_cost;

        return [
            'available' => true,
            'leads' => $leads,
            'applications' => null,
            'enrolled' => null,
            'cost_per_lead' => $cost !== null && $leads > 0 ? round($cost / $leads, 0) : null,
            'cost_per_enrolled' => null,
        ];
    }
}
