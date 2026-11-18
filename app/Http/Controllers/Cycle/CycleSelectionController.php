<?php

namespace App\Http\Controllers\Cycle;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /cycle/select: the header's cycle selector. The choice is remembered in the session.
 */
class CycleSelectionController extends Controller
{
    public function __invoke(Request $request, CurrentCycle $current, CurrentOrganization $organization): RedirectResponse
    {
        $data = $request->validate([
            'cycle_id' => ['required', 'integer', Rule::exists('admission_cycles', 'id')
                ->where('organization_id', $organization->id())->whereNull('deleted_at')],
        ]);

        $current->select(AdmissionCycle::query()->findOrFail($data['cycle_id']));

        return back(fallback: '/dashboard');
    }
}
