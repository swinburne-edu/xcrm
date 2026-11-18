<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\CatalogRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One tab of /settings/catalogs: model, list (filters/sorts), table columns, form fields and Form Request.
 * The UI (resources/js/pages/Catalog/Index.vue) is generated from schema().
 *
 * Field types: text | number | select | boolean | entity | combination_subjects
 * Column types: text | code | number | boolean | badge
 */
abstract class CatalogDefinition
{
    abstract public function key(): string;

    abstract public function title(): string;

    public function description(): ?string
    {
        return null;
    }

    /** @return class-string<Model> */
    abstract public function model(): string;

    /** @return class-string<CatalogRequest> */
    abstract public function request(): string;

    /** @return Builder<Model> */
    public function query(): Builder
    {
        return ($this->model())::query();
    }

    /** @return list<Filter> */
    abstract public function filters(): array;

    /** @return list<string> */
    abstract public function sorts(): array;

    public function defaultSort(): string
    {
        return 'code';
    }

    /**
     * Table row + edit form values.
     *
     * @return array<string, mixed>
     */
    abstract public function row(Model $model): array;

    /** @return list<array<string, mixed>> */
    abstract public function columns(): array;

    /** FilterBar definitions (resources/js/types/list.ts). @return list<array<string, mixed>> */
    abstract public function filterDefinitions(): array;

    /** @return list<array<string, mixed>> */
    abstract public function fields(): array;

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function save(Model $model, array $data): Model
    {
        $model->fill($data)->save();

        return $model;
    }

    /** Short label of a record for messages ("01 Hà Nội"). */
    public function label(Model $model): string
    {
        return trim(($model->getAttribute('code') ?? '').' '.($model->getAttribute('name') ?? ''));
    }

    /**
     * @return array{key: string, title: string, description: string|null, columns: list<array<string, mixed>>, filters: list<array<string, mixed>>, fields: list<array<string, mixed>>}
     */
    public function schema(): array
    {
        return [
            'key' => $this->key(),
            'title' => $this->title(),
            'description' => $this->description(),
            'columns' => $this->columns(),
            'filters' => $this->filterDefinitions(),
            'fields' => $this->fields(),
        ];
    }

    /**
     * Options for select fields/filters from a backed enum with label().
     *
     * @param  class-string<\BackedEnum>  $enum
     * @return list<array{value: string, label: string}>
     */
    protected static function enumOptions(string $enum): array
    {
        return array_map(
            fn (\BackedEnum $case) => ['value' => (string) $case->value, 'label' => $case->label()],
            $enum::cases(),
        );
    }

    /** Common "Tìm kiếm" text filter definition. */
    protected static function searchFilter(string $placeholder = 'Mã, tên…'): array
    {
        return ['key' => 'q', 'label' => 'Tìm kiếm', 'type' => 'text', 'placeholder' => $placeholder];
    }

    protected static function activeFilter(): array
    {
        return ['key' => 'is_active', 'label' => 'Trạng thái', 'type' => 'boolean', 'trueLabel' => 'Đang dùng', 'falseLabel' => 'Ngừng dùng'];
    }
}
