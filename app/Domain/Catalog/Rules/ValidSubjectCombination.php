<?php

namespace App\Domain\Catalog\Rules;

use App\Domain\Catalog\Models\Subject;
use App\Domain\Catalog\Services\CombinationValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates `subjects: [{subject_id, weight}, …]` of a combination against rule R2.
 */
class ValidSubjectCombination implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('Danh sách môn không hợp lệ.');

            return;
        }

        $ids = array_map(fn ($row) => is_array($row) ? ($row['subject_id'] ?? null) : null, $value);

        // Empty rows are reported by subjects.*.subject_id (required): "Vui lòng chọn đủ 3 môn."
        if (in_array(null, $ids, true) || in_array('', $ids, true)) {
            return;
        }

        $codes = Subject::query()->whereIn('id', $ids)->pluck('code', 'id');

        $subjects = [];

        foreach ($value as $row) {
            $id = is_array($row) ? ($row['subject_id'] ?? null) : null;

            if (! isset($codes[$id])) {
                $fail('Có môn học không tồn tại.');

                return;
            }

            $subjects[] = ['code' => $codes[$id], 'weight' => $row['weight'] ?? 0];
        }

        foreach (app(CombinationValidator::class)->errors($subjects) as $message) {
            $fail($message);
        }
    }
}
