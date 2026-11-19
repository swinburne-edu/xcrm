<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Platform\Services\MapData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** /map (task 3.6): high schools as markers and a heat circle per province (applicants, leads, enrolled) of the selected cycle. Needs report.view. */
class MapController extends Controller
{
    public function __invoke(Request $request, MapData $map, CurrentCycle $currentCycle): Response
    {
        abort_unless($request->user()->can('report.view'), 403);

        $cycle = $currentCycle->get();
        $schools = $map->schools($cycle);

        return Inertia::render('Map/Index', [
            'cycle' => $cycle?->only(['id', 'name']),
            'provinces' => $map->provinces($cycle),
            'schools' => $schools['schools'],
            'without_coordinates' => $schools['without_coordinates'],
        ]);
    }
}
