<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Models\LostReason;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\LostReasonRequest;
use Illuminate\Database\Eloquent\Model;

class LostReasonCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'lost-reasons';
    }

    public function title(): string
    {
        return 'Lý do thất bại';
    }

    public function description(): ?string
    {
        return 'Bắt buộc chọn một lý do khi chuyển hồ sơ sang giai đoạn thất bại.';
    }

    public function model(): string
    {
        return LostReason::class;
    }

    public function request(): string
    {
        return LostReasonRequest::class;
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['name']),
            Filter::boolean('is_active'),
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

    /** @param  LostReason  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'name' => $model->name,
            'sort_order' => $model->sort_order,
            'is_active' => $model->is_active,
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'sort_order', 'label' => 'Thứ tự', 'type' => 'number', 'sortable' => true],
            ['key' => 'name', 'label' => 'Lý do', 'sortable' => true],
            ['key' => 'is_active', 'label' => 'Trạng thái', 'type' => 'boolean'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [self::searchFilter('Tên lý do…'), self::activeFilter()];
    }

    public function fields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Lý do', 'type' => 'text', 'required' => true, 'placeholder' => 'VD: Học phí cao'],
            ['key' => 'sort_order', 'label' => 'Thứ tự hiển thị', 'type' => 'number', 'default' => 0],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'boolean', 'default' => true],
        ];
    }
}
