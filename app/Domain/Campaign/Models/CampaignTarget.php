<?php

namespace App\Domain\Campaign\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * What a campaign aims at: a major, a province or a high school (morph aliases major / province / high_school).
 *
 * @property int $id
 * @property int $campaign_id
 * @property string $targetable_type
 * @property int $targetable_id
 */
#[Fillable(['campaign_id', 'targetable_type', 'targetable_id'])]
class CampaignTarget extends Model
{
    public $timestamps = false;

    /** Morph aliases a campaign can target. */
    public const TYPES = ['major', 'province', 'high_school'];

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
