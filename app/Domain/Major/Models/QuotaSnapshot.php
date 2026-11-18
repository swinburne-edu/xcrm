<?php

namespace App\Domain\Major\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quota progress of one major on one day, written by the hourly aggregation job (the last run of the day wins).
 * It gives the trend ("+N in 7 days") and lets the job notice when a major changes warning level.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $cycle_id
 * @property int $major_id
 * @property string $snapshot_date Y-m-d (kept a plain string so it matches the date column on every database)
 * @property int $quota_total
 * @property int $target
 * @property int $interested
 * @property int $submitted
 * @property int $eligible
 * @property int $admitted
 * @property int $confirmed
 * @property int $enrolled
 * @property int $secured admitted + enrolled, counted once per case
 * @property string $level ok | short | over
 */
#[Fillable(['organization_id', 'cycle_id', 'major_id', 'snapshot_date', 'quota_total', 'target', 'interested', 'submitted', 'eligible', 'admitted', 'confirmed', 'enrolled', 'secured', 'level'])]
class QuotaSnapshot extends Model
{
    use BelongsToOrganization;

    /** @return BelongsTo<Major, $this> */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }
}
