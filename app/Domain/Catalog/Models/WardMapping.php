<?php

namespace App\Domain\Catalog\Models;

use Database\Factories\WardMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Old address (before 07/2025: province / district / ward) → new ward.
 *
 * @property int $id
 * @property string|null $old_province_name
 * @property string|null $old_district_name
 * @property string|null $old_ward_name
 * @property int|null $new_ward_id
 */
#[Fillable(['old_province_name', 'old_district_name', 'old_ward_name', 'new_ward_id'])]
#[UseFactory(WardMappingFactory::class)]
class WardMapping extends Model
{
    /** @use HasFactory<WardMappingFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function newWard(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'new_ward_id');
    }
}
