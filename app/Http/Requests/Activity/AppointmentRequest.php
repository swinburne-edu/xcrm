<?php

namespace App\Http\Requests\Activity;

use App\Domain\Activity\Enums\AppointmentType;
use App\Domain\Activity\Models\Appointment;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Book / change an appointment. Times are Vietnam time (Y-m-d\TH:i). The case is chosen when booking and
 * must be one the user may update (data scope, closed-cycle rule); changing needs the appointment's update right.
 */
class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($this->route('id') === null) {
            $case = AdmissionCase::query()->with('cycle')->find($this->input('case_id'));

            return (bool) $user?->can('create', Appointment::class) && $case !== null && (bool) $user->can('update', $case);
        }

        $appointment = Appointment::query()->find($this->route('id'));

        return $appointment !== null && (bool) $user?->can('update', $appointment);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('id') === null;

        return [
            'case_id' => [$creating ? 'required' : 'prohibited', 'integer'],
            'type' => ['required', Rule::enum(AppointmentType::class)],
            'start_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'host_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', app(CurrentOrganization::class)->id())->where('is_active', true)],
        ];
    }

    public function attributes(): array
    {
        return ['case_id' => 'hồ sơ', 'type' => 'loại', 'start_at' => 'giờ bắt đầu', 'end_at' => 'giờ kết thúc', 'location' => 'địa điểm', 'host_id' => 'người phụ trách'];
    }
}
