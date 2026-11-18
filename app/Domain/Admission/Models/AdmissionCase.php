<?php

namespace App\Domain\Admission\Models;

use App\Domain\Admission\Enums\CaseStatus;
use App\Domain\Admission\Enums\Temperature;
use App\Domain\Admission\Observers\AdmissionCaseObserver;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\LostReason;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Services\TerritoryScope;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Domain\Shared\Concerns\HasCustomData;
use App\Domain\Shared\Concerns\HasDataScope;
use App\Domain\Shared\Concerns\HasTags;
use App\Models\User;
use Database\Factories\AdmissionCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The admission case of one applicant in one cycle (rule R5): the object that moves through the pipeline.
 * `status` always mirrors the stage type (rule R7, AdmissionCaseObserver). A case without an owner sits in
 * the shared pool of its team.
 *
 * Data scope: own = owner; team = owner's team; the pool is visible to the teams it belongs to;
 * territory = cases of applicants in the user's provinces / high schools.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $uuid
 * @property int $applicant_id
 * @property int $cycle_id
 * @property int $pipeline_id
 * @property int $stage_id
 * @property CaseStatus $status
 * @property int|null $owner_id
 * @property int|null $team_id
 * @property int|null $source_id
 * @property int|null $campaign_id
 * @property int|null $primary_major_id
 * @property Temperature|null $temperature
 * @property Carbon|null $assigned_at
 * @property Carbon|null $first_contacted_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $next_action_at
 * @property Carbon|null $sla_alerted_at when the team leader was last told this case is past its stage SLA (task 2.4)
 * @property int|null $lost_reason_id
 * @property string|null $lost_note
 * @property int|null $enrolled_major_id
 * @property Carbon|null $enrolled_at
 */
#[Fillable(['organization_id', 'applicant_id', 'cycle_id', 'pipeline_id', 'stage_id', 'status', 'owner_id', 'team_id', 'source_id', 'campaign_id', 'school_activity_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'primary_major_id', 'temperature', 'score', 'our_preference_rank', 'assigned_at', 'first_contacted_at', 'last_activity_at', 'next_action_at', 'lost_reason_id', 'lost_note', 'enrolled_major_id', 'enrolled_at'])]
#[UseFactory(AdmissionCaseFactory::class)]
#[ObservedBy(AdmissionCaseObserver::class)]
class AdmissionCase extends Model
{
    /** @use HasFactory<AdmissionCaseFactory> */
    use BelongsToOrganization, HasAuditColumns, HasCustomData, HasDataScope, HasFactory, HasTags, SoftDeletes;

    protected $attributes = ['status' => 'open', 'score' => 0];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'temperature' => Temperature::class,
            'score' => 'integer',
            'assigned_at' => 'datetime',
            'first_contacted_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'next_action_at' => 'datetime',
            'sla_alerted_at' => 'datetime',
            'enrolled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $case): void {
            $case->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function dataScopeIncludesPool(): bool
    {
        return true;
    }

    public function applyDataScopeTerritory(Builder $query, User $user): void
    {
        $applicants = Applicant::query()->withoutGlobalScopes()->where('organization_id', $user->organization_id)
            ->where(fn (Builder $q) => TerritoryScope::apply($q, new Applicant, $user, provinceColumn: 'province_id', highSchoolColumn: 'high_school_id'))
            ->select('id');

        $query->whereIn($this->qualifyColumn('applicant_id'), $applicants);
    }

    /** True while the case has no owner (shared pool). */
    public function inPool(): bool
    {
        return $this->owner_id === null;
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<AdmissionCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'cycle_id');
    }

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<LeadSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<Major, $this> */
    public function primaryMajor(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'primary_major_id');
    }

    /** @return BelongsTo<LostReason, $this> */
    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LostReason::class);
    }

    /** @return HasMany<CaseStageHistory, $this> */
    public function stageHistories(): HasMany
    {
        return $this->hasMany(CaseStageHistory::class, 'case_id')->orderBy('changed_at')->orderBy('id');
    }

    /** @return HasMany<CaseTouch, $this> */
    public function caseTouches(): HasMany
    {
        return $this->hasMany(CaseTouch::class, 'case_id')->orderBy('touched_at');
    }

    /** @return BelongsToMany<Major, $this> */
    public function interestedMajors(): BelongsToMany
    {
        return $this->belongsToMany(Major::class, 'case_interested_majors', 'case_id', 'major_id')->withPivot('priority');
    }
}
