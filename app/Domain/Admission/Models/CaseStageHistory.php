<?php

namespace App\Domain\Admission\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One move of a case between stages. from_stage_id is null for the first entry.
 *
 * @property int $id
 * @property int $case_id
 * @property int|null $from_stage_id
 * @property int $to_stage_id
 * @property int|null $user_id
 * @property string|null $hours_in_previous
 * @property Carbon $changed_at
 */
#[Fillable(['case_id', 'from_stage_id', 'to_stage_id', 'user_id', 'hours_in_previous', 'changed_at'])]
class CaseStageHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    /** @return BelongsTo<AdmissionCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(AdmissionCase::class, 'case_id');
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'from_stage_id');
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'to_stage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
