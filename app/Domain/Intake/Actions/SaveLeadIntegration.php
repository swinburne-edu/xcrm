<?php

namespace App\Domain\Intake\Actions;

use App\Domain\Intake\Enums\LeadProvider;
use App\Domain\Intake\Models\LeadIntegration;
use App\Domain\Intake\Services\LeadFieldMapper;
use Illuminate\Support\Str;

/**
 * Creates or edits a lead ads integration. The platform is chosen on create and then fixed (the webhook URL and the
 * signature scheme depend on it). Secrets are only replaced when a new value is sent, so editing the name never
 * wipes them. A Facebook integration gets a random verify token when none is given.
 */
class SaveLeadIntegration
{
    /**
     * @param  array<string, mixed>  $data  validated by LeadIntegrationRequest
     */
    public function handle(?LeadIntegration $integration, array $data): LeadIntegration
    {
        $isNew = $integration === null;
        $integration ??= new LeadIntegration(['provider' => LeadProvider::from((string) $data['provider'])]);

        $integration->fill([
            'name' => trim((string) $data['name']),
            'campaign_id' => filled($data['campaign_id'] ?? null) ? (int) $data['campaign_id'] : null,
            'source_id' => filled($data['source_id'] ?? null) ? (int) $data['source_id'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'field_map' => $this->fieldMap((array) ($data['field_map'] ?? [])),
        ]);

        foreach (['secret', 'verify_token', 'access_token'] as $key) {
            if (filled($data[$key] ?? null)) {
                $integration->{$key} = trim((string) $data[$key]);
            }
        }

        if ($isNew && $integration->provider === LeadProvider::Facebook && blank($integration->verify_token)) {
            $integration->verify_token = Str::random(32);
        }

        $integration->save();

        return $integration;
    }

    /**
     * @param  list<array{name?: mixed, key?: mixed}>  $rows
     * @return array<string, string>|null platform field name => lead key
     */
    private function fieldMap(array $rows): ?array
    {
        $map = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $key = (string) ($row['key'] ?? '');

            if ($name !== '' && in_array($key, LeadFieldMapper::KEYS, true)) {
                $map[$name] = $key;
            }
        }

        return $map === [] ? null : $map;
    }
}
