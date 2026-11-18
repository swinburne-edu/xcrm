<?php

namespace App\Http\Requests\Activity;

use App\Domain\Activity\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /appointments/{id}/attendance: the outcome (attended / no show / cancelled) and the result notes.
 */
class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = Appointment::query()->find($this->route('id'));

        return $appointment !== null && (bool) $this->user()?->can('update', $appointment);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['attendance' => ['required', Rule::in(['attended', 'no_show', 'cancelled'])], 'result' => ['nullable', 'string', 'max:5000']];
    }
}
