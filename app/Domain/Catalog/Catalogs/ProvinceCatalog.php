<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Enums\ProvinceType;
use App\Domain\Catalog\Models\Province;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\ProvinceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProvinceCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'provinces';
    }

    public function title(): string
    {
        return 'Tỉnh/Thành phố';
    }

    public function description(): ?string
    {
        return '34 tỉnh, thành phố theo địa giới mới từ 01/07/2025.';
    }

    public function model(): string
    {
        return Province::class;
    }

    public function request(): string
    {
        return ProvinceRequest::class;
    }

    public function query(): Builder
    {
        return Province::query()->withCount('wards');
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['code', 'name']),
            Filter::exact('type'),
            Filter::boolean('is_active'),
        ];
    }

    public function sorts(): array
    {
        return ['code', 'name'];
    }

    /** @param  Province  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
            'type' => $model->type?->value,
            'type_label' => $model->type?->label(),
            'wards_count' => $model->wards_count ?? null,
            'is_active' => $model->is_active,
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'code', 'sortable' => true],
            ['key' => 'name', 'label' => 'Tên', 'sortable' => true],
            ['key' => 'type_label', 'label' => 'Loại'],
            ['key' => 'wards_count', 'label' => 'Số xã/phường', 'type' => 'number'],
            ['key' => 'is_active', 'label' => 'Trạng thái', 'type' => 'boolean'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter(),
            ['key' => 'type', 'label' => 'Loại', 'type' => 'select', 'options' => self::enumOptions(ProvinceType::class)],
            self::activeFilter(),
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true, 'maxlength' => 10, 'placeholder' => 'VD: 01'],
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true, 'placeholder' => 'VD: Hà Nội'],
            ['key' => 'type', 'label' => 'Loại', 'type' => 'select', 'options' => self::enumOptions(ProvinceType::class)],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'boolean', 'default' => true],
        ];
    }
}
