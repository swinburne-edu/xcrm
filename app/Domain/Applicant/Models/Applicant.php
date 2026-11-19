<?php

namespace App\Domain\Applicant\Models;

use App\Domain\Applicant\Enums\Gender;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\PriorityGroup;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Ward;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Services\TerritoryScope;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Domain\Shared\Concerns\HasCustomData;
use App\Domain\Shared\Concerns\HasDataScope;
use App\Domain\Shared\Services\PhoneNormalizer;
use App\Models\User;
use Database\Factories\ApplicantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A candidate. Route key is the uuid (never a guessable id). Saving keeps the derived columns in step:
 * phone_normalized (84xxxxxxxxx), lower-case email, citizen_id_hash (sha256 of the digits, for duplicate checks;
 * citizen_id itself is stored encrypted) and the per-organization code (TS000123).
 *
 * Data scope: own = referred by me or created by me; territory = my provinces / high schools.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $uuid
 * @property string $code
 * @property string $full_name
 * @property Gender|null $gender
 * @property Carbon|null $dob
 * @property string|null $phone
 * @property string|null $phone_normalized
 * @property string|null $email
 * @property string|null $citizen_id
 * @property string|null $citizen_id_hash
 * @property int|null $province_id
 * @property int|null $ward_id
 * @property int|null $high_school_id
 * @property int|null $current_grade
 * @property int|null $graduation_year
 * @property int|null $merged_into_id
 */
#[Fillable(['organization_id', 'full_name', 'gender', 'dob', 'phone', 'email', 'citizen_id', 'zalo_id', 'facebook_id', 'province_id', 'ward_id', 'address', 'high_school_id', 'class_name', 'current_grade', 'graduation_year', 'priority_area_id', 'priority_group_id', 'interests', 'first_source_id', 'first_campaign_id', 'referred_by_user_id', 'consent_marketing', 'consent_at'])]
#[UseFactory(ApplicantFactory::class)]
class Applicant extends Model
{
    /** @use HasFactory<ApplicantFactory> */
    use BelongsToOrganization, HasAuditColumns, HasCustomData, HasDataScope, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'dob' => 'date',
            'citizen_id' => 'encrypted',
            'interests' => 'array',
            'consent_marketing' => 'boolean',
            'consent_at' => 'datetime',
            'current_grade' => 'integer',
            'graduation_year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $applicant): void {
            $applicant->uuid ??= (string) Str::uuid();
            $applicant->code ??= self::nextCode((int) $applicant->organization_id);
        });

        static::saving(function (self $applicant): void {
            $applicant->phone_normalized = app(PhoneNormalizer::class)->normalize($applicant->phone);
            $applicant->email = $applicant->email === null || $applicant->email === '' ? null : Str::lower(trim($applicant->email));
            $applicant->citizen_id_hash = self::hashCitizenId($applicant->citizen_id);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Next code of an organization: TS000001, TS000002… (deleted applicants keep their numbers). */
    public static function nextCode(int $organizationId): string
    {
        $last = self::withoutGlobalScopes()->withTrashed()->where('organization_id', $organizationId)->orderByDesc('id')->value('code');
        $number = $last === null ? 0 : (int) substr($last, 2);

        return 'TS'.str_pad((string) ($number + 1), 6, '0', STR_PAD_LEFT);
    }

    /** sha256 of the citizen id digits; null when empty. */
    public static function hashCitizenId(?string $citizenId): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $citizenId);

        return $digits === '' ? null : hash('sha256', $digits);
    }

    public function dataScopeOwnerColumn(): string
    {
        return 'referred_by_user_id';
    }

    public function dataScopeAlsoOwnedBy(): array
    {
        return ['created_by'];
    }

    /** Whoever owns a case of this applicant sees the applicant too. */
    public function dataScopeExtra(User $user): ?\Closure
    {
        return fn (Builder $query) => $query->whereIn(
            $this->qualifyColumn('id'),
            DB::table('admission_cases')->select('applicant_id')->where('owner_id', $user->id)->whereNull('deleted_at'),
        );
    }

    public function dataScopeTeamColumn(): ?string
    {
        return null;
    }

    public function applyDataScopeTerritory(Builder $query, User $user): void
    {
        TerritoryScope::apply($query, $this, $user, provinceColumn: 'province_id', highSchoolColumn: 'high_school_id');
    }

    /** @return BelongsTo<Province, $this> */
    /**
     * The case of the newest admission cycle (what the list shows: stage, owner, last activity).
     *
     * @return HasOne<AdmissionCase, $this>
     */
    public function latestCase(): HasOne
    {
        return $this->hasOne(AdmissionCase::class)->ofMany('cycle_id', 'max');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return BelongsTo<Ward, $this> */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /** @return BelongsTo<HighSchool, $this> */
    public function highSchool(): BelongsTo
    {
        return $this->belongsTo(HighSchool::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<PriorityArea, $this> */
    public function priorityArea(): BelongsTo
    {
        return $this->belongsTo(PriorityArea::class);
    }

    /** @return BelongsTo<PriorityGroup, $this> */
    public function priorityGroup(): BelongsTo
    {
        return $this->belongsTo(PriorityGroup::class);
    }

    /** @return BelongsTo<LeadSource, $this> */
    public function firstSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'first_source_id');
    }

    /** @return BelongsTo<Campaign, $this> */
    public function firstCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'first_campaign_id');
    }

    /** @return BelongsTo<User, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    /** @return BelongsToMany<Guardian, $this> */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'applicant_guardian')
            ->withPivot(['relationship', 'is_decision_maker', 'is_primary_contact']);
    }

    /** @return HasMany<ApplicantScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(ApplicantScore::class);
    }

    /** @return HasMany<ApplicantCertificate, $this> */
    public function certificates(): HasMany
    {
        return $this->hasMany(ApplicantCertificate::class)->orderBy('type');
    }
}
