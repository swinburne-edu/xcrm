<?php

namespace App\Domain\Intake\Services;

use App\Domain\Intake\Models\LeadIntegration;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a Facebook lead (its field_data) from the Graph API by leadgen_id, with the Page access token of the integration.
 * appsecret_proof (HMAC-SHA256 of the token with the app secret) is sent so an app that requires it still works.
 */
class FacebookLeadFetcher
{
    /**
     * @return array<string, mixed> the Graph API lead: id, created_time, field_data...
     *
     * @throws RequestException when Facebook answers with an error (the job retries)
     * @throws RuntimeException when the integration has no access token
     */
    public function fetch(LeadIntegration $integration, string $leadgenId): array
    {
        $token = (string) $integration->access_token;

        if ($token === '') {
            throw new RuntimeException('Chưa khai báo Page access token để đọc dữ liệu lead từ Facebook.');
        }

        $version = (string) config('services.facebook.graph_version', 'v21.0');

        return Http::timeout(15)->acceptJson()->get("https://graph.facebook.com/{$version}/{$leadgenId}", array_filter([
            'access_token' => $token,
            'appsecret_proof' => $integration->secret ? hash_hmac('sha256', $token, (string) $integration->secret) : null,
            'fields' => 'id,created_time,ad_id,adset_id,campaign_id,form_id,field_data',
        ]))->throw()->json() ?? [];
    }
}
