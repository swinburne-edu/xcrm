<?php

namespace App\Http\Requests\Applicant;

use App\Domain\Applicant\Enums\ScoreType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Bulk entry of scores (the quick-entry grid). Each item is one cell: transcript = subject × term,
 * THPT exam = subject × year, other exams = a named exam total. An empty score deletes the cell.
 */
class ScoresRequest extends FormRequest
{
    public const TERMS = ['10-1', '10-2', '11-1', '11-2', '12-1', '12-2', '10', '11', '12'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array', 'max:300'],
            'scores.*.score_type' => ['required', Rule::enum(ScoreType::class)],
            'scores.*.subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'scores.*.term' => ['nullable', Rule::in(self::TERMS)],
            'scores.*.exam_name' => ['nullable', 'string', 'max:100'],
            'scores.*.year' => ['nullable', 'integer', 'between:2000,2100'],
            'scores.*.max_score' => ['nullable', 'numeric', 'gt:0', 'max:9999'],
            'scores.*.score' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ((array) $this->input('scores') as $i => $row) {
                $type = ScoreType::from($row['score_type']);
                $max = (float) ($row['max_score'] ?? 10);

                if ($type->perSubject() && blank($row['subject_id'] ?? null)) {
                    $validator->errors()->add("scores.{$i}.subject_id", 'Thiếu môn học.');
                }

                if ($type === ScoreType::Transcript && blank($row['term'] ?? null)) {
                    $validator->errors()->add("scores.{$i}.term", 'Điểm học bạ cần kỳ học.');
                }

                if ($type === ScoreType::ThptExam && blank($row['year'] ?? null)) {
                    $validator->errors()->add("scores.{$i}.year", 'Điểm thi tốt nghiệp cần năm thi.');
                }

                if (! $type->perSubject() && blank($row['exam_name'] ?? null)) {
                    $validator->errors()->add("scores.{$i}.exam_name", 'Cần tên kỳ thi (HSA, V-ACT, TSA…).');
                }

                if (isset($row['score']) && $row['score'] !== '' && (float) $row['score'] > $max) {
                    $validator->errors()->add("scores.{$i}.score", "Điểm không được lớn hơn thang điểm {$max}.");
                }
            }
        }];
    }
}
