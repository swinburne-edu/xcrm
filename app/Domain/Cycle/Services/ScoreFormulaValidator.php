<?php

namespace App\Domain\Cycle\Services;

/**
 * Validates `cycle_methods.score_formula` (docs/04-database.md §6). The formula is data, never code:
 * unknown keys are rejected so typos do not silently change scoring.
 *
 *   source                thpt_exam | transcript | competency_exam | direct | talent
 *   use_combination       bool   score the 3 subjects of the subject combination
 *   terms                 list of terms used for transcript scores ("10", "11", "12", "10-1"…)
 *   aggregate             avg | sum
 *   subject_scale         number > 0 (10 = subjects scored out of 10)
 *   scale_to              number > 0 (30 = total on a 30-point scale)
 *   certificate_conversion {subject: code, table: {CERT: {"5.0": 8.0, …}}}
 *   priority              {apply: bool, taper_from: number}
 *   encourage_bonus_max   number ≥ 0
 *   min_total             number ≥ 0
 */
class ScoreFormulaValidator
{
    public const SOURCES = ['thpt_exam', 'transcript', 'competency_exam', 'direct', 'talent'];

    public const AGGREGATES = ['avg', 'sum'];

    private const KEYS = [
        'source', 'use_combination', 'terms', 'aggregate', 'subject_scale', 'scale_to',
        'certificate_conversion', 'priority', 'encourage_bonus_max', 'min_total',
    ];

    /**
     * @return list<string> Vietnamese error messages; empty when valid
     */
    public function errors(mixed $formula): array
    {
        if ($formula === null) {
            return [];
        }

        if (! is_array($formula) || ($formula !== [] && array_is_list($formula))) {
            return ['Công thức điểm phải là một đối tượng JSON.'];
        }

        $errors = [];

        foreach (array_diff(array_keys($formula), self::KEYS) as $key) {
            $errors[] = "Công thức có trường không hợp lệ: {$key}.";
        }

        if (! isset($formula['source'])) {
            $errors[] = 'Công thức thiếu nguồn điểm (source).';
        } elseif (! in_array($formula['source'], self::SOURCES, true)) {
            $errors[] = 'Nguồn điểm (source) phải là một trong: '.implode(', ', self::SOURCES).'.';
        }

        if (isset($formula['use_combination']) && ! is_bool($formula['use_combination'])) {
            $errors[] = 'use_combination phải là true hoặc false.';
        }

        if (isset($formula['terms'])) {
            $terms = $formula['terms'];

            if (! is_array($terms) || ! array_is_list($terms) || array_filter($terms, fn ($t) => ! is_string($t) || ! preg_match('/^\d{1,2}(-\d)?$/', $t))) {
                $errors[] = 'terms phải là danh sách kỳ học dạng "10", "11", "12" hoặc "10-1".';
            }
        }

        if (isset($formula['aggregate']) && ! in_array($formula['aggregate'], self::AGGREGATES, true)) {
            $errors[] = 'aggregate phải là avg hoặc sum.';
        }

        foreach (['subject_scale', 'scale_to'] as $key) {
            if (isset($formula[$key]) && (! is_numeric($formula[$key]) || $formula[$key] <= 0)) {
                $errors[] = "{$key} phải là số lớn hơn 0.";
            }
        }

        foreach (['encourage_bonus_max', 'min_total'] as $key) {
            if (isset($formula[$key]) && (! is_numeric($formula[$key]) || $formula[$key] < 0)) {
                $errors[] = "{$key} phải là số không âm.";
            }
        }

        if (isset($formula['priority'])) {
            $priority = $formula['priority'];

            if (! is_array($priority) || (isset($priority['apply']) && ! is_bool($priority['apply']))
                || (isset($priority['taper_from']) && (! is_numeric($priority['taper_from']) || $priority['taper_from'] <= 0))
                || array_diff(array_keys($priority), ['apply', 'taper_from'])) {
                $errors[] = 'priority phải có dạng {"apply": true, "taper_from": 22.5}.';
            }
        }

        if (isset($formula['certificate_conversion'])) {
            $errors = [...$errors, ...$this->certificateErrors($formula['certificate_conversion'])];
        }

        return $errors;
    }

    public function passes(mixed $formula): bool
    {
        return $this->errors($formula) === [];
    }

    /**
     * @return list<string>
     */
    private function certificateErrors(mixed $conversion): array
    {
        $invalid = 'certificate_conversion phải có dạng {"subject": "ANH", "table": {"IELTS": {"5.0": 8.0}}}.';

        if (! is_array($conversion) || array_diff(array_keys($conversion), ['subject', 'table'])
            || ! isset($conversion['subject'], $conversion['table'])
            || ! is_string($conversion['subject']) || ! preg_match('/^[A-Z0-9_]+$/', $conversion['subject'])
            || ! is_array($conversion['table'])) {
            return [$invalid];
        }

        foreach ($conversion['table'] as $certificate => $levels) {
            if (! is_string($certificate) || ! is_array($levels) || $levels === []) {
                return [$invalid];
            }

            foreach ($levels as $level => $score) {
                if (! is_numeric($score) || $score < 0) {
                    return ["Điểm quy đổi của {$certificate} {$level} phải là số không âm."];
                }
            }
        }

        return [];
    }
}
