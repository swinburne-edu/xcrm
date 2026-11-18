<?php

namespace App\Http\Controllers\Activity;

use App\Domain\Activity\Actions\SaveAppointment;
use App\Domain\Activity\Actions\SetAttendance;
use App\Domain\Activity\Enums\AppointmentType;
use App\Domain\Activity\Enums\Attendance;
use App\Domain\Activity\Models\Appointment;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\AppointmentRequest;
use App\Http\Requests\Activity\AttendanceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /appointments: counseling / campus tour / interview / exam appointments of the cases the user may see
 * (the case's data scope decides), book / change / record attendance. Rules live in the Activity actions.
 */
class AppointmentController extends Controller
{
    public const VIEWS = ['upcoming', 'past', 'all'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Appointment::class);

        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'upcoming';
        $startOfToday = CarbonImmutable::now(config('app.display_timezone'))->startOfDay()->utc();

        $query = Appointment::query()
            ->whereIn('case_id', AdmissionCase::query()->select('admission_cases.id'))
            ->with(['host:id,name', 'case.applicant:id,uuid,full_name']);

        match ($view) {
            'upcoming' => $query->where('start_at', '>=', $startOfToday)->where('attendance', 'scheduled'),
            'past' => $query->where(fn (Builder $p) => $p->where('start_at', '<', $startOfToday)->orWhere('attendance', '!=', 'scheduled')),
            default => null,
        };

        $list = ListQuery::for($query, $request)
            ->filters(
                Filter::exact('type'),
                Filter::exact('attendance'),
                Filter::exact('host_id'),
                Filter::dateRange('start', 'start_at'),
            )
            ->sorts(['start_at', 'type', 'attendance'])
            ->defaultSort($view === 'past' ? '-start_at' : 'start_at');

        $user = $request->user();

        return Inertia::render('Appointments/Index', [
            'appointments' => $list->toProps(fn (Appointment $a) => $this->row($a)),
            'view' => $view,
            'options' => [
                'types' => collect(AppointmentType::cases())->map(fn (AppointmentType $t) => ['value' => $t->value, 'label' => $t->label()])->all(),
                'attendances' => collect(Attendance::cases())->map(fn (Attendance $t) => ['value' => $t->value, 'label' => $t->label()])->all(),
            ],
            'can' => ['create' => $user->can('create', Appointment::class), 'update' => $user->can('appointment.update')],
        ]);
    }

    public function store(AppointmentRequest $request, SaveAppointment $save): RedirectResponse
    {
        $case = AdmissionCase::query()->findOrFail($request->integer('case_id'));
        $save->handle($case, null, $request->validated(), $request->user());

        return $this->toast('Đã đặt lịch hẹn.');
    }

    public function update(AppointmentRequest $request, int $id, SaveAppointment $save): RedirectResponse
    {
        $appointment = Appointment::query()->findOrFail($id);
        $save->handle($appointment->case()->firstOrFail(), $appointment, $request->validated(), $request->user());

        return $this->toast('Đã cập nhật lịch hẹn.');
    }

    public function attendance(AttendanceRequest $request, int $id, SetAttendance $set): RedirectResponse
    {
        $set->handle(Appointment::query()->findOrFail($id), Attendance::from($request->input('attendance')), $request->input('result'), $request->user());

        return $this->toast('Đã ghi nhận kết quả lịch hẹn.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $appointment = Appointment::query()->findOrFail($id);
        $this->authorize('delete', $appointment);
        abort_unless($appointment->attendance === Attendance::Scheduled, 422, 'Chỉ xoá được lịch hẹn chưa ghi nhận kết quả.');
        $appointment->delete();

        return $this->toast('Đã xoá lịch hẹn.');
    }

    /** @return array<string, mixed> */
    private function row(Appointment $a): array
    {
        $applicant = $a->case?->applicant;

        return [
            'id' => $a->id,
            'case_id' => $a->case_id,
            'applicant_name' => $applicant?->full_name,
            'applicant_href' => $applicant ? "/applicants/{$applicant->uuid}" : null,
            'type' => $a->type->value,
            'type_label' => $a->type->label(),
            'start_at' => $a->start_at->toIso8601String(),
            'start_input' => DisplayTime::toInput($a->start_at),
            'end_input' => DisplayTime::toInput($a->end_at),
            'location' => $a->location,
            'host_id' => $a->host_id,
            'host_name' => $a->host?->name,
            'attendance' => $a->attendance->value,
            'attendance_label' => $a->attendance->label(),
            'result' => $a->result,
        ];
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/appointments');
    }
}
