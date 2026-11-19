<?php

namespace App\Domain\Intake\Models;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Enums\LeadProvider;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use Database\Factories\LeadIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One webhook endpoint for a lead ads platform: /api/webhooks/leads/{uuid}.
 * secret = Facebook app secret / TikTok signing secret (verifies every request); verify_token answers the Facebook
 * subscription handshake; access_token (a Page token) reads a Facebook lead from the Graph API.
 * The three are stored encrypted and never sent back to the browser. field_map: {platform field name: lead key}.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $uuid
 * @property LeadProvider $provider
 * @property string $name
 * @property int|null $campaign_id
 * @property int|null $source_id
 * @property string|null $secret
 * @property string|null $verify_token
 * @property string|null $access_token
 * @property array<string, string>|null $field_map
 * @property bool $is_active
 * @property Carbon|null $last_received_at
 */
#[Fillable(['organization_id', 'provider', 'name', 'campaign_id', 'source_id', 'secret', 'verify_token', 'access_token', 'field_map', 'is_active'])]
#[UseFactory(LeadIntegrationFactory::class)]
class LeadIntegration extends Model
{
    /** @use HasFactory<LeadIntegrationFactory> */
    use BelongsToOrganization, HasAuditColumns, HasFactory, SoftDeletes;

    protected $attributes = ['is_active' => true];

    protected $hidden = ['secret', 'verify_token', 'access_token'];

    protected static function booted(): void
    {
        static::creating(function (self $integration): void {
            $integration->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'provider' => LeadProvider::class,
            'secret' => 'encrypted',
            'verify_token' => 'encrypted',
            'access_token' => 'encrypted',
            'field_map' => 'array',
            'is_active' => 'boolean',
            'last_received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<LeadSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    /** @return HasMany<LeadWebhookEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LeadWebhookEvent::class, 'integration_id');
    }

    public function webhookUrl(): string
    {
        return url("/api/webhooks/leads/{$this->uuid}");
    }
}
