<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Major\Models\Faculty;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\FacultyRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Faculties of the institution (per organization). Majors belong to one.
 */
class FacultyCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'faculties';
    }

    public function title(): string
    {
        return 'Khoa';
    }

    public function description(): ?string
    {
        return 'Khoa/viện quản lý ngành. Khoa đã có ngành không xoá được.';
    }

    public function model(): string
    {
        return Faculty::class;
    }

    public function request(): string
    {
        return FacultyRequest::class;
    }

    public function filters(): array
    {
        return [Filter::search('q', ['code', 'name'])];
    }

    public function sorts(): array
    {
        return ['code', 'name'];
    }

    /** @param  Faculty  $model */
    public function row(Model $model): array
    {
        return ['id' => $model->id, 'code' => $model->code, 'name' => $model->name];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'code', 'sortable' => true],
            ['key' => 'name', 'label' => 'Tên khoa', 'sortable' => true],
        ];
    }

    public function filterDefinitions(): array
    {
        return [self::searchFilter('Mã, tên khoa…')];
    }

    public function fields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true, 'maxlength' => 20, 'placeholder' => 'VD: CNTT'],
            ['key' => 'name', 'label' => 'Tên khoa', 'type' => 'text', 'required' => true],
        ];
    }
}
