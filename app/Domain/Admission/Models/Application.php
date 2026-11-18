<?php

namespace App\Domain\Admission\Models;

use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One nguyện vọng of an admission case: major + method + combination in a round, ranked 1–15 (R3, R4).
 * Scores come from ScoreCalculator and are stored with their breakdown.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $case_id
 * @property int $major_id
 * @property int $cycle_method_id
 * @property int|null $combination_id
 * @property int $round_id
 * @property int $preference_order
 * @property string|null $raw_score
 * @property string|null $priority_bonus
 * @property string|null $encourage_bonus
 * @property string|null $final_score
 * @property array<string, mixed>|null $score_breakdown
 * @property ApplicationStatus $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decided_at
 * @property Carbon|null $confirmed_at
 */
#[Fillable(['organization_id', 'case_id', 'major_id', 'cycle_method_id', 'combination_id', 'round_id', 'preference_order', 'raw_score', 'priority_bonus', 'encourage_bonus', 'final_score', 'score_breakdown', 'status', 'submitted_at', 'decided_at', 'confirmed_at', 'moet_synced_at'])]
#[UseFactory(ApplicationFactory::class)]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use BelongsToOrganization, HasAuditColumns, HasFactory;

    /** Rule R3. */
    public const MAX_PER_CASE = 15;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'score_breakdown' => 'array',
            'preference_order' => 'integer',
            'raw_score' => 'decimal:2',
            'priority_bonus' => 'decimal:2',
            'encourage_bonus' => 'decimal:2',
            'final_score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'moet_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AdmissionCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(AdmissionCase::class, 'case_id');
    }

    /** @return BelongsTo<Major, $this> */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    /** @return BelongsTo<CycleMethod, $this> */
    public function cycleMethod(): BelongsTo
    {
        return $this->belongsTo(CycleMethod::class, 'cycle_method_id');
    }

    /** @return BelongsTo<SubjectCombination, $this> */
    public function combination(): BelongsTo
    {
        return $this->belongsTo(SubjectCombination::class, 'combination_id');
    }

    /** @return BelongsTo<AdmissionRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(AdmissionRound::class, 'round_id');
    }
}
