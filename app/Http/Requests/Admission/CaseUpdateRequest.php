<?php

namespace App\Http\Requests\Admission;

/**
 * PUT /cases/{uuid}: temperature, primary major, next action (Y-m-d\TH:i, Vietnam time), source, campaign.
 * Only the fields sent are changed.
 */
class CaseUpdateRequest extends CaseRequests
{
    public function authorize(): bool
    {
        $case = $this->routeCase();

        return $case !== null && (bool) $this->user()?->can('update', $case);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->commonRules() + ['next_action_at' => ['nullable', 'date_format:Y-m-d\TH:i']];
    }
}
