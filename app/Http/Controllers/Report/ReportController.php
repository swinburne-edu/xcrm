<?php

namespace App\Http\Controllers\Report;

use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Report\Exports\BreakdownExport;
use App\Domain\Report\Services\FunnelReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * /reports: the admission funnel by source, campaign, high school, province, major or counselor (table + chart),
 * with Excel export. Needs report.view (export: report.export); numbers follow the user's data scope.
 */
class ReportController extends Controller
{
    public function index(Request $request, CurrentCycle $currentCycle, FunnelReport $report): Response
    {
        abort_unless($request->user()->can('report.view'), 403);

        $dimension = $this->dimension($request);
        $cycle = $currentCycle->get();

        return Inertia::render('Reports/Index', [
            'cycle' => $cycle?->only(['id', 'name']),
            'dimension' => $dimension,
            'dimensions' => collect(FunnelReport::DIMENSIONS)->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])->values()->all(),
            'rows' => $cycle === null ? [] : $report->breakdown($cycle, $dimension),
            'can' => ['export' => $request->user()->can('report.export')],
        ]);
    }

    public function export(Request $request, CurrentCycle $currentCycle, FunnelReport $report): BinaryFileResponse
    {
        abort_unless($request->user()->can('report.export'), 403);

        $cycle = $currentCycle->get() ?? abort(422, 'Chưa có mùa tuyển sinh.');
        $dimension = $this->dimension($request);
        $name = 'bao-cao-pheu-'.$dimension.'-'.now()->format('Ymd').'.xlsx';

        return Excel::download(new BreakdownExport(FunnelReport::DIMENSIONS[$dimension], $report->breakdown($cycle, $dimension)), $name);
    }

    private function dimension(Request $request): string
    {
        $value = $request->query('dimension');

        return is_string($value) && array_key_exists($value, FunnelReport::DIMENSIONS) ? $value : 'source';
    }
}
