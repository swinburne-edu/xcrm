<?php

namespace App\Domain\Activity\Actions;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Enums\Attendance;
use App\Domain\Activity\Models\Appointment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Records the outcome of an appointment: attended, no show or cancelled (with the result notes) and
 * writes it on the case timeline. A recorded appointment is final.
 */
class SetAttendance
{
    public function __construct(private readonly RecordActivity $record) {}

    public function handle(Appointment $appointment, Attendance $attendance, ?string $result, User $by): Appointment
    {
        if ($appointment->attendance !== Attendance::Scheduled) {
            throw ValidationException::withMessages(['attendance' => 'Lịch hẹn đã được ghi nhận kết quả.']);
        }

        if ($attendance === Attendance::Scheduled) {
            throw ValidationException::withMessages(['attendance' => 'Chọn có đến, vắng hoặc huỷ.']);
        }

        if ($appointment->case()->first()?->cycle()->first()?->isClosed()) {
            throw ValidationException::withMessages(['attendance' => 'Mùa tuyển sinh đã đóng.']);
        }

        $appointment->attendance = $attendance;
        $appointment->result = $result !== null && trim($result) !== '' ? trim($result) : $appointment->result;
        $appointment->save();

        $case = $appointment->case()->first();

        if ($case !== null) {
            $this->record->handle($case, ActivityType::Meeting, [
                'title' => "Lịch hẹn {$appointment->type->label()}: {$attendance->label()}",
                'body' => $appointment->result,
                'meta' => ['appointment_id' => $appointment->id, 'attendance' => $attendance->value],
            ], $by);
        }

        return $appointment;
    }
}
