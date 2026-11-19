<?php

namespace App\Domain\Intake\Services;

use App\Domain\Intake\Enums\LeadProvider;
use App\Domain\Intake\Models\LeadIntegration;
use Illuminate\Http\Request;

/**
 * Receiving side of a lead ads webhook: answers the subscription handshake, checks the signature of a delivery
 * and splits it into leads (one per Facebook leadgen change / TikTok lead).
 *
 *  - Facebook: header X-Hub-Signature-256 = "sha256=" + HMAC-SHA256(raw body, app secret). The body only names the lead
 *    (leadgen_id); its fields are read later from the Graph API (FacebookLeadFetcher).
 *  - TikTok: header TikTok-Signature = "t=<unix time>,s=<hex>", s = HMAC-SHA256("<t>.<raw body>", signing secret),
 *    refused when t is more than 5 minutes old. The body carries the lead fields.
 * A delivery without a configured secret is never accepted.
 */
class LeadWebhookReceiver
{
    public const TIKTOK_TOLERANCE_SECONDS = 300;

    /** Facebook handshake (GET hub.mode=subscribe): the challenge to echo back, or null when the token is wrong. */
    public function challenge(LeadIntegration $integration, Request $request): ?string
    {
        $token = (string) $integration->verify_token;

        if ($integration->provider !== LeadProvider::Facebook || $token === '' || $this->query($request, 'mode') !== 'subscribe') {
            return null;
        }

        return hash_equals($token, $this->query($request, 'verify_token')) ? $this->query($request, 'challenge') : null;
    }

    public function signatureValid(LeadIntegration $integration, Request $request): bool
    {
        $secret = (string) $integration->secret;

        if ($secret === '') {
            return false;
        }

        $body = $request->getContent();

        if ($integration->provider === LeadProvider::Facebook) {
            $given = (string) $request->header('X-Hub-Signature-256', '');

            return str_starts_with($given, 'sha256=') && hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $given);
        }

        $parts = [];

        foreach (explode(',', (string) $request->header('TikTok-Signature', '')) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';

        if (! ctype_digit($timestamp) || $timestamp === '' || abs(time() - (int) $timestamp) > self::TIKTOK_TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), (string) ($parts['s'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload  decoded body
     * @return list<array{external_id: string|null, raw: array<string, mixed>}>
     */
    public function leads(LeadIntegration $integration, array $payload): array
    {
        return $integration->provider === LeadProvider::Facebook ? $this->facebookLeads($payload) : $this->tiktokLeads($payload);
    }

    /** @return list<array{external_id: string|null, raw: array<string, mixed>}> */
    private function facebookLeads(array $payload): array
    {
        $leads = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                if (($change['field'] ?? null) === 'leadgen' && filled($value['leadgen_id'] ?? null)) {
                    $leads[] = ['external_id' => (string) $value['leadgen_id'], 'raw' => $value];
                }
            }
        }

        return $leads;
    }

    /**
     * TikTok delivers one lead object, or a list under "leads" / "data".
     *
     * @return list<array{external_id: string|null, raw: array<string, mixed>}>
     */
    private function tiktokLeads(array $payload): array
    {
        $list = $payload['leads'] ?? $payload['data'] ?? null;
        $items = is_array($list) && array_is_list($list) ? $list : [$payload];
        $leads = [];

        foreach ($items as $item) {
            if (! is_array($item) || $item === []) {
                continue;
            }

            $id = $item['lead_id'] ?? $item['id'] ?? null;
            $leads[] = ['external_id' => is_scalar($id) && (string) $id !== '' ? (string) $id : sha1((string) json_encode($item)), 'raw' => $item];
        }

        return $leads;
    }

    /** PHP turns hub.mode into hub_mode in the query string; accept both spellings. */
    private function query(Request $request, string $name): string
    {
        return (string) ($request->query("hub_{$name}") ?? $request->query("hub.{$name}") ?? '');
    }
}
