<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Enums\StageType;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a stage. A pipeline must always keep at least one open, one won and one lost stage
 * (new cases start in the first open stage; closing a case needs a won / lost one), so a stage cannot be
 * turned into another type if it is the last of its type.
 */
class SaveStage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Pipeline $pipeline, ?PipelineStage $stage, array $data): PipelineStage
    {
        $type = StageType::from($data['type']);

        if ($stage !== null && $stage->type !== $type && self::isLastOfType($pipeline, $stage)) {
            throw ValidationException::withMessages(['type' => "Đây là giai đoạn \"{$stage->type->label()}\" duy nhất của pipeline, không đổi loại được."]);
        }

        $stage ??= new PipelineStage(['pipeline_id' => $pipeline->id]);

        $stage->fill([
            'code' => $data['code'],
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($stage->exists ? $stage->sort_order : ((int) $pipeline->stages()->max('sort_order') + 10)),
            'type' => $type,
            'probability' => $data['probability'] ?? 0,
            'sla_hours' => $data['sla_hours'] ?? null,
            'required_fields' => array_values($data['required_fields'] ?? []) ?: null,
        ])->save();

        return $stage;
    }

    public static function isLastOfType(Pipeline $pipeline, PipelineStage $stage): bool
    {
        return ! $pipeline->stages()->where('type', $stage->type->value)->whereKeyNot($stage->id)->exists();
    }
}
