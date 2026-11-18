<?php

namespace App\Domain\Major\Models;

use App\Domain\Catalog\Models\Province;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use Database\Factories\CampusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A campus where majors are taught.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $name
 * @property int|null $province_id
 * @property string|null $address
 */
#[Fillable(['organization_id', 'code', 'name', 'province_id', 'address'])]
#[UseFactory(CampusFactory::class)]
class Campus extends Model
{
    /** @use HasFactory<CampusFactory> */
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    protected $table = 'campuses';

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
