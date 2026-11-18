<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Organization\Actions\DeleteTerritory;
use App\Domain\Organization\Actions\SaveTerritory;
use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Services\TerritoryTargets;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TerritoryRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/territories: which user / team looks after which provinces and high schools.
 */
class TerritoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Territory::class);

        $territories = ListQuery::for(Territory::query()->with(['user:id,name', 'team:id,name', 'assignments']), $request)
            ->filters(
                Filter::search('q', ['name']),
                Filter::exact('user_id'),
                Filter::exact('team_id'),
                Filter::callback(
                    'province_id',
                    fn (Builder $q, array $ids) => $q->whereHas('assignments', fn (Builder $a) => $a->where('assignable_type', 'province')->whereIn('assignable_id', $ids)),
                    fn (mixed $v) => array_values(array_filter(array_map('intval', is_array($v) ? $v : explode(',', (string) $v)))) ?: null,
                ),
            )
            ->sorts(['name', 'created_at'])
            ->defaultSort('name')
            ->toProps();

        $territories['data'] = $this->rows($territories['data']);

        return Inertia::render('Territories/Index', [
            'territories' => $territories,
            'targets' => TerritoryTargets::available(),
            'can' => ['manage' => $request->user()->can('create', Territory::class)],
            'breadcrumbs' => [['title' => 'Cài đặt'], ['title' => 'Vùng phụ trách']],
        ]);
    }

    public function store(TerritoryRequest $request, SaveTerritory $save): RedirectResponse
    {
        $territory = $save->handle(null, $request->validated());

        return $this->back("Đã thêm vùng {$territory->name}.");
    }

    public function update(TerritoryRequest $request, int $id, SaveTerritory $save): RedirectResponse
    {
        $territory = Territory::query()->findOrFail($id);
        $this->authorize('update', $territory);

        $save->handle($territory, $request->validated());

        return $this->back("Đã cập nhật vùng {$territory->name}.");
    }

    public function destroy(int $id, DeleteTerritory $delete): RedirectResponse
    {
        $territory = Territory::query()->findOrFail($id);
        $this->authorize('delete', $territory);

        $delete->handle($territory);

        return $this->back("Đã xoá vùng {$territory->name}.");
    }

    /**
     * Page rows with the labels of the provinces / schools of each territory.
     *
     * @param  list<Territory>  $territories
     * @return list<array<string, mixed>>
     */
    private function rows(array $territories): array
    {
        $byType = [];

        foreach ($territories as $territory) {
            foreach ($territory->assignments as $a) {
                $byType[$a->assignable_type][] = $a->assignable_id;
            }
        }

        $labels = [];

        foreach ($byType as $type => $ids) {
            $labels[$type] = TerritoryTargets::labels($type, array_unique($ids));
        }

        return array_map(function (Territory $t) use ($labels): array {
            $targets = [];

            foreach ($t->assignments as $a) {
                $targets[$a->assignable_type][] = ['id' => $a->assignable_id, 'label' => $labels[$a->assignable_type][$a->assignable_id] ?? "#{$a->assignable_id}"];
            }

            return [
                'id' => $t->id,
                'name' => $t->name,
                'user_id' => $t->user_id,
                'user_name' => $t->user?->name,
                'team_id' => $t->team_id,
                'team_name' => $t->team?->name,
                'targets' => (object) $targets,
                'provinces_count' => count($targets['province'] ?? []),
                'high_schools_count' => count($targets['high_school'] ?? []),
                'scope_label' => collect($targets['province'] ?? [])->pluck('label')->take(3)->implode(', ')
                    .(count($targets['province'] ?? []) > 3 ? ' +'.(count($targets['province']) - 3) : ''),
            ];
        }, $territories);
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/settings/territories');
    }
}
