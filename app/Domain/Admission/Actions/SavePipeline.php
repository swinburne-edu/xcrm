<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Models\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a pipeline. One pipeline per level is the default (used for new cases): making one the
 * default demotes the others of the same level, and the first pipeline of a level is the default automatically.
 */
class SavePipeline
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Pipeline $pipeline, array $data): Pipeline
    {
        return DB::transaction(function () use ($pipeline, $data): Pipeline {
            $pipeline ??= new Pipeline;
            $level = $data['level'];

            $hasDefault = Pipeline::query()->where('level', $level)->where('is_default', true)->when($pipeline->exists, fn ($q) => $q->whereKeyNot($pipeline->id))->exists();
            $isDefault = (bool) ($data['is_default'] ?? false) || ! $hasDefault;

            // Cannot stop being the default without naming another one.
            if ($pipeline->exists && $pipeline->is_default && ! $isDefault) {
                $isDefault = true;
            }

            if ($isDefault) {
                Pipeline::query()->where('level', $level)->when($pipeline->exists, fn ($q) => $q->whereKeyNot($pipeline->id))->update(['is_default' => false]);
            }

            $pipeline->fill(['name' => $data['name'], 'level' => $level, 'is_default' => $isDefault])->save();

            return $pipeline->refresh();
        });
    }
}
