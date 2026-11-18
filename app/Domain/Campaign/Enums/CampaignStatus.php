<?php

namespace App\Domain\Campaign\Enums;

/**
 * Approval flow of a campaign (flow F1 step 5):
 *   draft → pending_approval → running ⇄ paused → ended
 * pending_approval can be sent back to draft by the approver.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Running = 'running';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::PendingApproval => 'Chờ duyệt',
            self::Running => 'Đang chạy',
            self::Paused => 'Tạm dừng',
            self::Ended => 'Kết thúc',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Draft => [self::PendingApproval],
            self::PendingApproval => [self::Running, self::Draft],
            self::Running => [self::Paused, self::Ended],
            self::Paused => [self::Running, self::Ended],
            self::Ended => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }
}
