<?php

namespace App\Domain\Activity\Actions;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Enums\Attendance;
use App\Domain\Activity\Models\Appointment;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Shared\Support\DisplayTime;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Book or change an appointment of a case (times in Vietnam time, Y-m-d\TH:i). Booking is noted on the
 * case timeline. Appointments cannot be changed once attendance is recorded (attended / no show), nor in a closed cycle.
 */
class SaveAppointment
{
    public function __construct(private readonly RecordActivity $record) {}

    /**
     * @param  array<string, mixed>  $data  type, start_at, end_at, location, host_id, result
     */
    public function handle(AdmissionCase $case, ?Appointment $appointment, array $data, User $by): Appointment
    {
        if ($case->cycle()->first()?->isClosed()) {
            throw ValidationException::withMessages(['case_id' => 'Mùa tuyển sinh đã đóng, không đặt lịch hẹn được.']);
        }

        if ($appointment !== null && in_array($appointment->attendance, [Attendance::Attended, Attendance::NoShow], true)) {
            throw ValidationException::withMessages(['attendance' => 'Lịch hẹn đã ghi nhận có đến/vắng, không sửa được.']);
        }

        $isNew = $appointment === null;
        $appointment ??= new Appointment(['organization_id' => $case->organization_id, 'case_id' => $case->id]);

        $appointment->fill([
            'type' => $data['type'],
            'start_at' => DisplayTime::toUtc($data['start_at']),
            'end_at' => DisplayTime::toUtc($data['end_at'] ?? null),
            'location' => $data['location'] ?? null,
            'host_id' => $data['host_id'] ?? ($isNew ? $by->id : $appointment->host_id),
        ])->save();

        $label = $appointment->type->label();
        $when = DisplayTime::toInput($appointment->start_at);

        $this->record->handle($case, ActivityType::Meeting, [
            'title' => ($isNew ? 'Đặt lịch hẹn' : 'Đổi lịch hẹn')." {$label}",
            'body' => str_replace('T', ' ', (string) $when).($appointment->location ? " · {$appointment->location}" : ''),
            'meta' => ['appointment_id' => $appointment->id],
        ], $by);

        return $appointment;
    }
}
