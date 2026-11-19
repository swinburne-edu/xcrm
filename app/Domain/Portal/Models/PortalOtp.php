<?php

namespace App\Domain\Portal\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $phone_normalized
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
#[Fillable(['organization_id', 'phone_normalized', 'code_hash', 'attempts', 'expires_at', 'consumed_at'])]
class PortalOtp extends Model
{
    use BelongsToOrganization;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
