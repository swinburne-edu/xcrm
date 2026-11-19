<?php

namespace App\Domain\Campaign\Services;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Cycle\Models\AdmissionCycle;
use Illuminate\Support\Facades\DB;

/**
 * Cost efficiency (task 2.8): per cycle for the cycle comparison, and per campaign inside one cycle.
 * Cost = campaigns.actual_cost (kept equal to the sum of campaign_costs); leads = cases attributed to the campaign;
 * enrolled = those that were won. Both queries go through the Campaign / AdmissionCycle models, so they are
 * already limited to the current organization.
 */
class CampaignPerformance
{
    /** @return list<array<string, mixed>> one row per cycle, newest first */
    public function byCycle(?float $revenuePerEnrolled = null): array
    {
        $cycles = AdmissionCycle::query()->orderByDesc('year')->orderByDesc('id')->get(['id', 'name', 'year']);
        $campaigns = Campaign::query()->get(['id', 'cycle_id', 'actual_cost'])->groupBy('cycle_id');
        $counts = $this->counts(Campaign::query()->pluck('id')->all())->groupBy('campaign_id');

        return $cycles->map(function (AdmissionCycle $cycle) use ($campaigns, $counts, $revenuePerEnrolled) {
            $own = $campaigns->get($cycle->id, collect());
            $ids = $own->pluck('id');
            $cost = (float) $own->sum('actual_cost');
            $leads = (int) $ids->sum(fn ($id) => $counts->get($id)?->first()->leads ?? 0);
            $enrolled = (int) $ids->sum(fn ($id) => $counts->get($id)?->first()->enrolled ?? 0);

            return ['id' => $cycle->id, 'name' => $cycle->name, 'campaigns' => $own->count()] + $this->metrics($cost, $leads, $enrolled, $revenuePerEnrolled);
        })->values()->all();
    }

    /** @return list<array<string, mixed>> one row per campaign of the cycle */
    public function byCampaign(int $cycleId, ?float $revenuePerEnrolled = null): array
    {
        $campaigns = Campaign::query()->where('cycle_id', $cycleId)->orderBy('name')->get(['id', 'code', 'name', 'channel', 'budget', 'actual_cost']);
        $counts = $this->counts($campaigns->pluck('id')->all())->keyBy('campaign_id');

        return $campaigns->map(fn (Campaign $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'channel_label' => $c->channel?->label(),
            'budget' => $c->budget === null ? null : (int) $c->budget,
        ] + $this->metrics((float) $c->actual_cost, (int) ($counts->get($c->id)->leads ?? 0), (int) ($counts->get($c->id)->enrolled ?? 0), $revenuePerEnrolled))->all();
    }

    /** @param  list<int>  $campaignIds */
    private function counts(array $campaignIds)
    {
        if ($campaignIds === []) {
            return collect();
        }

        return DB::table('admission_cases')
            ->whereIn('campaign_id', $campaignIds)
            ->whereNull('deleted_at')
            ->groupBy('campaign_id')
            ->selectRaw("campaign_id, count(*) as leads, sum(case when status = 'won' then 1 else 0 end) as enrolled")
            ->get();
    }

    /** @return array<string, int|float|null> */
    private function metrics(float $cost, int $leads, int $enrolled, ?float $revenuePerEnrolled): array
    {
        $revenue = $revenuePerEnrolled === null ? null : $revenuePerEnrolled * $enrolled;

        return [
            'cost' => (int) $cost,
            'leads' => $leads,
            'enrolled' => $enrolled,
            'cost_per_lead' => $cost > 0 && $leads > 0 ? (int) round($cost / $leads) : null,
            'cost_per_enrolled' => $cost > 0 && $enrolled > 0 ? (int) round($cost / $enrolled) : null,
            'conversion' => $leads > 0 ? round($enrolled / $leads * 100, 1) : null,
            'roi' => $revenue !== null && $cost > 0 ? round(($revenue - $cost) / $cost * 100, 1) : null,
        ];
    }
}
