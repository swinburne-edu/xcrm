<?php

namespace App\Http\Controllers\HighSchool;

use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\HighSchool\Actions\CompleteSchoolActivity;
use App\Domain\HighSchool\Actions\SaveSchoolActivity;
use App\Domain\HighSchool\Enums\SchoolActivityStatus;
use App\Domain\HighSchool\Enums\SchoolActivityType;
use App\Domain\HighSchool\Models\ActivityAttendee;
use App\Domain\HighSchool\Models\SchoolActivity;
use App\Domain\HighSchool\Services\CheckinQr;
use App\Domain\Shared\Services\SensitiveData;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\HighSchool\SchoolActivityRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Activities with high schools (task 2.1, M3): a table and a month calendar for the selected cycle, the detail page
 * with the QR code and the students who checked in, and the report after the activity.
 * Authorization: SchoolActivityPolicy (school_activity.* + data scope + closed cycle read-only).
 */
class SchoolActivityController extends Controller
{
    public function __construct(private readonly SensitiveData $sensitive) {}

    public function index(Request $request, CurrentCycle $currentCycle): Response
    {
        $this->authorize('viewAny', SchoolActivity::class);

        $cycle = $currentCycle->get();
        $base = fn () => SchoolActivity::query()->with(['highSchool:id,name', 'staff:id,name'])->withCount('attendees')->where('cycle_id', $cycle?->id ?? 0);

        $list = ListQuery::for($base(), $request)
            ->filters(
                Filter::search('q', ['title', 'location']),
                Filter::exact('type'),
                Filter::exact('status'),
                Filter::exact('high_school_id'),
                Filter::dateRange('start_at'),
            )
            ->sorts(['start_at', 'title', 'status', 'created_at'])
            ->defaultSort('-start_at')
            ->toProps(fn (SchoolActivity $a) => $this->row($a));

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month')) === 1 ? (string) $request->query('month') : now(config('app.display_timezone'))->format('Y-m');
        $from = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.display_timezone'))->startOfMonth();

        return Inertia::render('SchoolActivities/Index', [
            'cycle' => $cycle ? ['id' => $cycle->id, 'name' => $cycle->name, 'closed' => $cycle->isClosed()] : null,
            'activities' => $list,
            'view' => $request->query('view') === 'calendar' ? 'calendar' : 'table',
            'calendar' => [
                'month' => $month,
                'items' => $cycle === null ? [] : $base()->whereBetween('start_at', [$from->utc(), $from->endOfMonth()->utc()])->orderBy('start_at')->get()->map(fn (SchoolActivity $a) => $this->row($a))->all(),
            ],
            'options' => $this->options(),
            'can' => ['create' => $request->user()->can('create', SchoolActivity::class) && $cycle !== null && ! $cycle->isClosed()],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $activity = SchoolActivity::query()->with(['highSchool:id,name', 'campaign:id,name', 'staff:id,name'])->withCount('attendees')->findOrFail($id);
        $this->authorize('view', $activity);

        $user = $request->user();

        return Inertia::render('SchoolActivities/Show', [
            'activity' => $this->row($activity) + [
                'cycle_id' => $activity->cycle_id,
                'high_school_id' => $activity->high_school_id,
                'campaign_id' => $activity->campaign_id,
                'campaign_name' => $activity->campaign?->name,
                'start_input' => DisplayTime::toInput($activity->start_at),
                'end_input' => DisplayTime::toInput($activity->end_at),
                'actual_attendees' => $activity->actual_attendees,
                'cost' => $activity->cost === null ? null : (int) $activity->cost,
                'result_note' => $activity->result_note,
                'checkin_url' => $activity->checkinUrl(),
                'staff_ids' => $activity->staff->pluck('id')->all(),
                'lead_id' => $activity->staff->firstWhere('pivot.role', 'lead')?->id,
                'staff_items' => $activity->staff->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])->all(),
            ],
            'attendees' => ActivityAttendee::query()->with('applicant:id,uuid,code,full_name,phone,class_name')->where('school_activity_id', $activity->id)->orderByDesc('checked_in_at')->limit(500)->get()->map(fn (ActivityAttendee $t) => [
                'id' => $t->id,
                'uuid' => $t->applicant?->uuid,
                'code' => $t->applicant?->code,
                'full_name' => $t->applicant?->full_name,
                'phone' => $this->sensitive->phone($t->applicant?->phone, $user),
                'class_name' => $t->applicant?->class_name ?? ($t->form_data['class_name'] ?? null),
                'checked_in_at' => $t->checked_in_at?->toIso8601String(),
            ])->all(),
            'options' => $this->options(),
            'can' => ['update' => $user->can('update', $activity), 'delete' => $user->can('delete', $activity)],
        ]);
    }

    public function store(SchoolActivityRequest $request, CurrentCycle $currentCycle, SaveSchoolActivity $save): RedirectResponse
    {
        $this->authorize('create', SchoolActivity::class);

        $activity = $save->handle(null, $request->validated() + ['cycle_id' => $currentCycle->id()], $request->user());

        return $this->toast("Đã thêm hoạt động {$activity->title}.", "/school-activities/{$activity->id}");
    }

    public function update(SchoolActivityRequest $request, int $id, SaveSchoolActivity $save): RedirectResponse
    {
        $activity = SchoolActivity::query()->findOrFail($id);
        $this->authorize('update', $activity);

        $save->handle($activity, $request->validated() + ['cycle_id' => $activity->cycle_id], $request->user());

        return $this->toast("Đã cập nhật hoạt động {$activity->title}.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $activity = SchoolActivity::query()->findOrFail($id);
        $this->authorize('delete', $activity);
        $activity->delete();

        return $this->toast("Đã xoá hoạt động {$activity->title}.", '/school-activities');
    }

    public function complete(Request $request, int $id, CompleteSchoolActivity $complete): RedirectResponse
    {
        $activity = SchoolActivity::query()->findOrFail($id);
        $this->authorize('update', $activity);

        $data = $request->validate([
            'actual_attendees' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'result_note' => ['nullable', 'string', 'max:5000'],
        ], [], ['actual_attendees' => 'số tham dự thực tế', 'cost' => 'chi phí', 'result_note' => 'ghi chú kết quả']);

        $complete->handle($activity, $data);

        return $this->toast('Đã lưu báo cáo sau hoạt động.');
    }

    /** The QR code (SVG) of the check-in link; ?download=1 offers it as a file. */
    public function qr(Request $request, int $id, CheckinQr $qr): HttpResponse
    {
        $activity = SchoolActivity::query()->findOrFail($id);
        $this->authorize('view', $activity);

        return response($qr->svg($activity, 480), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
            ...($request->boolean('download') ? ['Content-Disposition' => 'attachment; filename="checkin-qr-'.$activity->id.'.svg"'] : []),
        ]);
    }

    /** @return array<string, mixed> */
    private function row(SchoolActivity $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'type' => $a->type->value,
            'type_label' => $a->type->label(),
            'type_tone' => $a->type->tone(),
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'status_tone' => $a->status->tone(),
            'school' => $a->highSchool?->name,
            'location' => $a->location,
            'start_at' => $a->start_at?->toIso8601String(),
            'end_at' => $a->end_at?->toIso8601String(),
            'expected_attendees' => $a->expected_attendees,
            'checkins' => (int) ($a->attendees_count ?? 0),
            'staff' => $a->staff->pluck('name')->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'types' => array_map(fn (SchoolActivityType $t) => ['value' => $t->value, 'label' => $t->label()], SchoolActivityType::cases()),
            'statuses' => array_map(fn (SchoolActivityStatus $s) => ['value' => $s->value, 'label' => $s->label()], SchoolActivityStatus::cases()),
        ];
    }

    private function toast(string $message, ?string $to = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $to ? redirect($to) : back(fallback: '/school-activities');
    }
}
