<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Catalogs\CatalogDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * Create or update one system catalog row (validated input).
 */
class SaveCatalogItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(CatalogDefinition $catalog, ?Model $model, array $data): Model
    {
        $model ??= new ($catalog->model());

        return $catalog->save($model, $data);
    }
}
