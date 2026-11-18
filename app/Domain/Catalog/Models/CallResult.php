<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use Database\Factories\CallResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Outcome of a call. is_contacted = the candidate was actually reached
 * (sets admission_cases.first_contacted_at, flow F4).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property bool $is_contacted
 * @property int $sort_order
 */
#[Fillable(['organization_id', 'name', 'is_contacted', 'sort_order'])]
#[UseFactory(CallResultFactory::class)]
class CallResult extends Model
{
    /** @use HasFactory<CallResultFactory> */
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    protected $attributes = ['is_contacted' => false, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'is_contacted' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
