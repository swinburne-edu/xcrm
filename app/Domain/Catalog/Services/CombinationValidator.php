<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Subject;

/**
 * Rule R2 (Thông tư 06/2026): an admission combination has exactly 3 distinct subjects,
 * includes Toán (TOAN) or Ngữ văn (VAN), and that core subject weighs at least 1/3 of the total.
 */
class CombinationValidator
{
    public const SUBJECT_COUNT = 3;

    /**
     * @param  list<array{code: string, weight: float|int|string}>  $subjects
     * @return list<string> Vietnamese error messages; empty when valid
     */
    public function errors(array $subjects): array
    {
        if (count($subjects) !== self::SUBJECT_COUNT) {
            return ['Tổ hợp phải có đúng 3 môn.'];
        }

        $codes = array_map(fn (array $s) => strtoupper($s['code']), $subjects);

        if (count(array_unique($codes)) !== self::SUBJECT_COUNT) {
            return ['Ba môn trong tổ hợp phải khác nhau.'];
        }

        $weights = array_map(fn (array $s) => (float) $s['weight'], $subjects);

        if (min($weights) <= 0) {
            return ['Trọng số mỗi môn phải lớn hơn 0.'];
        }

        $core = array_filter(
            array_combine($codes, $weights),
            fn (string $code) => in_array($code, [Subject::MATH, Subject::LITERATURE], true),
            ARRAY_FILTER_USE_KEY,
        );

        if ($core === []) {
            return ['Tổ hợp bắt buộc có môn Toán hoặc Ngữ văn.'];
        }

        // weight ≥ total/3  ⇔  3 × weight ≥ total (avoids rounding on 1/3).
        $total = array_sum($weights);
        $heaviestCore = max($core);

        if (3 * $heaviestCore + 1e-9 < $total) {
            return ['Trọng số môn Toán/Ngữ văn phải chiếm ít nhất 1/3 tổng trọng số.'];
        }

        return [];
    }

    /**
     * @param  list<array{code: string, weight: float|int|string}>  $subjects
     */
    public function passes(array $subjects): bool
    {
        return $this->errors($subjects) === [];
    }
}
