<?php

namespace App\Domain\Api\Services;

use App\Domain\Admission\Models\Application;
use App\Domain\Api\Enums\WebhookEvent;
use App\Domain\Api\Jobs\DeliverWebhook;
use App\Domain\Api\Models\Webhook;
use App\Domain\Api\Models\WebhookDelivery;

/**
 * Turns a business event into one queued delivery per active webhook of the organization that listens to it.
 * The body carries identity and result of the application (what a training system needs to enrol the student) but never
 * the citizen id.
 */
class WebhookDispatcher
{
    /** @return int how many deliveries were queued */
    public function application(WebhookEvent $event, Application $application): int
    {
        $webhooks = Webhook::query()->withoutGlobalScopes()->whereNull('deleted_at')->where('organization_id', $application->organization_id)->where('is_active', true)->get()
            ->filter(fn (Webhook $w) => in_array($event->value, $w->events, true));

        if ($webhooks->isEmpty()) {
            return 0;
        }

        $payload = $this->payload($event, $application);

        foreach ($webhooks as $webhook) {
            $delivery = WebhookDelivery::query()->create(['organization_id' => $webhook->organization_id, 'webhook_id' => $webhook->id, 'event' => $event->value, 'payload' => $payload]);
            DeliverWebhook::dispatch($delivery->id)->afterCommit();
        }

        return $webhooks->count();
    }

    /** @return array<string, mixed> */
    public function payload(WebhookEvent $event, Application $application): array
    {
        $application->loadMissing(['case.applicant.province', 'case.applicant.highSchool', 'case.cycle', 'major', 'cycleMethod.method', 'combination', 'round']);
        $case = $application->case;
        $applicant = $case->applicant;

        return [
            'event' => $event->value,
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'applicant' => [
                    'uuid' => $applicant->uuid,
                    'code' => $applicant->code,
                    'full_name' => $applicant->full_name,
                    'gender' => $applicant->gender?->value ?? $applicant->gender,
                    'dob' => $applicant->dob?->format('Y-m-d'),
                    'phone' => $applicant->phone_normalized,
                    'email' => $applicant->email,
                    'province' => $applicant->province?->name,
                    'high_school' => $applicant->highSchool?->name,
                    'graduation_year' => $applicant->graduation_year,
                ],
                'case' => ['uuid' => $case->uuid, 'cycle' => $case->cycle?->name],
                'application' => [
                    'id' => $application->id,
                    'major' => $application->major?->name,
                    'major_code' => $application->major?->moet_code,
                    'admission_code' => $application->major?->admission_code,
                    'method' => $application->cycleMethod?->display_name ?: $application->cycleMethod?->method?->name,
                    'combination' => $application->combination?->code,
                    'round' => $application->round?->name,
                    'final_score' => $application->final_score === null ? null : (float) $application->final_score,
                    'status' => $application->status->value,
                ],
            ],
        ];
    }
}
