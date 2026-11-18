<?php

namespace App\Http\Requests\Major;

use App\Domain\Major\Enums\MajorLevel;
use App\Domain\Major\Enums\ProgramType;
use App\Domain\Major\Models\Major;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MajorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($this->route('id') === null) {
            return (bool) $user?->can('create', Major::class);
        }

        $major = Major::query()->find($this->route('id'));

        return $major !== null && (bool) $user?->can('update', $major);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('admission_code'))) {
            $this->merge(['admission_code' => Str::upper(trim($this->input('admission_code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $inOrganization = fn (string $table) => Rule::exists($table, 'id')->where('organization_id', $organizationId);

        return [
            'moet_code' => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'admission_code' => ['required', 'string', 'max:20', Rule::unique('majors', 'admission_code')
                ->where('organization_id', $organizationId)->ignore($this->route('id'))],
            'name' => ['required', 'string', 'max:255'],
            'faculty_id' => ['nullable', 'integer', $inOrganization('faculties')],
            'campus_id' => ['nullable', 'integer', $inOrganization('campuses')],
            'program_type' => ['nullable', Rule::enum(ProgramType::class)],
            'level' => ['nullable', Rule::enum(MajorLevel::class)],
            'duration_years' => ['nullable', 'numeric', 'between:0.5,10'],
            'description' => ['nullable', 'string', 'max:10000'],
            'career_info' => ['nullable', 'string', 'max:10000'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'moet_code.regex' => 'Mã ngành của Bộ gồm đúng 7 chữ số.',
            'admission_code.unique' => 'Mã xét tuyển này đã được dùng cho ngành khác.',
        ];
    }

    public function attributes(): array
    {
        return [
            'moet_code' => 'mã ngành Bộ', 'admission_code' => 'mã xét tuyển', 'name' => 'tên ngành',
            'faculty_id' => 'khoa', 'campus_id' => 'cơ sở', 'program_type' => 'loại chương trình',
            'level' => 'bậc', 'duration_years' => 'thời gian đào tạo',
        ];
    }
}
