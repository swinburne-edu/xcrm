<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Database notification (bell): a case has been past its stage SLA without any contact (task 2.4).
 * Sent to the team leader (or the managers when the case has no team leader), once per stage entry / assignment.
 */
class SlaBreached extends Notification
{
    public function __construct(
        private readonly string $applicantName,
        private readonly string $applicantUuid,
        private readonly string $stage,
        private readonly ?string $ownerName,
        private readonly int $slaHours,
        private readonly int $overdueHours,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'sla',
            'level' => 'breached',
            'title' => "Quá hạn xử lý: {$this->applicantName}",
            'body' => "Hồ sơ ở giai đoạn \"{$this->stage}\" (SLA {$this->slaHours} giờ) đã quá hạn {$this->overdueHours} giờ mà chưa có liên hệ"
                .($this->ownerName ? "; người phụ trách: {$this->ownerName}." : '.'),
            'url' => "/applicants/{$this->applicantUuid}",
        ];
    }
}
