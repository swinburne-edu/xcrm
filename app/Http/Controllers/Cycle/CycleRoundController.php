<?php

namespace App\Http\Controllers\Cycle;

use App\Domain\Cycle\Actions\SaveRound;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cycle\RoundRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Rounds of a cycle. Every change needs cycle.update on a cycle that is not closed (CyclePolicy, rule R8).
 */
class CycleRoundController extends Controller
{
    public function store(RoundRequest $request, AdmissionCycle $cycleModel, SaveRound $save): RedirectResponse
    {
        $this->authorize('update', $cycleModel);

        $round = $save->handle($cycleModel, null, $request->validated());

        return $this->back("Đã thêm {$round->name}.");
    }

    public function update(RoundRequest $request, AdmissionCycle $cycleModel, int $roundId, SaveRound $save): RedirectResponse
    {
        $this->authorize('update', $cycleModel);
        $round = $cycleModel->rounds()->findOrFail($roundId);

        $save->handle($cycleModel, $round, $request->validated());

        return $this->back("Đã cập nhật {$round->name}.");
    }

    public function destroy(AdmissionCycle $cycleModel, int $roundId): RedirectResponse
    {
        $this->authorize('update', $cycleModel);
        $round = $cycleModel->rounds()->findOrFail($roundId);
        $round->delete();

        return $this->back("Đã xoá {$round->name}.");
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
