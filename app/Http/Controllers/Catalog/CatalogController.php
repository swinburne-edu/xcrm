<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\DeleteCatalogItem;
use App\Domain\Catalog\Actions\SaveCatalogItem;
use App\Domain\Catalog\Catalogs\CatalogDefinition;
use App\Domain\Catalog\Catalogs\CatalogRegistry;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/catalogs/{catalog}: one tab per system catalog (CatalogRegistry).
 */
class CatalogController extends Controller
{
    public function __construct(private readonly CatalogRegistry $registry) {}

    public function index(Request $request, ?string $catalog = null): Response
    {
        $definition = $catalog === null ? $this->registry->default() : $this->registry->find($catalog);

        $this->authorize('viewAny', $definition->model());

        return Inertia::render('Catalog/Index', [
            'tabs' => $this->registry->tabs(),
            'catalog' => $definition->schema(),
            'items' => ListQuery::for($definition->query(), $request)
                ->filters(...$definition->filters())
                ->sorts($definition->sorts())
                ->defaultSort($definition->defaultSort())
                ->toProps(fn (Model $model) => $definition->row($model)),
            'can' => ['manage' => $request->user()->can('create', $definition->model())],
            'breadcrumbs' => [
                ['title' => 'Cài đặt'],
                ['title' => 'Danh mục', 'href' => route('settings.catalogs.index', absolute: false)],
                ['title' => $definition->title(), 'href' => route('settings.catalogs.show', $definition->key(), false)],
            ],
        ]);
    }

    public function store(string $catalog, SaveCatalogItem $save): RedirectResponse
    {
        $definition = $this->registry->find($catalog);
        $this->authorize('create', $definition->model());

        $model = $save->handle($definition, null, $this->validated($definition));

        return $this->back($definition, 'success', 'Đã thêm '.$definition->label($model).'.');
    }

    public function update(string $catalog, int $id, SaveCatalogItem $save): RedirectResponse
    {
        $definition = $this->registry->find($catalog);
        $model = $definition->query()->findOrFail($id);
        $this->authorize('update', $model);

        $save->handle($definition, $model, $this->validated($definition));

        return $this->back($definition, 'success', 'Đã cập nhật '.$definition->label($model).'.');
    }

    public function destroy(string $catalog, int $id, DeleteCatalogItem $delete): RedirectResponse
    {
        $definition = $this->registry->find($catalog);
        $model = $definition->query()->findOrFail($id);
        $this->authorize('delete', $model);

        $label = $definition->label($model);

        return $delete->handle($model)
            ? $this->back($definition, 'success', "Đã xoá {$label}.")
            : $this->back($definition, 'error', "Không thể xoá {$label} vì đang được sử dụng. Hãy chuyển sang ngừng sử dụng.");
    }

    /**
     * Resolving the catalog's FormRequest runs its validation (redirect back with errors on failure).
     *
     * @return array<string, mixed>
     */
    private function validated(CatalogDefinition $definition): array
    {
        return app($definition->request())->validated();
    }

    private function back(CatalogDefinition $definition, string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back(fallback: route('settings.catalogs.show', $definition->key()));
    }
}
