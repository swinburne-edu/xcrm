<?php

namespace App\Domain\Platform\Models;

use App\Domain\Organization\Models\Team;
use App\Domain\Platform\Enums\AssignmentStrategy;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use Database\Factories\AssignmentRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * "Who gets a new case": rules are tried in ascending priority; the first active rule whose conditions match and
 * whose strategy finds someone decides (see AssignmentEngine). Empty conditions match every case.
 *
 * conditions (all optional, every present key must match, a list matches when the case has any of its values):
 *   source_ids, campaign_ids, province_ids, high_school_ids, major_ids (primary / interested majors), school_tiers (A|B|C)
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $priority
 * @property array<string, list<int|string>>|null $conditions
 * @property AssignmentStrategy $strategy
 * @property list<int>|null $user_ids
 * @property int|null $team_id
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'name', 'priority', 'conditions', 'strategy', 'user_ids', 'team_id', 'is_active'])]
#[UseFactory(AssignmentRuleFactory::class)]
class AssignmentRule extends Model
{
    /** @use HasFactory<AssignmentRuleFactory> */
    use BelongsToOrganization, HasAuditColumns, HasFactory, SoftDeletes;

    public const CONDITION_KEYS = ['source_ids', 'campaign_ids', 'province_ids', 'high_school_ids', 'major_ids', 'school_tiers'];

    protected $attributes = ['priority' => 100, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'strategy' => AssignmentStrategy::class,
            'user_ids' => 'array',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
