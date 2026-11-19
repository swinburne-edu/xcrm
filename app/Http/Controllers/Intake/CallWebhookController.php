<?php

namespace App\Http\Controllers\Intake;

use App\Domain\Telephony\Actions\ProcessCallEvent;
use App\Domain\Telephony\Models\CallIntegration;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Public webhook of the telephone system (task 3.7), no login: POST /api/webhooks/calls/{uuid}. The uuid picks the
 * integration and so the organization; the header X-Signature: sha256=HMAC-SHA256(raw body, secret) proves the sender.
 * Body: {call_id, event: ringing|answered|ended, direction: in|out, from, to, agent_email?, duration_sec?, recording_url?, started_at?}
 */
class CallWebhookController extends Controller
{
    public function __invoke(Request $request, string $uuid, ProcessCallEvent $process): JsonResponse
    {
        $integration = CallIntegration::query()->withoutGlobalScopes()->where('uuid', $uuid)->where('is_active', true)->whereNull('deleted_at')->firstOrFail();

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $integration->secret);

        if (! hash_equals($expected, (string) $request->header('X-Signature'))) {
            return response()->json(['message' => 'Chữ ký không hợp lệ.'], 403);
        }

        $validator = Validator::make($request->json()->all(), [
            'call_id' => ['required', 'string', 'max:100'],
            'event' => ['required', 'in:ringing,answered,ended'],
            'direction' => ['required', 'in:in,out'],
            'from' => ['required', 'string', 'max:40'],
            'to' => ['required', 'string', 'max:40'],
            'agent_email' => ['nullable', 'email'],
            'duration_sec' => ['nullable', 'integer', 'min:0'],
            'recording_url' => ['nullable', 'url', 'max:500'],
            'started_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Dữ liệu không hợp lệ.', 'errors' => $validator->errors()], 422);
        }

        $log = $process->handle($integration, $validator->validated());
        $integration->forceFill(['last_received_at' => now()])->saveQuietly();

        return response()->json(['ok' => true, 'call_log_id' => $log->id, 'status' => $log->status->value]);
    }
}
