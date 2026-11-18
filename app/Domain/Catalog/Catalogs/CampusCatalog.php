<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Major\Models\Campus;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\CampusRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Campuses (per organization). Majors are taught at one.
 */
class CampusCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'campuses';
    }

    public function title(): string
    {
        return 'Cơ sở đào tạo';
    }

    public function description(): ?string
    {
        return 'Cơ sở học của trường. Cơ sở đã có ngành không xoá được.';
    }

    public function model(): string
    {
        return Campus::class;
    }

    public function request(): string
    {
        return CampusRequest::class;
    }

    public function query(): Builder
    {
        return Campus::query()->with('province:id,name');
    }

    public function filters(): array
    {
        return [Filter::search('q', ['code', 'name']), Filter::exact('province_id')];
    }

    public function sorts(): array
    {
        return ['code', 'name'];
    }

    /** @param  Campus  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
            'province_id' => $model->province_id,
            'province_name' => $model->province?->name,
            'address' => $model->address,
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'code', 'sortable' => true],
            ['key' => 'name', 'label' => 'Tên cơ sở', 'sortable' => true],
            ['key' => 'province_name', 'label' => 'Tỉnh/Thành phố'],
            ['key' => 'address', 'label' => 'Địa chỉ'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter('Mã, tên cơ sở…'),
            ['key' => 'province_id', 'label' => 'Tỉnh/Thành phố', 'type' => 'entity', 'entity' => 'provinces'],
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true, 'maxlength' => 20, 'placeholder' => 'VD: HN'],
            ['key' => 'name', 'label' => 'Tên cơ sở', 'type' => 'text', 'required' => true],
            ['key' => 'province_id', 'label' => 'Tỉnh/Thành phố', 'type' => 'entity', 'entity' => 'provinces', 'labelKey' => 'province_name'],
            ['key' => 'address', 'label' => 'Địa chỉ', 'type' => 'text', 'maxlength' => 500],
        ];
    }
}
