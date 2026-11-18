<?php

namespace App\Http\Controllers\Cycle;

use App\Domain\Cycle\Actions\SaveCycleMethod;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cycle\CycleMethodRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Admission methods of a cycle (R1: at most 5 non-direct). Every change needs cycle.update on a
 * cycle that is not closed (CyclePolicy, rule R8).
 */
class CycleMethodController extends Controller
{
    public function store(CycleMethodRequest $request, AdmissionCycle $cycleModel, SaveCycleMethod $save): RedirectResponse
    {
        $this->authorize('update', $cycleModel);

        $method = $save->handle($cycleModel, null, $request->validated());

        return $this->back('Đã thêm phương thức '.($method->display_name ?: $method->method->name).'.');
    }

    public function update(CycleMethodRequest $request, AdmissionCycle $cycleModel, int $methodId, SaveCycleMethod $save): RedirectResponse
    {
        $this->authorize('update', $cycleModel);
        $method = $cycleModel->methods()->findOrFail($methodId);

        $save->handle($cycleModel, $method, $request->validated());

        return $this->back('Đã cập nhật phương thức.');
    }

    public function destroy(AdmissionCycle $cycleModel, int $methodId): RedirectResponse
    {
        $this->authorize('update', $cycleModel);
        $method = $cycleModel->methods()->findOrFail($methodId);
        $method->delete();

        return $this->back('Đã gỡ phương thức khỏi mùa.');
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
