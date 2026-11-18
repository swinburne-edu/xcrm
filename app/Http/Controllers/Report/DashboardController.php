<?php

namespace App\Http\Controllers\Report;

use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Report\Services\FunnelReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /dashboard: the cycle selected in the header at a glance (KPI cards, funnel by stage, new applicants per day, top
 * sources and schools, quota progress, my overdue tasks). Open to every signed-in user; the numbers follow their data scope.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentCycle $currentCycle, FunnelReport $report): Response
    {
        $cycle = $currentCycle->get();

        return Inertia::render('Dashboard', [
            'cycle' => $cycle?->only(['id', 'name']),
            'data' => $cycle === null ? null : $report->overview($cycle, $request->user()),
        ]);
    }
}
