<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Enums\WardType;
use App\Domain\Catalog\Models\Ward;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\WardRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WardCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'wards';
    }

    public function title(): string
    {
        return 'Xã/Phường';
    }

    public function description(): ?string
    {
        return 'Xã, phường, đặc khu trực thuộc tỉnh (không còn cấp huyện).';
    }

    public function model(): string
    {
        return Ward::class;
    }

    public function request(): string
    {
        return WardRequest::class;
    }

    public function query(): Builder
    {
        return Ward::query()->with('province:id,code,name');
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['code', 'name']),
            Filter::exact('province_id'),
            Filter::exact('type'),
            Filter::boolean('is_active'),
        ];
    }

    public function sorts(): array
    {
        return ['code', 'name'];
    }

    /** @param  Ward  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
            'type' => $model->type?->value,
            'type_label' => $model->type?->label(),
            'province_id' => $model->province_id,
            'province_name' => $model->province?->name,
            'is_active' => $model->is_active,
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'code', 'sortable' => true],
            ['key' => 'name', 'label' => 'Tên', 'sortable' => true],
            ['key' => 'type_label', 'label' => 'Loại'],
            ['key' => 'province_name', 'label' => 'Tỉnh/Thành phố'],
            ['key' => 'is_active', 'label' => 'Trạng thái', 'type' => 'boolean'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter(),
            ['key' => 'province_id', 'label' => 'Tỉnh/Thành phố', 'type' => 'entity', 'entity' => 'provinces'],
            ['key' => 'type', 'label' => 'Loại', 'type' => 'select', 'options' => self::enumOptions(WardType::class)],
            self::activeFilter(),
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'province_id', 'label' => 'Tỉnh/Thành phố', 'type' => 'entity', 'entity' => 'provinces', 'labelKey' => 'province_name', 'required' => true],
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true, 'maxlength' => 10],
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true],
            ['key' => 'type', 'label' => 'Loại', 'type' => 'select', 'options' => self::enumOptions(WardType::class)],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'boolean', 'default' => true],
        ];
    }
}
