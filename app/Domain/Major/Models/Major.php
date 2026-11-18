<?php

namespace App\Domain\Major\Models;

use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Enums\MajorLevel;
use App\Domain\Major\Enums\ProgramType;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Domain\Shared\Concerns\HasCustomData;
use Database\Factories\MajorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A major (ngành). Its admission setup is per cycle: quota (major_quotas), subject combinations
 * (major_combinations) and methods (major_cycle_methods).
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $moet_code
 * @property string $admission_code
 * @property string $name
 * @property int|null $faculty_id
 * @property int|null $campus_id
 * @property ProgramType $program_type
 * @property MajorLevel $level
 * @property string|null $duration_years
 * @property string|null $description
 * @property string|null $career_info
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'moet_code', 'admission_code', 'name', 'faculty_id', 'campus_id', 'program_type', 'level', 'duration_years', 'description', 'career_info', 'faq', 'is_active'])]
#[UseFactory(MajorFactory::class)]
class Major extends Model
{
    /** @use HasFactory<MajorFactory> */
    use BelongsToOrganization, HasAuditColumns, HasCustomData, HasFactory, SoftDeletes;

    protected $attributes = ['program_type' => 'standard', 'level' => 'university', 'is_active' => true];

    protected function casts(): array
    {
        return [
            'program_type' => ProgramType::class,
            'level' => MajorLevel::class,
            'faq' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Faculty, $this>
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * @return BelongsTo<Campus, $this>
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * @return HasMany<MajorQuota, $this>
     */
    public function quotas(): HasMany
    {
        return $this->hasMany(MajorQuota::class);
    }

    /**
     * Combinations across all cycles; the pivot carries cycle_id.
     *
     * @return BelongsToMany<SubjectCombination, $this>
     */
    public function combinations(): BelongsToMany
    {
        return $this->belongsToMany(SubjectCombination::class, 'major_combinations', 'major_id', 'combination_id')
            ->withPivot('cycle_id');
    }

    /**
     * Methods across all cycles (each cycle method belongs to one cycle).
     *
     * @return BelongsToMany<CycleMethod, $this>
     */
    public function cycleMethods(): BelongsToMany
    {
        return $this->belongsToMany(CycleMethod::class, 'major_cycle_methods', 'major_id', 'cycle_method_id');
    }

    /** True once the major is configured for any cycle (then it is deactivated, not deleted). */
    public function hasCycleConfiguration(): bool
    {
        return $this->quotas()->exists() || $this->combinations()->exists() || $this->cycleMethods()->exists();
    }
}
