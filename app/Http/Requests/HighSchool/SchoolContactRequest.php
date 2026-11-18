<?php

namespace App\Http\Requests\HighSchool;

use App\Domain\HighSchool\Enums\ContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contact of a high school. Authorization (school_contact.* and the school being in the user's
 * data scope) is done by SchoolContactController.
 */
class SchoolContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(ContactRole::class)],
            'homeroom_class' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'zalo' => ['nullable', 'string', 'max:50'],
            'relationship_level' => ['nullable', 'integer', 'between:1,5'],
            'is_primary' => ['boolean'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return ['full_name' => 'họ tên', 'role' => 'chức vụ', 'homeroom_class' => 'lớp chủ nhiệm', 'relationship_level' => 'mức độ quan hệ'];
    }
}
