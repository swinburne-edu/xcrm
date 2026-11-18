<?php

namespace App\Http\Requests\Admission;

use App\Domain\Admission\Enums\Temperature;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Shared\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared base of the case forms: the organization-bound existence rules.
 */
abstract class CaseRequests extends FormRequest
{
    /** Resolved case of the route ({uuid}) through the data scope; null when outside it. */
    protected function routeCase(): ?AdmissionCase
    {
        return AdmissionCase::query()->with('cycle')->where('uuid', $this->route('uuid'))->first();
    }

    /** @return array<string, mixed> */
    protected function commonRules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();
        $inOrganization = fn (string $table) => Rule::exists($table, 'id')->where('organization_id', $organizationId);

        return [
            'temperature' => ['nullable', Rule::enum(Temperature::class)],
            'primary_major_id' => ['nullable', 'integer', $inOrganization('majors')],
            'source_id' => ['nullable', 'integer', $inOrganization('lead_sources')],
            'campaign_id' => ['nullable', 'integer', $inOrganization('campaigns')],
        ];
    }

    public function attributes(): array
    {
        return [
            'applicant_id' => 'thí sinh', 'temperature' => 'mức độ quan tâm', 'primary_major_id' => 'ngành quan tâm chính',
            'source_id' => 'nguồn', 'campaign_id' => 'chiến dịch', 'owner_id' => 'người phụ trách', 'next_action_at' => 'hành động tiếp theo',
            'stage_id' => 'giai đoạn', 'lost_reason_id' => 'lý do thất bại',
        ];
    }
}
