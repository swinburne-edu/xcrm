<?php

namespace App\Domain\Admission\Services;

use App\Domain\Admission\Models\Application;
use App\Domain\Applicant\Enums\ScoreType;
use App\Domain\Applicant\Models\ApplicantCertificate;
use App\Domain\Applicant\Models\ApplicantScore;
use Illuminate\Support\Collection;

/**
 * Computes the admission score of an Application from cycle_methods.score_formula (docs/04-database.md §6).
 * Nothing is hard-coded: a different year or method is a different JSON. No eval, only the keys below.
 *
 *   source                   thpt_exam | transcript (per subject of the combination) | competency_exam | thinking_exam | talent (one exam total)
 *   terms                    transcript: terms that count: a semester ("12-1") or a whole grade ("12" = both semesters); empty = every term found
 *   aggregate                transcript: avg (default) | sum over the terms (the sum is out of subject_scale)
 *   subject_scale            max of one subject score (default 10)
 *   scale_to                 max of the total (default 30)
 *   exam_name                exam sources: only the exam with this name (e.g. "HSA")
 *   certificate_conversion   {subject: "ANH", table: {IELTS: {"5.0": 8, "6.5": 9.5}}}: a certificate replaces the subject score when higher
 *   priority                 {apply: true, taper_from: 22.5}: KV + đối tượng bonus, tapered above taper_from
 *   encourage_bonus_max      cap of the manually entered encouragement bonus
 *   cap_total                false = do not cap the total at scale_to (default: capped)
 *   min_total                minimum raw score; reported as meets_minimum
 *
 * raw      = Σ(score × weight) / Σweight × scale_to / subject_scale        (per-subject sources)
 *          = score / max_score × scale_to                                    (exam sources)
 * priority = (KV + UT) × (scale_to − raw) / (scale_to − taper_from) once raw ≥ taper_from
 * final    = min(raw + priority + encourage, scale_to)
 */
class ScoreCalculator
{
    public function calculate(Application $application): ScoreResult
    {
        $application->loadMissing([
            'cycleMethod', 'combination.subjects',
            'case.applicant.scores', 'case.applicant.certificates', 'case.applicant.priorityArea', 'case.applicant.priorityGroup',
        ]);

        $method = $application->cycleMethod;
        $formula = $method?->score_formula ?? [];

        if ($method?->is_direct) {
            return new ScoreResult(null, 0.0, 0.0, null, ['source' => 'direct', 'note' => 'Xét tuyển thẳng không tính điểm.']);
        }

        if ($formula === []) {
            return new ScoreResult(null, 0.0, 0.0, null, [], ['formula']);
        }

        $source = (string) ($formula['source'] ?? 'thpt_exam');
        $scaleTo = (float) ($formula['scale_to'] ?? 30);
        $breakdown = ['source' => $source, 'scale_to' => $scaleTo];
        $missing = [];

        $raw = match ($source) {
            'transcript', 'thpt_exam' => $this->bySubjects($application, $formula, $source, $scaleTo, $breakdown, $missing),
            'competency_exam', 'thinking_exam', 'talent' => $this->byExam($application, $formula, $source, $scaleTo, $breakdown, $missing),
            default => $this->unknownSource($missing),
        };

        if ($raw === null) {
            return new ScoreResult(null, 0.0, 0.0, null, $breakdown, $missing);
        }

        $raw = round($raw, 2);
        $priority = $this->priorityBonus($application, $formula, $raw, $scaleTo, $breakdown);
        $encourage = $this->encourageBonus($application, $formula);
        $final = $raw + $priority + $encourage;

        if (($formula['cap_total'] ?? true) !== false) {
            $final = min($final, $scaleTo);
        }

        $min = isset($formula['min_total']) ? (float) $formula['min_total'] : null;
        $breakdown['min_total'] = $min;
        $breakdown['meets_minimum'] = $min === null || $raw >= $min;

        return new ScoreResult($raw, $priority, $encourage, round($final, 2), $breakdown, $missing);
    }

    /** @param  list<string>  $missing */
    private function unknownSource(array &$missing): ?float
    {
        $missing[] = 'source';

        return null;
    }

    /**
     * @param  array<string, mixed>  $formula
     * @param  array<string, mixed>  $breakdown
     * @param  list<string>  $missing
     */
    private function bySubjects(Application $application, array $formula, string $source, float $scaleTo, array &$breakdown, array &$missing): ?float
    {
        $combination = $application->combination;

        if ($combination === null) {
            $missing[] = 'combination';

            return null;
        }

        $type = ScoreType::from($source);
        $subjectScale = (float) ($formula['subject_scale'] ?? 10);
        $applicant = $application->case->applicant;
        $conversion = $formula['certificate_conversion'] ?? null;
        $weighted = 0.0;
        $weights = 0.0;
        $rows = [];

        foreach ($combination->subjects as $subject) {
            $weight = (float) ($subject->pivot->weight ?? 1);
            $value = $this->subjectValue($applicant->scores, $type, $subject->id, $formula, $subjectScale);
            $fromCertificate = false;

            if (is_array($conversion) && ($conversion['subject'] ?? null) === $subject->code) {
                $converted = $this->certificateValue($applicant->certificates, $conversion['table'] ?? []);

                if ($converted !== null && ($value === null || $converted > $value)) {
                    $value = $converted;
                    $fromCertificate = true;
                }
            }

            $rows[] = ['subject' => $subject->code, 'weight' => $weight, 'score' => $value === null ? null : round($value, 2), 'from_certificate' => $fromCertificate];

            if ($value === null) {
                $missing[] = $subject->code;

                continue;
            }

            $weighted += $value * $weight;
            $weights += $weight;
        }

        $breakdown['combination'] = $combination->code;
        $breakdown['subjects'] = $rows;

        if ($missing !== [] || $weights <= 0.0) {
            return null;
        }

        return $weighted / $weights * ($scaleTo / $subjectScale);
    }

    /**
     * A subject score on the subject scale: transcript = avg/sum of the chosen terms, exam = the latest year.
     *
     * @param  Collection<int, ApplicantScore>  $scores
     * @param  array<string, mixed>  $formula
     */
    private function subjectValue(Collection $scores, ScoreType $type, int $subjectId, array $formula, float $subjectScale): ?float
    {
        $mine = $scores->filter(fn (ApplicantScore $s) => $s->score_type === $type && $s->subject_id === $subjectId && $s->score !== null);

        if ($type === ScoreType::ThptExam) {
            $latest = $mine->sortByDesc('year')->first();

            return $latest === null ? null : $this->normalise($latest, $subjectScale);
        }

        $terms = array_map('strval', $formula['terms'] ?? []);
        $sum = ($formula['aggregate'] ?? 'avg') === 'sum';

        if ($terms === []) {
            // Every term found counts. Sum: the total is out of subject_scale, each score is worth subject_scale / count.
            if ($mine->isEmpty()) {
                return null;
            }

            $share = $subjectScale / $mine->count();

            return $sum
                ? (float) $mine->sum(fn (ApplicantScore $s) => $this->normalise($s, $share))
                : (float) $mine->avg(fn (ApplicantScore $s) => $this->normalise($s, $subjectScale));
        }

        // A listed term is one semester ("12-1") or a whole grade ("12" = both semesters, averaged).
        $perTerm = [];

        foreach ($terms as $term) {
            $rows = $mine->filter(fn (ApplicantScore $s) => (string) $s->term === $term || str_starts_with((string) $s->term, $term.'-'));

            if ($rows->isNotEmpty()) {
                $perTerm[] = (float) $rows->avg(fn (ApplicantScore $s) => $this->normalise($s, $sum ? $subjectScale / count($terms) : $subjectScale));
            }
        }

        if ($perTerm === []) {
            return null;
        }

        return $sum ? array_sum($perTerm) : array_sum($perTerm) / count($perTerm);
    }

    private function normalise(ApplicantScore $score, float $scale): float
    {
        $max = (float) $score->max_score;

        return $max > 0 ? (float) $score->score / $max * $scale : 0.0;
    }

    /**
     * Best subject score a foreign-language certificate converts to: the highest threshold the certificate reaches.
     *
     * @param  Collection<int, ApplicantCertificate>  $certificates
     * @param  array<string, array<string, int|float>>  $table
     */
    private function certificateValue(Collection $certificates, array $table): ?float
    {
        $best = null;

        foreach ($certificates as $certificate) {
            $thresholds = $table[$certificate->type->value] ?? null;

            if (! is_array($thresholds) || ! is_numeric($certificate->score)) {
                continue;
            }

            if ($certificate->expires_at !== null && $certificate->expires_at->isPast()) {
                continue;
            }

            $reached = null;

            foreach ($thresholds as $threshold => $value) {
                if ((float) $threshold <= (float) $certificate->score && ($reached === null || (float) $threshold > $reached[0])) {
                    $reached = [(float) $threshold, (float) $value];
                }
            }

            if ($reached !== null && ($best === null || $reached[1] > $best)) {
                $best = $reached[1];
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $formula
     * @param  array<string, mixed>  $breakdown
     * @param  list<string>  $missing
     */
    private function byExam(Application $application, array $formula, string $source, float $scaleTo, array &$breakdown, array &$missing): ?float
    {
        $type = ScoreType::from($source);
        $name = $formula['exam_name'] ?? null;
        $best = null;

        foreach ($application->case->applicant->scores as $score) {
            if ($score->score_type !== $type || $score->score === null || (float) $score->max_score <= 0) {
                continue;
            }

            if ($name !== null && strcasecmp((string) $score->exam_name, (string) $name) !== 0) {
                continue;
            }

            $ratio = (float) $score->score / (float) $score->max_score;

            if ($best === null || $ratio > $best['ratio']) {
                $best = ['ratio' => $ratio, 'exam' => $score->exam_name, 'score' => (float) $score->score, 'max' => (float) $score->max_score];
            }
        }

        if ($best === null) {
            $missing[] = 'score';

            return null;
        }

        $breakdown['exam'] = ['name' => $best['exam'], 'score' => $best['score'], 'max' => $best['max']];

        return $best['ratio'] * $scaleTo;
    }

    /**
     * @param  array<string, mixed>  $formula
     * @param  array<string, mixed>  $breakdown
     */
    private function priorityBonus(Application $application, array $formula, float $raw, float $scaleTo, array &$breakdown): float
    {
        if (! ($formula['priority']['apply'] ?? false)) {
            return 0.0;
        }

        $applicant = $application->case->applicant;
        $area = (float) ($applicant->priorityArea?->bonus_score ?? 0);
        $group = (float) ($applicant->priorityGroup?->bonus_score ?? 0);
        $base = $area + $group;
        $bonus = $base;
        $taperFrom = $formula['priority']['taper_from'] ?? null;

        if ($taperFrom !== null && $raw >= (float) $taperFrom && $scaleTo > (float) $taperFrom) {
            $bonus = max(0.0, $base * ($scaleTo - $raw) / ($scaleTo - (float) $taperFrom));
        }

        $breakdown['priority'] = ['area' => $area, 'group' => $group, 'base' => $base, 'applied' => round($bonus, 2), 'tapered' => $bonus !== $base];

        return round($bonus, 2);
    }

    /** @param  array<string, mixed>  $formula */
    private function encourageBonus(Application $application, array $formula): float
    {
        $entered = max(0.0, (float) ($application->encourage_bonus ?? 0));
        $max = $formula['encourage_bonus_max'] ?? null;

        return round($max === null ? $entered : min($entered, (float) $max), 2);
    }
}
