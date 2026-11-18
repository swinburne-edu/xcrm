<?php

namespace App\Http\Requests\Settings;

use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Services\RoleDefinitions;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Shared\Services\PhoneNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('id') ? 'user.update' : 'user.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? Str::lower(trim($this->input('email'))) : $this->input('email'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'password' => $this->input('password') === '' ? null : $this->input('password'),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && ! app(PhoneNormalizer::class)->isValid($value)) {
                    $fail('Số điện thoại không hợp lệ.');
                }
            }],
            'password' => [$id ? 'nullable' : 'required', Password::defaults(), 'string', 'max:255'],
            'role' => ['required', Rule::in(RoleDefinitions::roleCodes()), function (string $attribute, mixed $value, Closure $fail): void {
                // Only an administrator may grant the administrator role.
                if ($value === RoleDefinitions::ADMIN && ! $this->user()->hasRole(RoleDefinitions::ADMIN)) {
                    $fail('Chỉ quản trị hệ thống mới được cấp vai trò này.');
                }
            }],
            'data_scope' => ['nullable', Rule::enum(DataScopeLevel::class)],
            'is_active' => ['boolean'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', Rule::exists('teams', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'họ tên',
            'email' => 'email',
            'phone' => 'số điện thoại',
            'password' => 'mật khẩu',
            'role' => 'vai trò',
            'data_scope' => 'phạm vi dữ liệu',
            'team_ids' => 'nhóm',
            'team_ids.*' => 'nhóm',
        ];
    }
}
