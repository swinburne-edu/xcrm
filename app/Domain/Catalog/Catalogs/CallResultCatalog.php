<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Models\CallResult;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\CallResultRequest;
use Illuminate\Database\Eloquent\Model;

class CallResultCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'call-results';
    }

    public function title(): string
    {
        return 'Kết quả cuộc gọi';
    }

    public function description(): ?string
    {
        return 'Kết quả được "liên hệ thành công" sẽ ghi nhận thời điểm liên hệ đầu tiên của hồ sơ.';
    }

    public function model(): string
    {
        return CallResult::class;
    }

    public function request(): string
    {
        return CallResultRequest::class;
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['name']),
            Filter::boolean('is_contacted'),
        ];
    }

    public function sorts(): array
    {
        return ['sort_order', 'name'];
    }

    public function defaultSort(): string
    {
        return 'sort_order';
    }

    /** @param  CallResult  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'is_contacted' => $model->is_contacted,
            'sort_order' => $model->sort_order,
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'sort_order', 'label' => 'Thứ tự', 'type' => 'number', 'sortable' => true],
            ['key' => 'name', 'label' => 'Kết quả', 'sortable' => true],
            ['key' => 'is_contacted', 'label' => 'Liên hệ thành công', 'type' => 'boolean', 'trueLabel' => 'Có', 'falseLabel' => 'Không'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter('Tên kết quả…'),
            ['key' => 'is_contacted', 'label' => 'Liên hệ thành công', 'type' => 'boolean', 'trueLabel' => 'Có', 'falseLabel' => 'Không'],
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Kết quả', 'type' => 'text', 'required' => true, 'placeholder' => 'VD: Hẹn gọi lại'],
            ['key' => 'is_contacted', 'label' => 'Liên hệ thành công (đã nói chuyện được với thí sinh)', 'type' => 'boolean', 'default' => false],
            ['key' => 'sort_order', 'label' => 'Thứ tự hiển thị', 'type' => 'number', 'default' => 0],
        ];
    }
}
