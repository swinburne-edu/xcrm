<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /cases/{uuid}/applications and PUT /applications/{id}: shape of a nguyện vọng.
 * The business rules R3 (≤ 15, unique order) and R4 (combination / method belong to the major and the cycle)
 * are checked by SaveApplication, which also knows the case. Authorization is done in the controller (case / application).
 */
class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'major_id' => ['required', 'integer'],
            'cycle_method_id' => ['required', 'integer'],
            'combination_id' => ['nullable', 'integer'],
            'round_id' => ['required', 'integer'],
            'preference_order' => ['nullable', 'integer', 'between:1,15'],
            'encourage_bonus' => ['nullable', 'numeric', 'between:0,30'],
        ];
    }

    public function attributes(): array
    {
        return [
            'major_id' => 'ngành',
            'cycle_method_id' => 'phương thức',
            'combination_id' => 'tổ hợp',
            'round_id' => 'đợt xét tuyển',
            'preference_order' => 'thứ tự nguyện vọng',
            'encourage_bonus' => 'điểm khuyến khích',
        ];
    }
}
