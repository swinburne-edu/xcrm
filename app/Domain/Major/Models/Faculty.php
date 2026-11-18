<?php

namespace App\Domain\Major\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use Database\Factories\FacultyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A faculty / school of the institution; groups majors.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $name
 */
#[Fillable(['organization_id', 'code', 'name'])]
#[UseFactory(FacultyFactory::class)]
class Faculty extends Model
{
    /** @use HasFactory<FacultyFactory> */
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    /**
     * @return HasMany<Major, $this>
     */
    public function majors(): HasMany
    {
        return $this->hasMany(Major::class);
    }
}
