<?php

namespace App\Domain\Organization\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One province / high school inside a territory. `assignable_type` is the morph alias
 * ("province", "high_school").
 *
 * @property int $id
 * @property int $territory_id
 * @property string $assignable_type
 * @property int $assignable_id
 */
#[Fillable(['territory_id', 'assignable_type', 'assignable_id'])]
class TerritoryAssignment extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Territory, $this>
     */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }
}
