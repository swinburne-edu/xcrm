<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Territory;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a territory and frees its provinces / schools for another territory.
 */
class DeleteTerritory
{
    public function handle(Territory $territory): void
    {
        DB::transaction(function () use ($territory): void {
            $territory->assignments()->delete();
            $territory->delete();
        });
    }
}
