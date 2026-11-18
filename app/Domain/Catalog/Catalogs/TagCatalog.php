<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Enums\TagColor;
use App\Domain\Catalog\Models\Tag;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\TagRequest;
use Illuminate\Database\Eloquent\Model;

class TagCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'tags';
    }

    public function title(): string
    {
        return 'Thẻ (tag)';
    }

    public function description(): ?string
    {
        return 'Nhãn gắn tự do cho thí sinh, hồ sơ, trường THPT. Xoá một thẻ sẽ gỡ nó khỏi mọi bản ghi.';
    }

    public function model(): string
    {
        return Tag::class;
    }

    public function request(): string
    {
        return TagRequest::class;
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['name']),
            Filter::exact('color'),
        ];
    }

    public function sorts(): array
    {
        return ['name'];
    }

    public function defaultSort(): string
    {
        return 'name';
    }

    /** @param  Tag  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'color' => $model->color?->value,
            'color_label' => $model->color?->label(),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Thẻ', 'type' => 'tag', 'toneKey' => 'color', 'sortable' => true],
            ['key' => 'color_label', 'label' => 'Màu'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter('Tên thẻ…'),
            ['key' => 'color', 'label' => 'Màu', 'type' => 'select', 'options' => self::enumOptions(TagColor::class)],
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Tên thẻ', 'type' => 'text', 'required' => true, 'maxlength' => 100, 'placeholder' => 'VD: Học sinh giỏi'],
            ['key' => 'color', 'label' => 'Màu', 'type' => 'select', 'options' => self::enumOptions(TagColor::class), 'default' => TagColor::Blue->value],
        ];
    }
}
