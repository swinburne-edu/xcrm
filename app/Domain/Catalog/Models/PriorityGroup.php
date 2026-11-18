<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\PriorityGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy priority group UT1 (objects 01–04) / UT2 (05–07) with its bonus.
 *
 * @property int $id
 * @property string $code
 * @property string|null $name
 * @property string $bonus_score decimal(4,2)
 */
#[Fillable(['code', 'name', 'bonus_score'])]
#[UseFactory(PriorityGroupFactory::class)]
class PriorityGroup extends Model
{
    /** @use HasFactory<PriorityGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['bonus_score' => 'decimal:2'];
    }
}
