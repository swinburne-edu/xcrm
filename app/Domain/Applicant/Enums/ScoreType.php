<?php

namespace App\Domain\Applicant\Enums;

/**
 * Where a score comes from. Transcript and THPT exam scores are per subject; the others are totals
 * of a named exam (HSA, V-ACT, TSA…) and have no subject.
 */
enum ScoreType: string
{
    case Transcript = 'transcript';
    case ThptExam = 'thpt_exam';
    case CompetencyExam = 'competency_exam';
    case ThinkingExam = 'thinking_exam';
    case Talent = 'talent';

    public function label(): string
    {
        return match ($this) {
            self::Transcript => 'Học bạ THPT',
            self::ThptExam => 'Thi tốt nghiệp THPT',
            self::CompetencyExam => 'Đánh giá năng lực',
            self::ThinkingExam => 'Đánh giá tư duy',
            self::Talent => 'Năng khiếu',
        };
    }

    public function perSubject(): bool
    {
        return $this === self::Transcript || $this === self::ThptExam;
    }
}
