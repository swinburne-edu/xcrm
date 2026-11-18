<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Pipeline;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a pipeline that is not the default of its level and has never held a case.
 */
class DeletePipeline
{
    public function handle(Pipeline $pipeline): void
    {
        if ($pipeline->is_default) {
            throw ValidationException::withMessages(['pipeline' => 'Không xoá được pipeline mặc định. Hãy đặt pipeline khác làm mặc định trước.']);
        }

        if (AdmissionCase::query()->withoutGlobalScopes()->withTrashed()->where('pipeline_id', $pipeline->id)->exists()) {
            throw ValidationException::withMessages(['pipeline' => 'Pipeline đã có hồ sơ nên không xoá được.']);
        }

        $pipeline->delete();
    }
}
