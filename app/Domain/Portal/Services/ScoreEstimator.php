<?php

namespace App\Domain\Portal\Services;

use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\Catalog\Models\Subject;

/**
 * Public "score and major suggestion" (task 3.4). Given the subject scores a student typed, estimates for every major
 * of the cycle the score of each of its combinations under every method that scores by subjects (thpt_exam,
 * transcript), with the same formula as ScoreCalculator: sum(score x weight) / sum(weight) x scale_to / subject_scale
 * (+ the bonus typed in, capped at scale_to). Priority points are not guessed: the student enters them as a bonus.
 * An estimate, not an admission result: no cut-off scores are stored, so a major is only marked as meeting the minimum
 * of the method (min_total) when the method has one.
 */
class ScoreEstimator
{
    /**
     * @param  array<string, float|int|string|null>  $scores  subject code => score
     * @return list<array<string, mixed>> one row per major, best estimate first
     */
    public function suggest(AdmissionCycle $cycle, array $scores, float $bonus = 0.0): array
    {
        $scores = array_filter(array_map(fn ($v) => is_numeric($v) ? (float) $v : null, $scores), fn ($v) => $v !== null);
        $rows = [];

        $majors = Major::query()->where('is_active', true)
            ->with(['combinations' => fn ($q) => $q->wherePivot('cycle_id', $cycle->id)->with('subjects:id,code'), 'cycleMethods' => fn ($q) => $q->where('cycle_id', $cycle->id)->with('method:id,name')])
            ->get();

        foreach ($majors as $major) {
            $options = [];

            foreach ($major->cycleMethods as $method) {
                $source = $method->score_formula['source'] ?? null;

                if ($method->is_direct || ! in_array($source, ['thpt_exam', 'transcript'], true)) {
                    continue;
                }

                foreach ($major->combinations as $combination) {
                    $estimate = $this->estimate($method, $combination->subjects, $scores, $bonus);

                    if ($estimate !== null) {
                        $options[] = ['combination' => $combination->code, 'method' => $method->display_name ?: $method->method?->name, 'source' => $source] + $estimate;
                    }
                }
            }

            if ($options !== []) {
                usort($options, fn ($a, $b) => $b['score'] <=> $a['score']);
                $rows[] = ['major_id' => $major->id, 'major_name' => $major->name, 'best' => $options[0], 'options' => array_slice($options, 0, 6)];
            }
        }

        usort($rows, fn ($a, $b) => $b['best']['score'] <=> $a['best']['score']);

        return $rows;
    }

    /**
     * @param  iterable<Subject>  $subjects
     * @param  array<string, float>  $scores
     * @return array{score: float, meets_minimum: bool}|null null when a subject of the combination has no score
     */
    private function estimate(CycleMethod $method, iterable $subjects, array $scores, float $bonus): ?array
    {
        $formula = $method->score_formula;
        $subjectScale = (float) ($formula['subject_scale'] ?? 10);
        $scaleTo = (float) ($formula['scale_to'] ?? 30);
        $weighted = 0.0;
        $weights = 0.0;

        foreach ($subjects as $subject) {
            if (! isset($scores[$subject->code])) {
                return null;
            }

            $weight = (float) ($subject->pivot->weight ?? 1);
            $weighted += $scores[$subject->code] * $weight;
            $weights += $weight;
        }

        if ($weights <= 0) {
            return null;
        }

        $raw = round($weighted / $weights * $scaleTo / $subjectScale, 2);
        $final = $raw + max(0, $bonus);

        if (($formula['cap_total'] ?? true) !== false) {
            $final = min($final, $scaleTo);
        }

        $min = isset($formula['min_total']) ? (float) $formula['min_total'] : null;

        return ['score' => round($final, 2), 'meets_minimum' => $min === null || $raw >= $min];
    }
}
