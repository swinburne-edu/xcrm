<?php

namespace App\Domain\Applicant\Models;

use App\Domain\Applicant\Enums\ScoreType;
use App\Domain\Catalog\Models\Subject;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One score of an applicant: a subject in a term (transcript), a subject in a year (THPT exam),
 * or the total of a named exam (HSA, V-ACT, TSA…, no subject).
 *
 * @property int $id
 * @property int $applicant_id
 * @property ScoreType $score_type
 * @property int|null $subject_id
 * @property string|null $term
 * @property string|null $exam_name
 * @property string|null $score
 * @property string $max_score
 * @property int|null $year
 * @property bool $verified
 */
#[Fillable(['applicant_id', 'score_type', 'subject_id', 'term', 'exam_name', 'score', 'max_score', 'year', 'verified', 'verified_by'])]
class ApplicantScore extends Model
{
    public $timestamps = false;

    protected $attributes = ['max_score' => 10, 'verified' => false];

    protected function casts(): array
    {
        return ['score_type' => ScoreType::class, 'verified' => 'boolean', 'year' => 'integer'];
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
