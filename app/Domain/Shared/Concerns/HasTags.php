<?php

namespace App\Domain\Shared\Concerns;

use App\Domain\Catalog\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Tags on a model through the `taggables` pivot. The model must have a short name
 * in the morph map (AppServiceProvider::configureMorphMap).
 *
 * @mixin Model
 */
trait HasTags
{
    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
