<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Shared\Support\DisplayTime;

/**
 * Edits the day-to-day fields of a case from the quick-view drawer: temperature, primary major,
 * next action time (entered in Vietnam time), source and campaign. Stage and owner have their own actions.
 */
class UpdateAdmissionCase
{
    /**
     * @param  array<string, mixed>  $data  validated by CaseUpdateRequest; only the keys present are changed
     */
    public function handle(AdmissionCase $case, array $data): AdmissionCase
    {
        foreach (['temperature', 'primary_major_id', 'source_id', 'campaign_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $case->{$field} = $data[$field];
            }
        }

        if (array_key_exists('next_action_at', $data)) {
            $case->next_action_at = DisplayTime::toUtc($data['next_action_at']);
        }

        $case->last_activity_at = now();
        $case->save();

        return $case;
    }
}
