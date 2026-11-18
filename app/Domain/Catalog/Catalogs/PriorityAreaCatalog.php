<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\PriorityAreaRequest;
use Illuminate\Database\Eloquent\Model;

class PriorityAreaCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'priority-areas';
    }

    public function title(): string
    {
        return 'Khu vực ưu tiên';
    }

    public function description(): ?string
    {
        return 'Điểm ưu tiên khu vực (thang 30). Giá trị tham khảo, sửa theo quy chế năm hiện hành.';
    }

    public function model(): string
    {
        return PriorityArea::class;
    }

    public function request(): string
    {
        return PriorityAreaRequest::class;
    }

    public function filters(): array
    {
        return [Filter::search('q', ['code', 'name'])];
    }

    public function sorts(): array
    {
        return ['code', 'name', 'bonus_score'];
    }

    public function defaultSort(): string
    {
        return '-bonus_score';
    }

    /** @param  PriorityArea  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
            'bonus_score' => $model->bonus_score,
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'code', 'sortable' => true],
            ['key' => 'name', 'label' => 'Tên', 'sortable' => true],
            ['key' => 'bonus_score', 'label' => 'Điểm ưu tiên', 'type' => 'number', 'sortable' => true],
        ];
    }

    public function filterDefinitions(): array
    {
        return [self::searchFilter()];
    }

    public function fields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true, 'maxlength' => 10, 'placeholder' => 'VD: KV2-NT'],
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true],
            ['key' => 'bonus_score', 'label' => 'Điểm ưu tiên', 'type' => 'number', 'required' => true, 'step' => 0.01, 'default' => 0],
        ];
    }
}
