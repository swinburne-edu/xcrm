<?php

namespace App\Domain\Catalog\Catalogs;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tabs of /settings/catalogs, in display order.
 */
class CatalogRegistry
{
    /** @var list<class-string<CatalogDefinition>> */
    private const CATALOGS = [
        ProvinceCatalog::class,
        WardCatalog::class,
        WardMappingCatalog::class,
        PriorityAreaCatalog::class,
        PriorityGroupCatalog::class,
        SubjectCatalog::class,
        SubjectCombinationCatalog::class,
        AdmissionMethodCatalog::class,
    ];

    /** @return list<CatalogDefinition> */
    public function all(): array
    {
        return array_map(fn (string $class) => app($class), self::CATALOGS);
    }

    public function default(): CatalogDefinition
    {
        return $this->all()[0];
    }

    public function find(string $key): CatalogDefinition
    {
        foreach ($this->all() as $catalog) {
            if ($catalog->key() === $key) {
                return $catalog;
            }
        }

        throw new NotFoundHttpException("Unknown catalog [{$key}].");
    }

    /** @return list<class-string<Model>> */
    public function models(): array
    {
        return array_map(fn (CatalogDefinition $c) => $c->model(), $this->all());
    }

    /** @return list<array{key: string, title: string}> */
    public function tabs(): array
    {
        return array_map(fn (CatalogDefinition $c) => ['key' => $c->key(), 'title' => $c->title()], $this->all());
    }
}
