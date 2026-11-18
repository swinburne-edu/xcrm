<?php

namespace App\Http\Requests\Activity;

use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Services\TimelineSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /timeline: a note, call, message or meeting typed on a record. The subject is checked
 * (and authorized) by TimelineController through its data scope.
 */
class TimelineEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $manual = array_map(fn (ActivityType $t) => $t->value, array_filter(ActivityType::cases(), fn (ActivityType $t) => $t->manual()));

        return [
            'subject_type' => ['required', Rule::in(array_keys(TimelineSubject::TYPES))],
            'subject' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::in($manual)],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required_unless:type,call', 'nullable', 'string', 'max:10000'],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
            'call_result_id' => ['nullable', 'integer'],
            'duration_sec' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'callback_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ];
    }

    public function messages(): array
    {
        return ['body.required_unless' => 'Nhập nội dung.'];
    }
}
