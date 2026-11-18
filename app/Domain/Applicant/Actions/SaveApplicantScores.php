<?php

namespace App\Domain\Applicant\Actions;

use App\Domain\Applicant\Enums\ScoreType;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantScore;
use Illuminate\Support\Facades\DB;

/**
 * Upserts the cells of the quick-entry grid. A cell is identified by (type, subject, term, exam name);
 * an empty score removes it. Changing a score clears its "verified" flag.
 */
class SaveApplicantScores
{
    /**
     * @param  list<array<string, mixed>>  $rows  validated by ScoresRequest
     * @return array{saved: int, deleted: int}
     */
    public function handle(Applicant $applicant, array $rows): array
    {
        $saved = 0;
        $deleted = 0;

        DB::transaction(function () use ($applicant, $rows, &$saved, &$deleted): void {
            foreach ($rows as $row) {
                $type = ScoreType::from($row['score_type']);
                $key = [
                    'applicant_id' => $applicant->id,
                    'score_type' => $type->value,
                    'subject_id' => $type->perSubject() ? $row['subject_id'] : null,
                    'term' => $type === ScoreType::Transcript ? $row['term'] : null,
                    'exam_name' => $type->perSubject() ? null : $row['exam_name'],
                ];

                $existing = ApplicantScore::query()->where($key)->first();
                $empty = ! isset($row['score']) || $row['score'] === '';

                if ($empty) {
                    $existing?->delete();
                    $deleted += $existing === null ? 0 : 1;

                    continue;
                }

                $values = ['score' => $row['score'], 'max_score' => $row['max_score'] ?? 10, 'year' => $row['year'] ?? null];

                if ($existing === null) {
                    ApplicantScore::query()->create($key + $values);
                } elseif ((float) $existing->score !== (float) $row['score'] || (float) $existing->max_score !== (float) $values['max_score']) {
                    $existing->fill($values + ['verified' => false, 'verified_by' => null])->save();
                } else {
                    $existing->fill(['year' => $values['year']])->save();
                }

                $saved++;
            }
        });

        return ['saved' => $saved, 'deleted' => $deleted];
    }
}
