<?php

namespace App\Http\Controllers\Communication;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Shared\Services\SensitiveData;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Domain\Telephony\Actions\DialCall;
use App\Domain\Telephony\Enums\CallStatus;
use App\Domain\Telephony\Models\CallLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Call centre screens (task 3.7): the call log (call.view), click-to-call (call.dial) and the popup feed of calls that
 * are ringing for the current user.
 */
class CallController extends Controller
{
    public function __construct(private readonly SensitiveData $sensitive) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('call.view'), 403);
        $user = $request->user();

        $logs = ListQuery::for(CallLog::query()->with(['applicant:id,uuid,full_name', 'user:id,name']), $request)
            ->filters(Filter::exact('status'), Filter::exact('direction'), Filter::exact('user_id'))
            ->sorts(['created_at', 'duration_sec'])
            ->defaultSort('-created_at')
            ->toProps(fn (CallLog $c) => [
                'id' => $c->id,
                'direction' => $c->direction,
                'number' => $this->sensitive->phone($c->customerNumber(), $user),
                'applicant_uuid' => $c->applicant?->uuid,
                'applicant_name' => $c->applicant?->full_name,
                'user_name' => $c->user?->name,
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'duration_sec' => $c->duration_sec,
                'recording_url' => $c->recording_url,
                'error' => $c->error,
                'created_at' => str_replace('T', ' ', (string) DisplayTime::toInput($c->created_at)),
            ]);

        return Inertia::render('Calls/Index', [
            'calls' => $logs,
            'options' => [
                'statuses' => array_map(fn (CallStatus $s) => ['value' => $s->value, 'label' => $s->label()], CallStatus::cases()),
                'directions' => [['value' => 'in', 'label' => 'Gọi đến'], ['value' => 'out', 'label' => 'Gọi đi']],
            ],
        ]);
    }

    /** Click-to-call for one applicant of the list or detail page. */
    public function dial(Request $request, DialCall $dial): JsonResponse
    {
        abort_unless($request->user()->can('call.dial'), 403);
        $data = $request->validate(['applicant_uuid' => ['required', 'uuid']]);
        $applicant = Applicant::query()->where('uuid', $data['applicant_uuid'])->firstOrFail();
        $this->authorize('view', $applicant);

        $log = $dial->handle($request->user(), $applicant);

        return response()->json(['status' => $log->status->value, 'status_label' => $log->status->label(), 'error' => $log->error], $log->error === null ? 200 : 502);
    }

    /** Inbound calls ringing right now for this user, polled by the popup every few seconds. */
    public function incoming(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('call.view'), 403);
        $user = $request->user();

        $calls = CallLog::query()->with(['applicant' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'uuid', 'full_name')])
            ->where('direction', 'in')->where('status', CallStatus::Ringing->value)
            ->where('created_at', '>=', now()->subSeconds((int) config('telephony.popup_seconds')))
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere(fn ($q2) => $q2->whereNull('user_id')->whereHas('applicant')))
            ->latest('id')->limit(5)->get();

        return response()->json(['calls' => $calls->map(fn (CallLog $c) => [
            'id' => $c->id,
            'number' => $this->sensitive->phone($c->customerNumber(), $user),
            'applicant_uuid' => $c->applicant?->uuid,
            'applicant_name' => $c->applicant?->full_name,
        ])->all()]);
    }
}
