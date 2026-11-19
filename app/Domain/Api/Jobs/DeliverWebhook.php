<?php

namespace App\Domain\Api\Jobs;

use App\Domain\Api\Models\WebhookDelivery;
use App\Domain\Workflow\Services\SafeWebhookUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * POSTs one delivery to its webhook, signed with the secret. Any 2xx counts as delivered. Anything else is retried
 * up to MAX_ATTEMPTS times with a growing delay (the receiver may be down for a while), then marked failed; the page
 * Cài đặt, API và Webhook can send it again by hand.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const MAX_ATTEMPTS = 4;

    /** seconds to wait after the 1st, 2nd and 3rd failed attempt */
    private const BACKOFF = [60, 600, 3600];

    public int $tries = 1;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(SafeWebhookUrl $urls): void
    {
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->with('webhook')->find($this->deliveryId);

        if ($delivery === null || $delivery->status === 'delivered' || $delivery->webhook === null || ! $delivery->webhook->is_active) {
            return;
        }

        $webhook = $delivery->webhook;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $delivery->increment('attempts');
        $error = $urls->check($webhook->url);
        $code = null;

        if ($error === null) {
            try {
                $response = Http::timeout(10)->withBody($body, 'application/json')->withHeaders([
                    'X-Xcrm-Event' => $delivery->event,
                    'X-Xcrm-Delivery' => $delivery->uuid,
                    'X-Xcrm-Signature' => 'sha256='.hash_hmac('sha256', $body, (string) $webhook->secret),
                ])->post($webhook->url);
                $code = $response->status();
                $error = $response->successful() ? null : "Máy nhận trả lỗi {$code}.";
            } catch (Throwable $e) {
                $error = mb_substr($e->getMessage(), 0, 500);
            }
        }

        if ($error === null) {
            $delivery->forceFill(['status' => 'delivered', 'response_code' => $code, 'error' => null, 'delivered_at' => now()])->save();

            return;
        }

        $final = $delivery->attempts >= self::MAX_ATTEMPTS;
        $delivery->forceFill(['status' => $final ? 'failed' : 'pending', 'response_code' => $code, 'error' => $error])->save();

        if (! $final) {
            self::dispatch($delivery->id)->delay(now()->addSeconds(self::BACKOFF[min($delivery->attempts, count(self::BACKOFF)) - 1]));
        }
    }
}
