<?php

namespace App\Domain\Communication\Actions;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Communication\Enums\MessageChannelType;
use App\Domain\Communication\Jobs\DeliverMessage;
use App\Domain\Communication\Models\MessageLog;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Communication\Services\TemplateRenderer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bulk send of one template to the applicants of a query (the list's current filters).
 *  - Only applicants who agreed to marketing messages (consent_marketing) are messaged.
 *  - zns / sms need a normalized phone number, email needs an email address; others are counted as "no contact".
 *  - At most config('communication.max_bulk') recipients per send.
 * plan() counts and previews without writing; handle() writes one queued log row per recipient and dispatches the delivery job.
 */
class SendMessages
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    /**
     * @param  Builder<Applicant>  $applicants
     * @return array{recipients: list<array{applicant: Applicant, to: string}>, total: int, no_consent: int, no_contact: int, sample: string|null}
     *
     * @throws ValidationException
     */
    public function plan(MessageTemplate $template, Builder $applicants, ?User $by = null): array
    {
        if (! $template->is_active) {
            throw ValidationException::withMessages(['template_id' => 'Mẫu tin đang tắt.']);
        }

        $max = (int) config('communication.max_bulk');
        $all = $applicants->with(['highSchool:id,name', 'organization:id,name'])->limit($max + 1)->get();

        if ($all->count() > $max) {
            throw ValidationException::withMessages(['filter' => "Một lần gửi tối đa {$max} người. Hãy lọc thu hẹp danh sách."]);
        }

        $recipients = [];
        $noConsent = 0;
        $noContact = 0;

        foreach ($all as $applicant) {
            if (! $applicant->consent_marketing) {
                $noConsent++;

                continue;
            }

            $to = $template->channel === MessageChannelType::Email ? $applicant->email : $applicant->phone_normalized;

            if (blank($to)) {
                $noContact++;

                continue;
            }

            $recipients[] = ['applicant' => $applicant, 'to' => (string) $to];
        }

        $first = $recipients[0]['applicant'] ?? null;

        return [
            'recipients' => $recipients,
            'total' => $all->count(),
            'no_consent' => $noConsent,
            'no_contact' => $noContact,
            'sample' => $first === null ? null : $this->renderer->render($template->body, $this->renderer->valuesFor($first, $by)),
        ];
    }

    /**
     * @param  Builder<Applicant>  $applicants
     * @return array{batch_id: string, queued: int, no_consent: int, no_contact: int}
     */
    public function handle(MessageTemplate $template, Builder $applicants, ?User $by = null): array
    {
        $plan = $this->plan($template, $applicants, $by);
        $batch = (string) Str::uuid();
        $ids = [];

        DB::transaction(function () use ($plan, $template, $by, $batch, &$ids): void {
            foreach ($plan['recipients'] as $r) {
                $values = $this->renderer->valuesFor($r['applicant'], $by);
                $ids[] = MessageLog::query()->create([
                    'organization_id' => $template->organization_id,
                    'template_id' => $template->id,
                    'batch_id' => $batch,
                    'channel' => $template->channel,
                    'recipient' => $r['to'],
                    'applicant_id' => $r['applicant']->id,
                    'user_id' => $by?->id,
                    'subject' => $template->subject === null ? null : $this->renderer->render($template->subject, $values),
                    'body' => $this->renderer->render($template->body, $values),
                ])->id;
            }
        });

        foreach ($ids as $id) {
            DeliverMessage::dispatch($id)->afterCommit();
        }

        return ['batch_id' => $batch, 'queued' => count($ids), 'no_consent' => $plan['no_consent'], 'no_contact' => $plan['no_contact']];
    }
}
