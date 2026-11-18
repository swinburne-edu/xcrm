<?php

namespace App\Http\Controllers\Major;

use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\QuotaSnapshot;
use App\Domain\Major\Services\QuotaProgress;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /quotas: quota progress per major for the cycle selected in the header (task 2.3): how many are interested,
 * applied, admitted, confirmed and enrolled against the quota, with a warning when a major is over or behind schedule.
 * ?level=alert|short|over narrows the table; the summary always covers every major.
 */
class QuotaController extends Controller
{
    public function index(Request $request, CurrentCycle $currentCycle, QuotaProgress $progress): Response
    {
        $this->authorize('viewAny', Major::class);

        $cycle = $currentCycle->get();
        $rows = $cycle === null ? [] : $progress->rows($cycle);
        $level = in_array($request->query('level'), ['alert', 'short', 'over'], true) ? (string) $request->query('level') : 'all';

        $shown = match ($level) {
            'alert' => array_values(array_filter($rows, fn (array $r) => $r['level'] !== 'ok')),
            'short', 'over' => array_values(array_filter($rows, fn (array $r) => $r['level'] === $level)),
            default => $rows,
        };

        $share = $cycle === null ? null : $progress->progress($cycle);

        return Inertia::render('Quotas/Index', [
            'cycle' => $cycle ? ['id' => $cycle->id, 'name' => $cycle->name, 'status' => $cycle->status->value, 'progress' => $share === null ? null : round($share * 100)] : null,
            'rows' => $shown,
            'summary' => $progress->summary($rows),
            'level' => $level,
            'last_aggregated_at' => $cycle === null ? null : QuotaSnapshot::query()->where('cycle_id', $cycle->id)->max('updated_at'),
            'rules' => ['short_factor' => QuotaProgress::SHORT_FACTOR, 'min_progress' => QuotaProgress::MIN_PROGRESS * 100],
        ]);
    }
}
