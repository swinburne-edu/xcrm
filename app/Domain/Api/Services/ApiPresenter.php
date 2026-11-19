<?php

namespace App\Domain\Api\Services;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Shared\Services\SensitiveData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The shape of every record the REST API returns. Fields are fixed (not the database columns) so the API stays stable;
 * the phone follows the same masking as the screens: without applicant.view_sensitive the number is hidden. The citizen
 * id is never returned.
 */
class ApiPresenter
{
    public function __construct(private readonly SensitiveData $sensitive) {}

    /** @return array<string, mixed> */
    public function applicant(Applicant $a, User $user): array
    {
        return [
            'uuid' => $a->uuid,
            'code' => $a->code,
            'full_name' => $a->full_name,
            'gender' => $a->gender?->value ?? $a->gender,
            'dob' => $a->dob?->format('Y-m-d'),
            'phone' => $this->sensitive->phone($a->phone, $user),
            'email' => $a->email,
            'province' => $a->province === null ? null : ['id' => $a->province_id, 'name' => $a->province->name],
            'high_school' => $a->highSchool === null ? null : ['id' => $a->high_school_id, 'name' => $a->highSchool->name],
            'graduation_year' => $a->graduation_year,
            'consent_marketing' => (bool) $a->consent_marketing,
            'created_at' => $a->created_at?->toIso8601String(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function case(AdmissionCase $c): array
    {
        return [
            'uuid' => $c->uuid,
            'applicant_uuid' => $c->applicant?->uuid,
            'cycle' => $c->cycle === null ? null : ['id' => $c->cycle_id, 'name' => $c->cycle->name],
            'stage' => $c->stage === null ? null : ['id' => $c->stage_id, 'code' => $c->stage->code, 'name' => $c->stage->name],
            'status' => $c->status->value,
            'owner' => $c->owner === null ? null : ['id' => $c->owner_id, 'name' => $c->owner->name],
            'source_id' => $c->source_id,
            'campaign_id' => $c->campaign_id,
            'primary_major_id' => $c->primary_major_id,
            'temperature' => $c->temperature?->value,
            'score' => $c->score,
            'last_activity_at' => $c->last_activity_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function application(Application $a): array
    {
        return [
            'id' => $a->id,
            'case_uuid' => $a->case?->uuid,
            'preference_order' => $a->preference_order,
            'round' => $a->round === null ? null : ['id' => $a->round_id, 'name' => $a->round->name],
            'major' => $a->major === null ? null : ['id' => $a->major_id, 'moet_code' => $a->major->moet_code, 'admission_code' => $a->major->admission_code, 'name' => $a->major->name],
            'method' => $a->cycleMethod === null ? null : ['id' => $a->cycle_method_id, 'name' => $a->cycleMethod->display_name ?: $a->cycleMethod->method?->name],
            'combination' => $a->combination?->code,
            'raw_score' => $a->raw_score === null ? null : (float) $a->raw_score,
            'final_score' => $a->final_score === null ? null : (float) $a->final_score,
            'status' => $a->status->value,
            'submitted_at' => $a->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * @param  callable(mixed): array<string, mixed>  $map
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function page(LengthAwarePaginator $page, callable $map): array
    {
        return [
            'data' => $page->getCollection()->map($map)->values()->all(),
            'meta' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ];
    }
}
