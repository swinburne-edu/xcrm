<?php

namespace App\Domain\Admission\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A point of contact that brought (or brought back) the applicant: a form, an activity, a campaign link.
 * The first touch is written when the case is created; later ones when the same person comes in again.
 *
 * @property int $id
 * @property int $case_id
 * @property int|null $source_id
 * @property int|null $campaign_id
 * @property int|null $school_activity_id
 * @property int|null $form_id
 * @property array<string, mixed>|null $utm
 * @property array<string, mixed>|null $payload
 * @property Carbon $touched_at
 */
#[Fillable(['case_id', 'source_id', 'campaign_id', 'school_activity_id', 'form_id', 'utm', 'payload', 'touched_at'])]
class CaseTouch extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['utm' => 'array', 'payload' => 'array', 'touched_at' => 'datetime'];
    }

    /** @return BelongsTo<AdmissionCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(AdmissionCase::class, 'case_id');
    }
}
