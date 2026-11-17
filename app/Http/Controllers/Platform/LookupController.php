<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Lookups\EloquentLookup;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /lookups/{entity}?q=term          → search
 * GET /lookups/{entity}?ids=1,2         → labels of selected ids
 * Other query params are passed to the lookup (e.g. province_id for wards).
 */
class LookupController extends Controller
{
    public function __invoke(Request $request, string $entity): JsonResponse
    {
        $class = config("lookups.{$entity}");

        abort_unless(is_string($class) && is_subclass_of($class, EloquentLookup::class), 404);

        /** @var EloquentLookup $lookup */
        $lookup = app($class);
        $params = $request->except(['q', 'ids']);

        if ($request->filled('ids')) {
            $ids = array_values(array_filter(
                explode(',', (string) $request->query('ids')),
                fn (string $id) => $id !== '',
            ));

            return response()->json(['data' => $lookup->find($ids, $params)]);
        }

        return response()->json(['data' => $lookup->search((string) $request->query('q', ''), $params)]);
    }
}
