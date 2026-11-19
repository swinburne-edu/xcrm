<?php

namespace App\Domain\Communication\Channels;

use App\Domain\Communication\Contracts\MessageChannel;
use App\Domain\Communication\Data\OutboundMessage;
use App\Domain\Communication\Data\SendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Zalo Notification Service: sends an approved template (provider_template_id) to a phone number, with the template
 * variables as template_data. Needs ZALO_ZNS_ACCESS_TOKEN (Official Account token).
 */
class ZnsChannel implements MessageChannel
{
    public function send(OutboundMessage $message): SendResult
    {
        $token = config('communication.zns.access_token');

        if (blank($token)) {
            return SendResult::failed('Chưa cấu hình Zalo ZNS (ZALO_ZNS_ACCESS_TOKEN).');
        }

        if (blank($message->providerTemplateId)) {
            return SendResult::failed('Mẫu tin chưa có mã mẫu ZNS đã được Zalo duyệt.');
        }

        try {
            $response = Http::timeout(15)->withHeaders(['access_token' => $token])->acceptJson()->post((string) config('communication.zns.url'), [
                'phone' => $message->recipient,
                'template_id' => $message->providerTemplateId,
                'template_data' => $message->variables,
                'tracking_id' => $message->trackingId,
            ]);
        } catch (Throwable $e) {
            return SendResult::failed($e->getMessage());
        }

        if ($response->failed() || (int) $response->json('error', 0) !== 0) {
            return SendResult::failed('Zalo ZNS từ chối: '.($response->json('message') ?? "HTTP {$response->status()}"));
        }

        $id = $response->json('data.msg_id');

        return SendResult::sent($id === null ? null : (string) $id);
    }
}
