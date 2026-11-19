<?php

namespace App\Domain\Communication\Jobs;

use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Communication\Data\OutboundMessage;
use App\Domain\Communication\Data\SendResult;
use App\Domain\Communication\Enums\MessageStatus;
use App\Domain\Communication\Models\MessageLog;
use App\Domain\Communication\Services\ChannelManager;
use App\Domain\Communication\Services\TemplateRenderer;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends one queued message_logs row through its channel driver, stores the outcome and, when it went out, adds an entry
 * to the applicant's timeline. A row that is no longer "queued" is left alone, so a retry never sends twice.
 */
class DeliverMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $logId) {}

    public function handle(ChannelManager $channels, TemplateRenderer $renderer, RecordActivity $record, CurrentOrganization $current): void
    {
        $log = MessageLog::query()->withoutGlobalScopes()->find($this->logId);

        if ($log === null || $log->status !== MessageStatus::Queued) {
            return;
        }

        $current->run($log->organization_id, function () use ($log, $channels, $renderer, $record): void {
            $template = $log->template;
            $applicant = $log->applicant;
            $variables = $applicant === null ? [] : collect($renderer->valuesFor($applicant, $log->user))
                ->mapWithKeys(fn ($v, $k) => [str_replace('.', '_', $k) => $v])->all();

            try {
                $result = $channels->for($log->channel)->send(new OutboundMessage(
                    recipient: (string) $log->recipient,
                    body: (string) $log->body,
                    subject: $log->subject,
                    providerTemplateId: $template?->provider_template_id,
                    variables: $variables,
                    trackingId: "msg-{$log->id}",
                ));
            } catch (Throwable $e) {
                $result = SendResult::failed($e->getMessage());
            }

            $log->forceFill($result->ok
                ? ['status' => MessageStatus::Sent, 'provider_message_id' => $result->providerMessageId, 'sent_at' => now(), 'error' => null]
                : ['status' => MessageStatus::Failed, 'error' => mb_substr((string) $result->error, 0, 1000)])->save();

            if ($result->ok && $applicant !== null) {
                $record->handle($applicant, $log->channel->activityType(), [
                    'direction' => 'out',
                    'title' => $template?->name,
                    'body' => $log->body,
                    'meta' => ['message_log_id' => $log->id],
                ], $log->user);
            }
        });
    }
}
