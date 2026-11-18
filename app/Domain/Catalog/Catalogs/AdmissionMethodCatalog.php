<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Enums\ScoreSource;
use App\Domain\Catalog\Models\AdmissionMethod;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\AdmissionMethodRequest;
use Illuminate\Database\Eloquent\Model;

class AdmissionMethodCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'admission-methods';
    }

    public function title(): string
    {
        return 'Phương thức xét tuyển';
    }

    public function description(): ?string
    {
        return 'Mã phương thức theo danh mục của Bộ. Phương thức áp dụng cho từng mùa cấu hình ở Mùa tuyển sinh.';
    }

    public function model(): string
    {
        return AdmissionMethod::class;
    }

    public function request(): string
    {
        return AdmissionMethodRequest::class;
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['code', 'name']),
            Filter::exact('score_source'),
        ];
    }

    public function sorts(): array
    {
        return ['code', 'name'];
    }

    /** @param  AdmissionMethod  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
            'score_source' => $model->score_source?->value,
            'score_source_label' => $model->score_source?->label(),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã Bộ', 'type' => 'code', 'sortable' => true],
            ['key' => 'name', 'label' => 'Tên', 'sortable' => true],
            ['key' => 'score_source_label', 'label' => 'Nguồn điểm', 'type' => 'badge'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter(),
            ['key' => 'score_source', 'label' => 'Nguồn điểm', 'type' => 'select', 'multiple' => true, 'options' => self::enumOptions(ScoreSource::class)],
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Mã Bộ', 'type' => 'text', 'required' => true, 'maxlength' => 10, 'placeholder' => 'VD: 100'],
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true],
            ['key' => 'score_source', 'label' => 'Nguồn điểm', 'type' => 'select', 'options' => self::enumOptions(ScoreSource::class)],
        ];
    }
}
