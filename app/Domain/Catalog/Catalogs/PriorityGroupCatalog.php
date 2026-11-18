<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Models\PriorityGroup;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\PriorityGroupRequest;
use Illuminate\Database\Eloquent\Model;

class PriorityGroupCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'priority-groups';
    }

    public function title(): string
    {
        return 'Đối tượng ưu tiên';
    }

    public function description(): ?string
    {
        return 'Nhóm đối tượng ưu tiên chính sách và điểm cộng tương ứng (thang 30).';
    }

    public function model(): string
    {
        return PriorityGroup::class;
    }

    public function request(): string
    {
        return PriorityGroupRequest::class;
    }

    public function filters(): array
    {
        return [Filter::search('q', ['code', 'name'])];
    }

    public function sorts(): array
    {
        return ['code', 'name', 'bonus_score'];
    }

    /** @param  PriorityGroup  $model */
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
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true, 'maxlength' => 10, 'placeholder' => 'VD: UT1'],
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true],
            ['key' => 'bonus_score', 'label' => 'Điểm ưu tiên', 'type' => 'number', 'required' => true, 'step' => 0.01, 'default' => 0],
        ];
    }
}
