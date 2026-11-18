<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Database notification (bell in the header): a major changed its quota warning level (task 2.3).
 * Same shape as the other in-app notifications: title, body, url, plus a "kind" the bell uses for the icon.
 */
class QuotaAlert extends Notification
{
    /**
     * @param  array{major_id: int, major: string, level: string, gap: int, secured: int, quota: int, target: int}  $row
     */
    public function __construct(private readonly array $row, private readonly string $cycleName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $over = $this->row['level'] === 'over';

        return [
            'kind' => 'quota',
            'level' => $this->row['level'],
            'title' => ($over ? 'Vượt chỉ tiêu: ' : 'Thiếu so với tiến độ: ').$this->row['major'],
            'body' => $over
                ? "Đã có {$this->row['secured']} thí sinh trúng tuyển / nhập học, vượt chỉ tiêu {$this->row['quota']} ({$this->cycleName})."
                : "Mới có {$this->row['secured']} thí sinh trúng tuyển / nhập học, thiếu khoảng {$this->row['gap']} so với tiến độ để đạt mục tiêu {$this->row['target']} ({$this->cycleName}).",
            'url' => "/applications?filter[major_id]={$this->row['major_id']}",
            'major_id' => $this->row['major_id'],
        ];
    }
}
