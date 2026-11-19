<?php

namespace App\Domain\Api\Observers;

use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\Application;
use App\Domain\Api\Enums\WebhookEvent;
use App\Domain\Api\Services\WebhookDispatcher;

/** Sends case.enrolled when a preference becomes confirmed and application.admitted when it becomes admitted. */
class ApplicationWebhookObserver
{
    public function updated(Application $application): void
    {
        if (! $application->wasChanged('status')) {
            return;
        }

        $event = match ($application->status) {
            ApplicationStatus::Confirmed => WebhookEvent::CaseEnrolled,
            ApplicationStatus::Admitted => WebhookEvent::ApplicationAdmitted,
            default => null,
        };

        if ($event !== null) {
            app(WebhookDispatcher::class)->application($event, $application);
        }
    }
}
