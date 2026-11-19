<?php

namespace App\Domain\Telephony\Drivers;

use App\Domain\Telephony\Contracts\CallDriver;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Generic gateway: POST {call_id, agent, to} to TELEPHONY_URL with a bearer token. */
class HttpCallDriver implements CallDriver
{
    public function dial(User $agent, string $toNumber, string $callId): ?string
    {
        $url = config('telephony.http.url');

        if (blank($url)) {
            return 'Chưa cấu hình tổng đài (TELEPHONY_URL).';
        }

        try {
            $response = Http::timeout(10)->withToken((string) config('telephony.http.token'))->acceptJson()->post($url, [
                'call_id' => $callId,
                'agent' => ['id' => $agent->id, 'name' => $agent->name, 'email' => $agent->email],
                'to' => $toNumber,
            ]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return $response->failed() ? "Tổng đài trả lỗi {$response->status()}." : null;
    }
}
