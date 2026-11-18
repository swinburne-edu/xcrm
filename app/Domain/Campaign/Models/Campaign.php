<?php

namespace App\Domain\Campaign\Models;

use App\Domain\Campaign\Enums\CampaignChannel;
use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Enums\CampaignType;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Domain\Shared\Concerns\HasCustomData;
use App\Models\User;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A marketing campaign of a cycle. Its code is the utm_campaign value, so it is fixed once the
 * campaign leaves draft. A campaign of a closed cycle is read-only (R8).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $cycle_id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property CampaignType|null $type
 * @property CampaignChannel|null $channel
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $budget
 * @property string|null $actual_cost
 * @property int|null $target_leads
 * @property int|null $target_applications
 * @property int|null $target_enrolled
 * @property int|null $owner_id
 * @property CampaignStatus $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $landing_url
 * @property string|null $description
 */
#[Fillable(['organization_id', 'cycle_id', 'parent_id', 'code', 'name', 'type', 'channel', 'start_date', 'end_date', 'budget', 'actual_cost', 'target_leads', 'target_applications', 'target_enrolled', 'owner_id', 'status', 'approved_by', 'approved_at', 'landing_url', 'description'])]
#[UseFactory(CampaignFactory::class)]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use BelongsToOrganization, HasAuditColumns, HasCustomData, HasFactory, SoftDeletes;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'type' => CampaignType::class,
            'channel' => CampaignChannel::class,
            'status' => CampaignStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
            'target_leads' => 'integer',
            'target_applications' => 'integer',
            'target_enrolled' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AdmissionCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'cycle_id');
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<CampaignTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(CampaignTarget::class);
    }
}
