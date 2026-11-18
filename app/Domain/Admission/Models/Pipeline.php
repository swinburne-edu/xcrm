<?php

namespace App\Domain\Admission\Models;

use App\Domain\Cycle\Enums\CycleLevel;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use Database\Factories\PipelineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sales-like pipeline of admission stages for one level (university, college…). One pipeline per
 * level is the default and is used for new cases.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property CycleLevel $level
 * @property bool $is_default
 */
#[Fillable(['organization_id', 'name', 'level', 'is_default'])]
#[UseFactory(PipelineFactory::class)]
class Pipeline extends Model
{
    /** @use HasFactory<PipelineFactory> */
    use BelongsToOrganization, HasFactory;

    protected $attributes = ['level' => 'university', 'is_default' => false];

    protected function casts(): array
    {
        return ['level' => CycleLevel::class, 'is_default' => 'boolean'];
    }

    /**
     * @return HasMany<PipelineStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('sort_order')->orderBy('id');
    }
}
