<?php

namespace App\Domain\Major\Models;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use Database\Factories\MajorQuotaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quota of a major in a cycle. Only a total and a direct-admission part exist (CLAUDE.md §6);
 * quota_direct ≤ quota_total (R11).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $major_id
 * @property int $cycle_id
 * @property int $quota_total
 * @property int $quota_direct
 * @property int|null $target_enrolled
 * @property string|null $tuition_fee
 * @property string|null $min_threshold
 * @property array<int, array<string, mixed>>|null $previous_cutoffs
 * @property string|null $scholarship_info
 */
#[Fillable(['organization_id', 'major_id', 'cycle_id', 'quota_total', 'quota_direct', 'target_enrolled', 'tuition_fee', 'min_threshold', 'previous_cutoffs', 'scholarship_info'])]
#[UseFactory(MajorQuotaFactory::class)]
class MajorQuota extends Model
{
    /** @use HasFactory<MajorQuotaFactory> */
    use BelongsToOrganization, HasFactory;

    protected $attributes = ['quota_direct' => 0];

    protected function casts(): array
    {
        return [
            'quota_total' => 'integer',
            'quota_direct' => 'integer',
            'target_enrolled' => 'integer',
            'previous_cutoffs' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Major, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    /**
     * @return BelongsTo<AdmissionCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'cycle_id');
    }
}
