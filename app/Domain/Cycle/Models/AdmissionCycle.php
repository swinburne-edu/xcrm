<?php

namespace App\Domain\Cycle\Models;

use App\Domain\Cycle\Enums\CycleLevel;
use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use Database\Factories\AdmissionCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An admission season ("Tuyển sinh 2027"). All admission configuration hangs off a cycle (CLAUDE.md §6).
 * A closed cycle is read-only (R8); one cycle per level is "current" (R10).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $year
 * @property CycleLevel $level
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property CycleStatus $status
 * @property int|null $target_enrolled
 * @property string|null $regulation_ref
 * @property bool $is_current
 */
#[Fillable(['organization_id', 'name', 'year', 'level', 'start_date', 'end_date', 'status', 'target_enrolled', 'regulation_ref', 'is_current'])]
#[UseFactory(AdmissionCycleFactory::class)]
class AdmissionCycle extends Model
{
    /** @use HasFactory<AdmissionCycleFactory> */
    use BelongsToOrganization, HasAuditColumns, HasFactory, SoftDeletes;

    /** Max methods per cycle that are not direct admission (rule R1, Thông tư 06/2026). */
    public const MAX_METHODS = 5;

    protected $attributes = ['status' => 'preparing', 'level' => 'university', 'is_current' => false];

    protected function casts(): array
    {
        return [
            'level' => CycleLevel::class,
            'status' => CycleStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'year' => 'integer',
            'target_enrolled' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === CycleStatus::Closed;
    }

    /**
     * @return HasMany<AdmissionRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(AdmissionRound::class, 'cycle_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<CycleMethod, $this>
     */
    public function methods(): HasMany
    {
        return $this->hasMany(CycleMethod::class, 'cycle_id')->orderBy('id');
    }
}
