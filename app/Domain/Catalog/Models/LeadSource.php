<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\LeadChannel;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use Database\Factories\LeadSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a candidate came from (Facebook Ads, school counselling…).
 * `code` is a stable key used by code (e.g. "school_counseling" for check-in, "import").
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $name
 * @property LeadChannel|null $channel
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'code', 'name', 'channel', 'is_active'])]
#[UseFactory(LeadSourceFactory::class)]
class LeadSource extends Model
{
    /** @use HasFactory<LeadSourceFactory> */
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'channel' => LeadChannel::class,
            'is_active' => 'boolean',
        ];
    }
}
