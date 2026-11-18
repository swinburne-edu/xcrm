<?php

namespace App\Domain\Admission\Models;

use App\Domain\Admission\Enums\StageType;
use Database\Factories\PipelineStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stage of a pipeline. `required_fields` lists case columns that must be filled to enter the stage
 * (e.g. ["primary_major_id"]); `sla_hours` is the time a case should spend here.
 *
 * @property int $id
 * @property int $pipeline_id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property StageType $type
 * @property int $probability
 * @property int|null $sla_hours
 * @property list<string>|null $required_fields
 */
#[Fillable(['pipeline_id', 'code', 'name', 'color', 'sort_order', 'type', 'probability', 'sla_hours', 'required_fields'])]
#[UseFactory(PipelineStageFactory::class)]
class PipelineStage extends Model
{
    /** @use HasFactory<PipelineStageFactory> */
    use HasFactory;

    /** Case columns a stage may require. */
    public const REQUIRABLE = ['primary_major_id', 'source_id', 'campaign_id', 'temperature', 'owner_id', 'next_action_at'];

    public $timestamps = false;

    protected $attributes = ['type' => 'open', 'probability' => 0, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'type' => StageType::class,
            'sort_order' => 'integer',
            'probability' => 'integer',
            'sla_hours' => 'integer',
            'required_fields' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * @return HasMany<AdmissionCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(AdmissionCase::class, 'stage_id');
    }
}
