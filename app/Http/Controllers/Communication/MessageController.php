<?php

namespace App\Http\Controllers\Communication;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Services\ApplicantFilters;
use App\Domain\Communication\Actions\SendMessages;
use App\Domain\Communication\Enums\MessageChannelType;
use App\Domain\Communication\Enums\MessageStatus;
use App\Domain\Communication\Models\MessageLog;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /messages (task 3.1): the log of messages sent, and the bulk send to the applicants matched by the applicant list's
 * filters (preview first, then send). Needs message.view / message.send.
 */
class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('message.view'), 403);

        $logs = ListQuery::for(MessageLog::query()->with(['applicant:id,uuid,full_name', 'template:id,name', 'user:id,name']), $request)
            ->filters(Filter::exact('status'), Filter::exact('channel'), Filter::exact('batch_id'))
            ->sorts(['created_at', 'status'])
            ->defaultSort('-created_at')
            ->toProps(fn (MessageLog $l) => [
                'id' => $l->id,
                'channel' => $l->channel->value,
                'channel_label' => $l->channel->label(),
                'recipient' => $l->recipient,
                'applicant_uuid' => $l->applicant?->uuid,
                'applicant_name' => $l->applicant?->full_name,
                'template_name' => $l->template?->name,
                'user_name' => $l->user?->name,
                'body' => $l->body,
                'status' => $l->status->value,
                'status_label' => $l->status->label(),
                'error' => $l->error,
                'created_at' => str_replace('T', ' ', (string) DisplayTime::toInput($l->created_at)),
            ]);

        return Inertia::render('Messages/Index', [
            'logs' => $logs,
            'options' => [
                'statuses' => array_map(fn (MessageStatus $s) => ['value' => $s->value, 'label' => $s->label()], MessageStatus::cases()),
                'channels' => array_map(fn (MessageChannelType $c) => ['value' => $c->value, 'label' => $c->label()], MessageChannelType::cases()),
            ],
        ]);
    }

    /** What a bulk send would do, without sending: counts and a rendered sample. */
    public function preview(Request $request, SendMessages $send): JsonResponse
    {
        [$template, $query] = $this->target($request);
        $plan = $send->plan($template, $query, $request->user());

        return response()->json([
            'total' => $plan['total'],
            'recipients' => count($plan['recipients']),
            'no_consent' => $plan['no_consent'],
            'no_contact' => $plan['no_contact'],
            'sample' => $plan['sample'],
        ]);
    }

    public function send(Request $request, SendMessages $send): JsonResponse
    {
        [$template, $query] = $this->target($request);

        return response()->json($send->handle($template, $query, $request->user()));
    }

    /** @return array{0: MessageTemplate, 1: Builder<Applicant>} */
    private function target(Request $request): array
    {
        abort_unless($request->user()->can('message.send'), 403);
        $data = $request->validate(['template_id' => ['required', 'integer'], 'filter' => ['nullable', 'array']]);
        $template = MessageTemplate::query()->findOrFail($data['template_id']);

        $listRequest = Request::create('/applicants', 'GET', ['filter' => $data['filter'] ?? []]);
        $listRequest->setUserResolver(fn () => $request->user());
        $query = ListQuery::for(Applicant::query()->whereNull('merged_into_id'), $listRequest)->filters(...ApplicantFilters::all())->query();

        return [$template, $query];
    }
}
