<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\WardType;
use Database\Factories\WardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commune / ward / special zone (no district level since 07/2025).
 *
 * @property int $id
 * @property int $province_id
 * @property string $code
 * @property string $name
 * @property WardType|null $type
 * @property bool $is_active
 */
#[Fillable(['province_id', 'code', 'name', 'type', 'is_active'])]
#[UseFactory(WardFactory::class)]
class Ward extends Model
{
    /** @use HasFactory<WardFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'type' => WardType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
