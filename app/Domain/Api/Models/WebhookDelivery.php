<?php

namespace App\Domain\Api\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One event sent to one webhook: the body that is signed, the outcome of the latest attempt and how many were made.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $webhook_id
 * @property string $uuid
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $status pending | delivered | failed
 * @property int|null $response_code
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $delivered_at
 */
#[Fillable(['organization_id', 'webhook_id', 'event', 'payload', 'status', 'response_code', 'attempts', 'error', 'delivered_at'])]
class WebhookDelivery extends Model
{
    use BelongsToOrganization;

    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected static function booted(): void
    {
        static::creating(function (self $delivery): void {
            $delivery->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class)->withTrashed();
    }
}
