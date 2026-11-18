<?php

namespace App\Http\Requests\Catalog;

use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Base for system catalog forms. Authorization is done by CatalogController (CatalogPolicy).
 */
abstract class CatalogRequest extends FormRequest
{
    /** Codes are stored upper-case (TOAN, KV2-NT, A01…) unless a request opts out. */
    protected bool $uppercaseCode = true;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $code = trim($this->input('code'));
            $this->merge(['code' => $this->uppercaseCode ? Str::upper($code) : $code]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /** id of the record being edited (null on create). */
    protected function recordId(): ?int
    {
        $id = $this->route('id');

        return $id === null ? null : (int) $id;
    }

    protected function uniqueCode(string $table): Unique
    {
        return Rule::unique($table, 'code')->ignore($this->recordId());
    }

    /** Unique among the rows of the current organization (per-organization catalogs). */
    protected function uniqueInOrganization(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)
            ->where('organization_id', app(CurrentOrganization::class)->id())
            ->ignore($this->recordId());
    }

    public function attributes(): array
    {
        return [
            'code' => 'mã',
            'name' => 'tên',
            'type' => 'loại',
            'is_active' => 'trạng thái',
            'bonus_score' => 'điểm ưu tiên',
            'province_id' => 'tỉnh/thành phố',
            'new_ward_id' => 'xã/phường mới',
            'score_source' => 'nguồn điểm',
            'subjects' => 'môn trong tổ hợp',
            'channel' => 'kênh',
            'sort_order' => 'thứ tự',
            'is_contacted' => 'liên hệ thành công',
            'color' => 'màu',
        ];
    }
}
