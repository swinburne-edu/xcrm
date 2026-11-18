<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Models\WardMapping;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\WardMappingRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WardMappingCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'ward-mappings';
    }

    public function title(): string
    {
        return 'Ánh xạ địa giới';
    }

    public function description(): ?string
    {
        return 'Địa chỉ cũ (tỉnh/huyện/xã trước 07/2025) tương ứng với xã/phường mới, dùng khi nhập dữ liệu cũ.';
    }

    public function model(): string
    {
        return WardMapping::class;
    }

    public function request(): string
    {
        return WardMappingRequest::class;
    }

    public function query(): Builder
    {
        return WardMapping::query()->with('newWard.province:id,name');
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['old_province_name', 'old_district_name', 'old_ward_name']),
            Filter::exact('new_ward_id'),
        ];
    }

    public function sorts(): array
    {
        return ['old_province_name', 'old_district_name', 'old_ward_name'];
    }

    public function defaultSort(): string
    {
        return 'old_province_name,old_district_name,old_ward_name';
    }

    /** @param  WardMapping  $model */
    public function row(Model $model): array
    {
        $ward = $model->newWard;

        return [
            'id' => $model->id,
            'old_province_name' => $model->old_province_name,
            'old_district_name' => $model->old_district_name,
            'old_ward_name' => $model->old_ward_name,
            'new_ward_id' => $model->new_ward_id,
            'new_ward_name' => $ward ? $ward->name.' ('.$ward->province?->name.')' : null,
        ];
    }

    public function label(Model $model): string
    {
        /** @var WardMapping $model */
        return trim($model->old_ward_name.', '.$model->old_district_name, ', ');
    }

    public function columns(): array
    {
        return [
            ['key' => 'old_province_name', 'label' => 'Tỉnh (cũ)', 'sortable' => true],
            ['key' => 'old_district_name', 'label' => 'Quận/huyện (cũ)', 'sortable' => true],
            ['key' => 'old_ward_name', 'label' => 'Xã/phường (cũ)', 'sortable' => true],
            ['key' => 'new_ward_name', 'label' => 'Xã/phường mới'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter('Tên tỉnh, huyện, xã cũ…'),
            ['key' => 'new_ward_id', 'label' => 'Xã/phường mới', 'type' => 'entity', 'entity' => 'wards'],
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'old_province_name', 'label' => 'Tỉnh (cũ)', 'type' => 'text', 'required' => true],
            ['key' => 'old_district_name', 'label' => 'Quận/huyện (cũ)', 'type' => 'text'],
            ['key' => 'old_ward_name', 'label' => 'Xã/phường (cũ)', 'type' => 'text', 'required' => true],
            ['key' => 'new_ward_id', 'label' => 'Xã/phường mới', 'type' => 'entity', 'entity' => 'wards', 'labelKey' => 'new_ward_name', 'required' => true],
        ];
    }
}
