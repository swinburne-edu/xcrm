<?php

namespace App\Domain\HighSchool\Models;

use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Ward;
use App\Domain\HighSchool\Enums\PartnershipStatus;
use App\Domain\HighSchool\Enums\SchoolTier;
use App\Domain\HighSchool\Enums\SchoolType;
use App\Domain\Organization\Services\TerritoryScope;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Domain\Shared\Concerns\HasCustomData;
use App\Domain\Shared\Concerns\HasDataScope;
use App\Models\User;
use Database\Factories\HighSchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A high school the institution recruits from. Visibility follows the data scope: own (owner_id),
 * or territory (province / school assigned to the user's territories). Schools have no team column.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $code
 * @property string $name
 * @property SchoolType|null $type
 * @property int|null $province_id
 * @property int|null $ward_id
 * @property string|null $address
 * @property int|null $priority_area_id
 * @property int|null $grade12_students
 * @property int|null $grade12_classes
 * @property list<string>|null $strong_combinations
 * @property SchoolTier|null $tier
 * @property PartnershipStatus $partnership_status
 * @property Carbon|null $mou_signed_at
 * @property int|null $owner_id
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $note
 */
#[Fillable(['organization_id', 'code', 'name', 'type', 'province_id', 'ward_id', 'address', 'priority_area_id', 'lat', 'lng', 'grade12_students', 'grade12_classes', 'strong_combinations', 'tier', 'partnership_status', 'mou_signed_at', 'owner_id', 'phone', 'email', 'website', 'note'])]
#[UseFactory(HighSchoolFactory::class)]
class HighSchool extends Model
{
    /** @use HasFactory<HighSchoolFactory> */
    use BelongsToOrganization, HasAuditColumns, HasCustomData, HasDataScope, HasFactory, SoftDeletes;

    protected $attributes = ['partnership_status' => 'not_contacted'];

    protected function casts(): array
    {
        return [
            'type' => SchoolType::class,
            'tier' => SchoolTier::class,
            'partnership_status' => PartnershipStatus::class,
            'mou_signed_at' => 'date',
            'strong_combinations' => 'array',
            'grade12_students' => 'integer',
            'grade12_classes' => 'integer',
        ];
    }

    public function dataScopeTeamColumn(): ?string
    {
        return null;
    }

    /** Territory rule: schools assigned to the user's territories, or in an assigned province. */
    public function applyDataScopeTerritory(Builder $query, User $user): void
    {
        TerritoryScope::apply($query, $this, $user, provinceColumn: 'province_id', highSchoolColumn: 'id');
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * @return BelongsTo<PriorityArea, $this>
     */
    public function priorityArea(): BelongsTo
    {
        return $this->belongsTo(PriorityArea::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<SchoolContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SchoolContact::class)->orderByDesc('is_primary')->orderBy('id');
    }
}
