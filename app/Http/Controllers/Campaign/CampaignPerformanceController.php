<?php

namespace App\Http\Controllers\Campaign;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignPerformance;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** /campaigns/performance: cost per lead / per enrolled / ROI, compared across cycles and per campaign. */
class CampaignPerformanceController extends Controller
{
    public function __invoke(Request $request, CampaignPerformance $performance, CurrentCycle $currentCycle): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $revenue = $request->query('revenue');
        $revenue = is_numeric($revenue) && (float) $revenue >= 0 ? (float) $revenue : null;
        $cycle = $currentCycle->get();

        return Inertia::render('Campaigns/Performance', [
            'cycles' => $performance->byCycle($revenue),
            'campaigns' => $cycle ? $performance->byCampaign($cycle->id, $revenue) : [],
            'cycle' => $cycle?->only(['id', 'name']),
            'revenue' => $revenue,
        ]);
    }
}
