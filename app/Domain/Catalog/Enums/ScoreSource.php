<?php

namespace App\Domain\Catalog\Enums;

/**
 * Where an admission method takes its scores from (admission_methods.score_source).
 */
enum ScoreSource: string
{
    case ThptExam = 'thpt_exam';
    case Transcript = 'transcript';
    case CompetencyExam = 'competency_exam';
    case Direct = 'direct';
    case Talent = 'talent';
    case Combined = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::ThptExam => 'Thi tốt nghiệp THPT',
            self::Transcript => 'Học bạ THPT',
            self::CompetencyExam => 'Đánh giá năng lực / tư duy',
            self::Direct => 'Xét tuyển thẳng',
            self::Talent => 'Năng khiếu',
            self::Combined => 'Kết hợp',
        };
    }
}
