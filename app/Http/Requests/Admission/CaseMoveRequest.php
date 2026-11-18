<?php

namespace App\Http\Requests\Admission;

use App\Domain\Admission\Models\PipelineStage;
use Illuminate\Validation\Rule;

/**
 * POST /cases/{uuid}/move: the stage to move to, with the lost reason / note for a "lost" stage (R6).
 * The stage rules themselves live in MoveCaseStage.
 */
class CaseMoveRequest extends CaseRequests
{
    public function authorize(): bool
    {
        $case = $this->routeCase();

        return $case !== null && (bool) $this->user()?->can('update', $case);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer', Rule::exists('pipeline_stages', 'id')],
            'lost_reason_id' => ['nullable', 'integer'],
            'lost_note' => ['nullable', 'string', 'max:5000'],
            'enrolled_major_id' => ['nullable', 'integer'],
        ];
    }

    public function stage(): PipelineStage
    {
        return PipelineStage::query()->findOrFail($this->integer('stage_id'));
    }
}
