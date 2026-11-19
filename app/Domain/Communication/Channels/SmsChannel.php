<?php

namespace App\Domain\Communication\Channels;

use App\Domain\Communication\Contracts\MessageChannel;
use App\Domain\Communication\Data\OutboundMessage;
use App\Domain\Communication\Data\SendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/** SMS through a JSON gateway configured in config/communication.php (sms.url, sms.token, sms.brandname). */
class SmsChannel implements MessageChannel
{
    public function send(OutboundMessage $message): SendResult
    {
        $url = config('communication.sms.url');

        if (blank($url)) {
            return SendResult::failed('Chưa cấu hình cổng SMS (SMS_API_URL).');
        }

        try {
            $response = Http::timeout(15)->withToken((string) config('communication.sms.token'))->acceptJson()->post($url, [
                'to' => $message->recipient,
                'content' => $message->body,
                'brandname' => config('communication.sms.brandname'),
            ]);
        } catch (Throwable $e) {
            return SendResult::failed($e->getMessage());
        }

        if ($response->failed()) {
            return SendResult::failed("Cổng SMS trả lỗi {$response->status()}: ".mb_substr($response->body(), 0, 300));
        }

        $id = $response->json('id') ?? $response->json('message_id');

        return SendResult::sent($id === null ? null : (string) $id);
    }
}
