<?php

namespace App\Domain\Telephony\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One webhook endpoint of the telephone provider: /api/webhooks/calls/{uuid}. The secret signs every request
 * (X-Signature: sha256=HMAC of the body); it is stored encrypted and never sent back to the browser.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $uuid
 * @property string $name
 * @property string $secret
 * @property bool $is_active
 * @property Carbon|null $last_received_at
 */
#[Fillable(['organization_id', 'name', 'secret', 'is_active'])]
class CallIntegration extends Model
{
    use BelongsToOrganization, HasAuditColumns, SoftDeletes;

    protected $attributes = ['is_active' => true];

    protected $hidden = ['secret'];

    protected static function booted(): void
    {
        static::creating(function (self $integration): void {
            $integration->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'is_active' => 'boolean', 'last_received_at' => 'datetime'];
    }

    public function webhookUrl(): string
    {
        return url("/api/webhooks/calls/{$this->uuid}");
    }
}
