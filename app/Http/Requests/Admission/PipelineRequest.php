<?php

namespace App\Http\Requests\Admission;

use App\Domain\Admission\Models\Pipeline;
use App\Domain\Cycle\Enums\CycleLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($this->route('id') === null) {
            return (bool) $user?->can('create', Pipeline::class);
        }

        $pipeline = Pipeline::query()->find($this->route('id'));

        return $pipeline !== null && (bool) $user?->can('update', $pipeline);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::enum(CycleLevel::class)],
            'is_default' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'tên pipeline', 'level' => 'bậc'];
    }
}
