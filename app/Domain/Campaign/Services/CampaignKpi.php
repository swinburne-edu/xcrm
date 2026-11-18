<?php

namespace App\Domain\Campaign\Services;

use App\Domain\Campaign\Models\Campaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provisional KPIs of a campaign (and its sub-campaigns), counted from admission_cases.
 * leads = cases of the campaign's cycle, enrolled = cases that reached a "won" stage. The count of
 * submitted applications stays null until the applications table exists. `available` is false only
 * if the cases table is missing.
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
        $cases = DB::table('admission_cases')->whereIn('campaign_id', $ids)->where('cycle_id', $campaign->cycle_id)->whereNull('deleted_at');
        $leads = (clone $cases)->count();
        $enrolled = (clone $cases)->where('status', 'won')->count();
        $cost = $campaign->actual_cost === null ? null : (float) $campaign->actual_cost;

        return [
            'available' => true,
            'leads' => $leads,
            'applications' => null,
            'enrolled' => $enrolled,
            'cost_per_lead' => $cost !== null && $leads > 0 ? round($cost / $leads, 0) : null,
            'cost_per_enrolled' => $cost !== null && $enrolled > 0 ? round($cost / $enrolled, 0) : null,
        ];
    }
}
