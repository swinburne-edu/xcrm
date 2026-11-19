<?php

namespace App\Domain\Campaign\Models;

use App\Domain\Shared\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One day of spending on a campaign. No organization column: it is reached through its campaign.
 *
 * @property int $id
 * @property int $campaign_id
 * @property Carbon $date
 * @property string $amount
 * @property string|null $note
 */
#[Fillable(['campaign_id', 'date', 'amount', 'note'])]
class CampaignCost extends Model
{
    use HasAuditColumns;

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
