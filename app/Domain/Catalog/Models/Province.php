<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProvinceType;
use Database\Factories\ProvinceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Province / centrally-run city, administrative map after 07/2025 (34 units).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property ProvinceType|null $type
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'type', 'is_active'])]
#[UseFactory(ProvinceFactory::class)]
class Province extends Model
{
    /** @use HasFactory<ProvinceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'type' => ProvinceType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Ward, $this>
     */
    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }
}
