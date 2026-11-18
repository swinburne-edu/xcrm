<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Models\Subject;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\SubjectRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SubjectCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'subjects';
    }

    public function title(): string
    {
        return 'Môn học';
    }

    public function description(): ?string
    {
        return 'Môn dùng trong tổ hợp xét tuyển và bảng điểm. Môn cốt lõi: Toán, Ngữ văn.';
    }

    public function model(): string
    {
        return Subject::class;
    }

    public function request(): string
    {
        return SubjectRequest::class;
    }

    public function query(): Builder
    {
        return Subject::query()->withCount('combinations');
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['code', 'name']),
            Filter::boolean('is_core'),
        ];
    }

    public function sorts(): array
    {
        return ['code', 'name', 'is_core'];
    }

    public function defaultSort(): string
    {
        return '-is_core,code';
    }

    /** @param  Subject  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
            'is_core' => $model->is_core,
            'combinations_count' => $model->combinations_count ?? null,
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'code', 'sortable' => true],
            ['key' => 'name', 'label' => 'Tên', 'sortable' => true],
            ['key' => 'is_core', 'label' => 'Môn cốt lõi', 'type' => 'boolean', 'trueLabel' => 'Cốt lõi', 'falseLabel' => '—'],
            ['key' => 'combinations_count', 'label' => 'Số tổ hợp', 'type' => 'number'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter(),
            ['key' => 'is_core', 'label' => 'Môn cốt lõi', 'type' => 'boolean', 'trueLabel' => 'Cốt lõi', 'falseLabel' => 'Không'],
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text', 'required' => true, 'maxlength' => 20, 'placeholder' => 'VD: TOAN', 'help' => 'Chữ in hoa không dấu.'],
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true],
            ['key' => 'is_core', 'label' => 'Môn cốt lõi (Toán, Ngữ văn)', 'type' => 'boolean', 'default' => false],
        ];
    }
}
