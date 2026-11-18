<?php

namespace App\Domain\HighSchool\Models;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Enums\SchoolActivityStatus;
use App\Domain\HighSchool\Enums\SchoolActivityType;
use App\Domain\Organization\Services\TerritoryScope;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Domain\Shared\Concerns\HasDataScope;
use App\Models\User;
use Database\Factories\SchoolActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * An activity with a high school (or at the campus when high_school_id is null): counseling, career fair, tour…
 * The staff go in school_activity_user; students who scan the QR (checkin_token) become activity_attendees.
 * Visible to its creator, its staff and whoever looks after the school (territory); "all" sees everything.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $cycle_id
 * @property int|null $high_school_id
 * @property int|null $campaign_id
 * @property SchoolActivityType $type
 * @property string $title
 * @property Carbon|null $start_at
 * @property Carbon|null $end_at
 * @property string|null $location
 * @property SchoolActivityStatus $status
 * @property int|null $expected_attendees
 * @property int|null $actual_attendees
 * @property string|null $cost
 * @property string $checkin_token
 * @property string|null $result_note
 */
#[Fillable(['organization_id', 'cycle_id', 'high_school_id', 'campaign_id', 'type', 'title', 'start_at', 'end_at', 'location', 'status', 'expected_attendees', 'actual_attendees', 'cost', 'checkin_token', 'result_note'])]
#[UseFactory(SchoolActivityFactory::class)]
class SchoolActivity extends Model
{
    /** @use HasFactory<SchoolActivityFactory> */
    use BelongsToOrganization, HasAuditColumns, HasDataScope, HasFactory, SoftDeletes;

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return [
            'type' => SchoolActivityType::class,
            'status' => SchoolActivityStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'expected_attendees' => 'integer',
            'actual_attendees' => 'integer',
            'cost' => 'decimal:0',
        ];
    }

    public function dataScopeOwnerColumn(): string
    {
        return 'created_by';
    }

    public function dataScopeTeamColumn(): ?string
    {
        return null;
    }

    /** Whoever is on the staff of an activity sees it. */
    public function dataScopeExtra(User $user): ?\Closure
    {
        return fn (Builder $query) => $query->whereIn(
            $this->qualifyColumn('id'),
            DB::table('school_activity_user')->select('school_activity_id')->where('user_id', $user->id),
        );
    }

    public function applyDataScopeTerritory(Builder $query, User $user): void
    {
        TerritoryScope::apply($query, $this, $user, highSchoolColumn: 'high_school_id');
    }

    /** @return BelongsTo<HighSchool, $this> */
    public function highSchool(): BelongsTo
    {
        return $this->belongsTo(HighSchool::class);
    }

    /** @return BelongsTo<AdmissionCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'cycle_id');
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_activity_user', 'school_activity_id', 'user_id')->withPivot('role');
    }

    /** @return HasMany<ActivityAttendee, $this> */
    public function attendees(): HasMany
    {
        return $this->hasMany(ActivityAttendee::class, 'school_activity_id');
    }

    /** Check-in link given to students (QR code). */
    public function checkinUrl(): string
    {
        return url("/checkin/{$this->checkin_token}");
    }
}
