<?php

namespace App\Domain\Admission\Enums;

/** Life of one nguyện vọng (docs/database.dbml applications.status). */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';
    case ConditionalAdmit = 'conditional_admit';
    case Admitted = 'admitted';
    case Rejected = 'rejected';
    case Confirmed = 'confirmed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Submitted => 'Đã nộp',
            self::Eligible => 'Đủ điều kiện',
            self::Ineligible => 'Không đủ điều kiện',
            self::ConditionalAdmit => 'Trúng tuyển có điều kiện',
            self::Admitted => 'Trúng tuyển',
            self::Rejected => 'Không trúng tuyển',
            self::Confirmed => 'Xác nhận nhập học',
            self::Withdrawn => 'Đã rút',
        };
    }

    /** Badge tone understood by <ToneBadge>. */
    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Withdrawn => 'gray',
            self::Submitted => 'blue',
            self::Eligible, self::ConditionalAdmit => 'violet',
            self::Admitted, self::Confirmed => 'green',
            self::Ineligible, self::Rejected => 'red',
        };
    }

    /** Statuses that count as "đã nộp" (not a draft, not withdrawn). */
    public static function submittedOrLater(): array
    {
        return [self::Submitted, self::Eligible, self::ConditionalAdmit, self::Admitted, self::Confirmed, self::Ineligible, self::Rejected];
    }

    /** Statuses of an application still competing for a place. */
    public function isActive(): bool
    {
        return ! in_array($this, [self::Ineligible, self::Rejected, self::Withdrawn], true);
    }
}
