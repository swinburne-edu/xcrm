<?php

namespace App\Domain\Organization\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Models\User;
use Database\Factories\TerritoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A responsibility area: the provinces / high schools one user and/or team looks after.
 * A province or school belongs to at most one territory of an organization.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int|null $user_id
 * @property int|null $team_id
 * @property Carbon|null $deleted_at
 */
#[Fillable(['organization_id', 'name', 'user_id', 'team_id'])]
#[UseFactory(TerritoryFactory::class)]
class Territory extends Model
{
    /** @use HasFactory<TerritoryFactory> */
    use BelongsToOrganization, HasAuditColumns, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<TerritoryAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TerritoryAssignment::class);
    }
}
