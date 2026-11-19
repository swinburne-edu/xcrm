<?php

namespace App\Http\Requests\Settings;

use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Enums\CustomFieldType;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /settings/custom-fields and PUT /settings/custom-fields/{id} (needs custom_field.manage).
 * On create: object, key (snake_case, unique per object) and type. On update only label, options and the flags
 * change (object, key and type are ignored). Select types need at least one option.
 */
class CustomFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('custom_field.manage');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->route('id') === null;
        $rules = [
            'label' => ['required', 'string', 'max:255'],
            'options' => ['nullable', 'array', 'max:100'],
            'options.*' => ['nullable', 'string', 'max:100'],
            'is_required' => ['boolean'],
            'is_filterable' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ];

        if ($creating) {
            $rules += [
                'object' => ['required', Rule::enum(CustomFieldObject::class)],
                'type' => ['required', Rule::enum(CustomFieldType::class)],
                'key' => ['required', 'regex:/^[a-z][a-z0-9_]{0,63}$/', Rule::unique('custom_field_definitions', 'key')
                    ->where('organization_id', app(CurrentOrganization::class)->id())
                    ->where('object', (string) $this->input('object'))],
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'key.regex' => 'Mã trường chỉ gồm chữ thường không dấu, số và dấu gạch dưới, bắt đầu bằng chữ.',
            'key.unique' => 'Mã trường này đã dùng cho đối tượng đã chọn.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['label' => 'tên hiển thị', 'key' => 'mã trường', 'type' => 'kiểu dữ liệu', 'object' => 'đối tượng', 'options' => 'danh sách lựa chọn'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = $this->route('id') === null ? CustomFieldType::tryFrom((string) $this->input('type')) : null;

            if ($type?->hasOptions() && array_filter(array_map('trim', array_map('strval', (array) $this->input('options', [])))) === []) {
                $validator->errors()->add('options', 'Nhập ít nhất một lựa chọn.');
            }
        }];
    }
}
