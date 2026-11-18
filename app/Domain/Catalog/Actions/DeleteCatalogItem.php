<?php

namespace App\Domain\Catalog\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Hard-deletes a catalog row (catalogs have no soft deletes).
 * Rows still referenced (a province with wards, a subject in a combination…)
 * are protected by foreign keys: the delete is refused and the caller suggests deactivating instead.
 */
class DeleteCatalogItem
{
    /**
     * @return bool false when the row is still in use
     */
    public function handle(Model $model): bool
    {
        try {
            $model->delete();
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    private function isForeignKeyViolation(QueryException $e): bool
    {
        // 23000 = integrity constraint violation (MySQL, SQLite).
        return $e->getCode() === '23000' || str_contains($e->getMessage(), 'FOREIGN KEY');
    }
}
