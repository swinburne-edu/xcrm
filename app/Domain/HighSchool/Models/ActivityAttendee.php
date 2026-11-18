<?php

namespace App\Domain\HighSchool\Models;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student who checked in to a school activity (unique per activity + applicant; a second scan only refreshes form_data).
 *
 * @property int $id
 * @property int $school_activity_id
 * @property int $applicant_id
 * @property Carbon|null $checked_in_at
 * @property array<string, mixed>|null $form_data
 */
#[Fillable(['organization_id', 'school_activity_id', 'applicant_id', 'checked_in_at', 'form_data'])]
class ActivityAttendee extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'form_data' => 'array'];
    }

    /** @return BelongsTo<SchoolActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(SchoolActivity::class, 'school_activity_id');
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
