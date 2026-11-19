<?php

namespace App\Http\Controllers\Intake;

use App\Domain\Intake\Enums\LeadEventStatus;
use App\Domain\Intake\Jobs\ProcessLeadWebhookEvent;
use App\Domain\Intake\Models\LeadIntegration;
use App\Domain\Intake\Models\LeadWebhookEvent;
use App\Domain\Intake\Services\LeadWebhookReceiver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public webhook of a lead ads integration (task 2.7), no login: the uuid in the URL picks the integration and
 * therefore the organization; the signature proves the call comes from the platform.
 *  GET  /api/webhooks/leads/{uuid}  Facebook subscription handshake (hub.verify_token / hub.challenge)
 *  POST /api/webhooks/leads/{uuid}  deliveries: each lead is logged once (external id) and processed by a queue job,
 *                                   so the platform gets its 200 at once and its retries never duplicate a lead.
 */
class LeadWebhookController extends Controller
{
    public function verify(Request $request, string $uuid, LeadWebhookReceiver $receiver): Response
    {
        $challenge = $receiver->challenge($this->integration($uuid), $request);

        abort_if($challenge === null, 403, 'Verify token không đúng.');

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, string $uuid, LeadWebhookReceiver $receiver): JsonResponse
    {
        $integration = $this->integration($uuid);

        if (! $receiver->signatureValid($integration, $request)) {
            LeadWebhookEvent::query()->create(['integration_id' => $integration->id, 'status' => LeadEventStatus::Rejected, 'error' => 'Chữ ký không hợp lệ.']);

            return response()->json(['message' => 'Chữ ký không hợp lệ.'], 403);
        }

        $accepted = 0;

        foreach ($receiver->leads($integration, (array) $request->json()->all()) as $lead) {
            $event = LeadWebhookEvent::query()->firstOrCreate(
                ['integration_id' => $integration->id, 'external_id' => $lead['external_id']],
                ['status' => LeadEventStatus::Received, 'payload' => $lead['raw']],
            );

            if ($event->wasRecentlyCreated) {
                $accepted++;
                ProcessLeadWebhookEvent::dispatch($event->id);
            }
        }

        $integration->forceFill(['last_received_at' => now()])->saveQuietly();

        return response()->json(['ok' => true, 'accepted' => $accepted]);
    }

    private function integration(string $uuid): LeadIntegration
    {
        return LeadIntegration::query()->withoutGlobalScopes()->where('uuid', $uuid)->where('is_active', true)->whereNull('deleted_at')->firstOrFail();
    }
}
