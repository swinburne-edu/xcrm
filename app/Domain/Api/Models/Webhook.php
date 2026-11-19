<?php

namespace App\Domain\Api\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An endpoint that receives events (WebhookEvent) as signed JSON POSTs. The secret is stored encrypted, shown once
 * when it is created or renewed, and signs every body: X-Xcrm-Signature: sha256=HMAC-SHA256(body, secret).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $url
 * @property list<string> $events
 * @property string $secret
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'name', 'url', 'events', 'secret', 'is_active'])]
class Webhook extends Model
{
    use BelongsToOrganization, HasAuditColumns, SoftDeletes;

    protected $attributes = ['is_active' => true];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['events' => 'array', 'secret' => 'encrypted', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest('id');
    }
}
