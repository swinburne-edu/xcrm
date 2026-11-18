<?php

namespace App\Domain\Catalog\Catalogs;

use App\Domain\Catalog\Enums\LeadChannel;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Shared\Support\Listing\Filter;
use App\Http\Requests\Catalog\LeadSourceRequest;
use Illuminate\Database\Eloquent\Model;

class LeadSourceCatalog extends CatalogDefinition
{
    public function key(): string
    {
        return 'lead-sources';
    }

    public function title(): string
    {
        return 'Nguồn thí sinh';
    }

    public function description(): ?string
    {
        return 'Thí sinh biết đến trường qua đâu. Mã nguồn là khoá cố định, đừng đổi mã của nguồn đã dùng.';
    }

    public function model(): string
    {
        return LeadSource::class;
    }

    public function request(): string
    {
        return LeadSourceRequest::class;
    }

    public function filters(): array
    {
        return [
            Filter::search('q', ['code', 'name']),
            Filter::exact('channel'),
            Filter::boolean('is_active'),
        ];
    }

    public function sorts(): array
    {
        return ['code', 'name', 'channel'];
    }

    public function defaultSort(): string
    {
        return 'name';
    }

    /** @param  LeadSource  $model */
    public function row(Model $model): array
    {
        return [
            'id' => $model->id,
            'code' => $model->code,
            'name' => $model->name,
            'channel' => $model->channel?->value,
            'channel_label' => $model->channel?->label(),
            'is_active' => $model->is_active,
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Tên', 'sortable' => true],
            ['key' => 'code', 'label' => 'Mã', 'type' => 'code', 'sortable' => true],
            ['key' => 'channel_label', 'label' => 'Kênh', 'type' => 'badge'],
            ['key' => 'is_active', 'label' => 'Trạng thái', 'type' => 'boolean'],
        ];
    }

    public function filterDefinitions(): array
    {
        return [
            self::searchFilter(),
            ['key' => 'channel', 'label' => 'Kênh', 'type' => 'select', 'options' => self::enumOptions(LeadChannel::class)],
            self::activeFilter(),
        ];
    }

    public function fields(): array
    {
        return [
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true, 'placeholder' => 'VD: Facebook Ads'],
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text', 'maxlength' => 50, 'placeholder' => 'VD: facebook_ads', 'help' => 'Chữ thường không dấu. Để trống sẽ tự tạo từ tên.'],
            ['key' => 'channel', 'label' => 'Kênh', 'type' => 'select', 'options' => self::enumOptions(LeadChannel::class)],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'boolean', 'default' => true],
        ];
    }
}
