<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Database notification (bell): a case was taken back to the shared pool because nobody touched it for too long
 * (task 2.4). Sent to the previous owner and the team leader.
 */
class CaseRecalled extends Notification
{
    public function __construct(
        private readonly string $applicantName,
        private readonly string $applicantUuid,
        private readonly ?string $previousOwner,
        private readonly int $days,
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
            'kind' => 'recall',
            'level' => 'recalled',
            'title' => "Đã thu hồi hồ sơ: {$this->applicantName}",
            'body' => 'Hồ sơ không có hoạt động nào trong '.$this->days.' ngày nên đã về kho chung'.($this->previousOwner ? " (trước đó do {$this->previousOwner} phụ trách)." : '.'),
            'url' => "/applicants/{$this->applicantUuid}",
        ];
    }
}
