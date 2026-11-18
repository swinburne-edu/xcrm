<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use Database\Factories\LostReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Why an admission case was lost (required when moving a case to a "lost" stage, rule R6).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['organization_id', 'name', 'sort_order', 'is_active'])]
#[UseFactory(LostReasonFactory::class)]
class LostReason extends Model
{
    /** @use HasFactory<LostReasonFactory> */
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
