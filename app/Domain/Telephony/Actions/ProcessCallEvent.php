<?php

namespace App\Domain\Telephony\Actions;

use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Activity\Actions\SaveTask;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Actions\IntakeLead;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Shared\Services\PhoneNormalizer;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Telephony\Enums\CallStatus;
use App\Domain\Telephony\Models\CallIntegration;
use App\Domain\Telephony\Models\CallLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * One event of the telephone system (ringing, answered, ended) for one call, identified by its call id.
 *  - The first event creates the call log. For an inbound call the caller is matched to an applicant by phone number;
 *    an unknown caller becomes a new applicant with a case (flow F3, source Hotline), so no call is lost.
 *  - The agent is the user whose email the event names, else the owner of the case of the applicant.
 *  - When the call ends, one timeline entry is written (duration, recording). An inbound call nobody answered is
 *    "missed" and gives the owner a task to call back.
 * Events may arrive more than once or out of order: the log is looked up by call id and finished only once.
 */
class ProcessCallEvent
{
    public function __construct(
        private readonly IntakeLead $intake,
        private readonly PhoneNormalizer $phones,
        private readonly RecordActivity $record,
        private readonly SaveTask $saveTask,
        private readonly CurrentOrganization $current,
    ) {}

    /** @param  array<string, mixed>  $e  call_id, event, direction, from, to, agent_email, duration_sec, recording_url, started_at */
    public function handle(CallIntegration $integration, array $e): CallLog
    {
        return $this->current->run($integration->organization_id, function () use ($integration, $e): CallLog {
            $log = CallLog::query()->where('external_id', (string) $e['call_id'])->first() ?? $this->open($integration, $e);

            if ($log->activity_id !== null || in_array($log->status, [CallStatus::Missed, CallStatus::Ended], true)) {
                return $log;
            }

            match ($e['event']) {
                'answered' => $log->forceFill(['status' => CallStatus::Answered, 'answered_at' => $log->answered_at ?? now()])->save(),
                'ended' => $this->finish($log, $e),
                default => null,
            };

            return $log;
        });
    }

    /** @param  array<string, mixed>  $e */
    private function open(CallIntegration $integration, array $e): CallLog
    {
        $inbound = $e['direction'] === 'in';
        $customer = (string) ($inbound ? $e['from'] : $e['to']);
        $applicant = $inbound ? $this->callerOf($integration, $customer) : $this->applicantByPhone($integration->organization_id, $customer);
        $agent = $this->agent($integration, $e['agent_email'] ?? null) ?? $this->ownerOf($applicant);

        return CallLog::query()->create([
            'organization_id' => $integration->organization_id,
            'integration_id' => $integration->id,
            'external_id' => (string) $e['call_id'],
            'direction' => $e['direction'],
            'from_number' => (string) $e['from'],
            'to_number' => (string) $e['to'],
            'phone_normalized' => $this->phones->normalize($customer),
            'applicant_id' => $applicant?->id,
            'user_id' => $agent?->id,
            'status' => CallStatus::Ringing,
            'started_at' => isset($e['started_at']) ? Carbon::parse($e['started_at']) : now(),
        ]);
    }

    /** @param  array<string, mixed>  $e */
    private function finish(CallLog $log, array $e): void
    {
        $duration = (int) ($e['duration_sec'] ?? 0);
        $answered = $log->answered_at !== null || $duration > 0;
        $missed = $log->direction === 'in' && ! $answered;

        $log->forceFill([
            'status' => $missed ? CallStatus::Missed : CallStatus::Ended,
            'duration_sec' => $duration,
            'recording_url' => $e['recording_url'] ?? $log->recording_url,
        ])->save();

        $applicant = $log->applicant_id === null ? null : Applicant::query()->withoutGlobalScopes()->find($log->applicant_id);
        $agent = $log->user_id === null ? null : User::query()->find($log->user_id);

        if ($applicant === null) {
            return;
        }

        $title = $missed ? 'Cuộc gọi nhỡ' : ($log->direction === 'in' ? 'Cuộc gọi đến' : 'Cuộc gọi đi');
        $activity = $this->record->handle($applicant, ActivityType::Call, [
            'direction' => $log->direction,
            'title' => $title,
            'duration_sec' => $duration,
            'recording_url' => $log->recording_url,
            'meta' => ['call_log_id' => $log->id],
        ], $agent);
        $log->forceFill(['activity_id' => $activity->id])->save();

        if ($missed && $agent !== null) {
            $this->saveTask->handle(null, ['title' => 'Gọi lại khách nhỡ máy', 'assignee_id' => $agent->id, 'due_at' => DisplayTime::toInput(now()->addHour())], $agent, $applicant);
        }
    }

    /** Inbound caller: an existing applicant, or a new one created through the lead intake. */
    private function callerOf(CallIntegration $integration, string $number): ?Applicant
    {
        try {
            $source = LeadSource::query()->where('code', 'hotline')->value('id');
            $result = $this->intake->handle($integration->organization_id, ['full_name' => "Khách gọi đến {$number}", 'phone' => $number], ['source_id' => $source, 'label' => 'Cuộc gọi đến tổng đài']);

            return $result['applicant'];
        } catch (ValidationException) {
            return null;
        }
    }

    private function applicantByPhone(int $organizationId, string $number): ?Applicant
    {
        $normalized = $this->phones->normalize($number);

        return $normalized === null ? null : Applicant::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->whereNull('deleted_at')->where('phone_normalized', $normalized)->first();
    }

    private function agent(CallIntegration $integration, ?string $email): ?User
    {
        return blank($email) ? null : User::query()->where('organization_id', $integration->organization_id)->where('email', $email)->first();
    }

    private function ownerOf(?Applicant $applicant): ?User
    {
        if ($applicant === null) {
            return null;
        }

        $ownerId = AdmissionCase::query()->withoutGlobalScopes()->where('applicant_id', $applicant->id)->whereNull('deleted_at')->orderByDesc('cycle_id')->value('owner_id');

        return $ownerId === null ? null : User::query()->find($ownerId);
    }
}
