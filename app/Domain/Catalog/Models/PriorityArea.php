<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\PriorityAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Priority area KV1 / KV2-NT / KV2 / KV3 with its bonus (30-point scale).
 *
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property string $bonus_score decimal(4,2)
 */
#[Fillable(['code', 'name', 'bonus_score'])]
#[UseFactory(PriorityAreaFactory::class)]
class PriorityArea extends Model
{
    /** @use HasFactory<PriorityAreaFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['bonus_score' => 'decimal:2'];
    }
}
