<?php

namespace App\Domain\Cycle\Actions;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\CycleMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update the admission method of a cycle (formula, conditions, documents).
 * Rule R1: at most 5 methods that are not direct admission per cycle. Checked here, inside
 * the transaction, as the last line of defence (the request validates it too for a friendly message).
 */
class SaveCycleMethod
{
    /**
     * @param  array<string, mixed>  $data  validated by CycleMethodRequest
     */
    public function handle(AdmissionCycle $cycle, ?CycleMethod $method, array $data): CycleMethod
    {
        return DB::transaction(function () use ($cycle, $method, $data): CycleMethod {
            // Serialize concurrent saves of the same cycle so the count below is reliable.
            AdmissionCycle::query()->whereKey($cycle->id)->lockForUpdate()->first();

            $isDirect = (bool) ($data['is_direct'] ?? false);

            if (! $isDirect && self::wouldExceedLimit($cycle, $method)) {
                throw ValidationException::withMessages([
                    'is_direct' => 'Mỗi mùa chỉ có tối đa '.AdmissionCycle::MAX_METHODS.' phương thức (không tính xét tuyển thẳng).',
                ]);
            }

            $method ??= new CycleMethod(['cycle_id' => $cycle->id]);

            $method->fill([
                'method_id' => $data['method_id'],
                'display_name' => $data['display_name'] ?? null,
                'is_direct' => $isDirect,
                'score_formula' => $data['score_formula'] ?? null,
                'conditions' => $data['conditions'] ?? null,
                'required_documents' => array_values($data['required_documents'] ?? []) ?: null,
            ])->save();

            return $method;
        });
    }

    /** Would saving this method as a non-direct one make more than the limit? */
    public static function wouldExceedLimit(AdmissionCycle $cycle, ?CycleMethod $editing): bool
    {
        $others = CycleMethod::query()
            ->where('cycle_id', $cycle->id)
            ->where('is_direct', false)
            ->when($editing?->exists, fn ($q) => $q->whereKeyNot($editing->id))
            ->count();

        return $others >= AdmissionCycle::MAX_METHODS;
    }
}
