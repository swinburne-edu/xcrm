<?php

namespace App\Domain\Intake\Jobs;

use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Actions\IntakeLead;
use App\Domain\Intake\Enums\LeadEventStatus;
use App\Domain\Intake\Enums\LeadProvider;
use App\Domain\Intake\Models\LeadIntegration;
use App\Domain\Intake\Models\LeadWebhookEvent;
use App\Domain\Intake\Services\FacebookLeadFetcher;
use App\Domain\Intake\Services\LeadFieldMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns one received lead into an applicant and case (flow F3, through IntakeLead): reads the Facebook lead from the
 * Graph API (TikTok sends its fields in the body), maps the fields, records the touch with source, campaign and utm.
 * A lead that fails validation (no phone or email...) is logged as failed without retrying; Graph API errors retry.
 */
class ProcessLeadWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 180];

    public function __construct(public readonly int $eventId) {}

    public function handle(IntakeLead $intake, LeadFieldMapper $mapper, FacebookLeadFetcher $facebook): void
    {
        $event = LeadWebhookEvent::query()->find($this->eventId);
        $integration = $event === null ? null : LeadIntegration::query()->withoutGlobalScopes()->find($event->integration_id);

        if ($event === null || $integration === null || $event->status !== LeadEventStatus::Received) {
            return;
        }

        $raw = (array) $event->payload;
        $lead = $integration->provider === LeadProvider::Facebook ? $facebook->fetch($integration, (string) $event->external_id) : $raw;
        $source = (array) ($lead['field_data'] ?? $lead['fields'] ?? $lead['answers'] ?? $lead['lead'] ?? $lead);
        $fields = $mapper->map($mapper->flatten($source), $integration->field_map);

        try {
            $result = $intake->handle($integration->organization_id, $fields['data'] + ['consent' => true], [
                'source_id' => $integration->source_id ?? $this->defaultSource($integration),
                'campaign_id' => $integration->campaign_id,
                'label' => "Lead từ {$integration->provider->label()} “{$integration->name}”",
                'utm' => ['source' => $integration->provider->value, 'medium' => 'lead_ads'],
                'payload' => array_filter([
                    'integration' => $integration->name,
                    'external_id' => $event->external_id,
                    'ad_id' => $lead['ad_id'] ?? $raw['ad_id'] ?? null,
                    'form_id' => $lead['form_id'] ?? $raw['form_id'] ?? null,
                    'campaign_ref' => $lead['campaign_id'] ?? $raw['campaign_id'] ?? null,
                    'fields' => $fields['extra'] ?: null,
                ]),
            ]);
        } catch (ValidationException $e) {
            $event->update(['status' => LeadEventStatus::Failed, 'error' => collect($e->errors())->flatten()->implode(' ')]);

            return;
        }

        $status = $result['created'] ? LeadEventStatus::Created : LeadEventStatus::Merged;
        $event->update(['status' => $status, 'applicant_id' => $result['applicant']->id, 'error' => null]);
    }

    public function failed(Throwable $exception): void
    {
        LeadWebhookEvent::query()->whereKey($this->eventId)->update(['status' => LeadEventStatus::Failed->value, 'error' => mb_substr($exception->getMessage(), 0, 1000)]);
    }

    private function defaultSource(LeadIntegration $integration): ?int
    {
        return LeadSource::query()->withoutGlobalScopes()
            ->where('organization_id', $integration->organization_id)->where('code', $integration->provider->sourceCode())->value('id');
    }
}
