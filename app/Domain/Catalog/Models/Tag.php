<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\TagColor;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A free label attached to applicants, cases, high schools… through `taggables`
 * (see App\Domain\Shared\Concerns\HasTags).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property TagColor|null $color
 */
#[Fillable(['organization_id', 'name', 'color'])]
#[UseFactory(TagFactory::class)]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['color' => TagColor::class];
    }
}
