<?php

namespace App\Domain\Admission\Actions;

use App\Domain\Admission\Enums\StageType;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\CaseStageHistory;
use App\Domain\Admission\Models\CaseTouch;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\Province;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Services\TerritoryResolver;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens the admission case of an applicant in a cycle.
 *  - R5: one case per applicant per cycle (also enforced by a unique index).
 *  - R8: not in a closed cycle.
 *  - Starts at the first open stage of the organization's default pipeline for the cycle's level.
 *  - Owner: the one given, else the owner of the territory of the applicant's school / province (rule 17),
 *    else nobody (the shared pool).
 *  - Writes the first stage history row and the first touch.
 */
class CreateAdmissionCase
{
    public function __construct(private readonly TerritoryResolver $territories) {}

    /**
     * @param  array<string, mixed>  $data  optional: owner_id (null = pool), team_id, source_id, campaign_id, school_activity_id, form_id, utm_*, primary_major_id, temperature, payload
     */
    public function handle(Applicant $applicant, AdmissionCycle $cycle, array $data = [], ?User $by = null): AdmissionCase
    {
        if ($cycle->isClosed()) {
            throw ValidationException::withMessages(['cycle_id' => 'Mùa tuyển sinh đã đóng, không thể tạo hồ sơ.']);
        }

        if (AdmissionCase::query()->withoutGlobalScopes()->withTrashed()->where('applicant_id', $applicant->id)->where('cycle_id', $cycle->id)->exists()) {
            throw ValidationException::withMessages(['applicant_id' => 'Thí sinh đã có hồ sơ trong mùa tuyển sinh này.']);
        }

        $pipeline = $this->pipelineFor($cycle, $applicant->organization_id);
        $stage = $pipeline->stages()->where('type', StageType::Open->value)->first()
            ?? throw ValidationException::withMessages(['pipeline_id' => 'Pipeline chưa có giai đoạn đang xử lý nào.']);

        [$ownerId, $teamId] = $this->owner($applicant, $data);
        $now = now();

        try {
            return DB::transaction(function () use ($applicant, $cycle, $pipeline, $stage, $ownerId, $teamId, $data, $by, $now): AdmissionCase {
                $case = AdmissionCase::query()->create([
                    'organization_id' => $applicant->organization_id,
                    'applicant_id' => $applicant->id,
                    'cycle_id' => $cycle->id,
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $stage->id,
                    'owner_id' => $ownerId,
                    'team_id' => $teamId,
                    'source_id' => $data['source_id'] ?? $applicant->first_source_id,
                    'campaign_id' => $data['campaign_id'] ?? $applicant->first_campaign_id,
                    'school_activity_id' => $data['school_activity_id'] ?? null,
                    'utm_source' => $data['utm_source'] ?? null,
                    'utm_medium' => $data['utm_medium'] ?? null,
                    'utm_campaign' => $data['utm_campaign'] ?? null,
                    'utm_content' => $data['utm_content'] ?? null,
                    'primary_major_id' => $data['primary_major_id'] ?? null,
                    'temperature' => $data['temperature'] ?? null,
                    'assigned_at' => $ownerId === null ? null : $now,
                    'last_activity_at' => $now,
                ]);

                CaseStageHistory::query()->create(['case_id' => $case->id, 'from_stage_id' => null, 'to_stage_id' => $stage->id, 'user_id' => $by?->id, 'changed_at' => $now]);
                CaseTouch::query()->create([
                    'case_id' => $case->id,
                    'source_id' => $case->source_id,
                    'campaign_id' => $case->campaign_id,
                    'school_activity_id' => $case->school_activity_id,
                    'form_id' => $data['form_id'] ?? null,
                    'utm' => array_filter(['source' => $case->utm_source, 'medium' => $case->utm_medium, 'campaign' => $case->utm_campaign, 'content' => $case->utm_content]) ?: null,
                    'payload' => $data['payload'] ?? null,
                    'touched_at' => $now,
                ]);

                return $case->refresh();
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE')) {
                throw ValidationException::withMessages(['applicant_id' => 'Thí sinh đã có hồ sơ trong mùa tuyển sinh này.']);
            }

            throw $e;
        }
    }

    private function pipelineFor(AdmissionCycle $cycle, int $organizationId): Pipeline
    {
        $own = Pipeline::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('is_default', true);

        return (clone $own)->where('level', $cycle->level->value)->first()
            ?? $own->first()
            ?? throw ValidationException::withMessages(['pipeline_id' => 'Chưa có pipeline mặc định. Tạo ở Cài đặt → Pipeline.']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int|null, 1: int|null} owner_id, team_id
     */
    private function owner(Applicant $applicant, array $data): array
    {
        if (array_key_exists('owner_id', $data)) {
            $ownerId = $data['owner_id'];
            $teamId = $data['team_id'] ?? ($ownerId === null ? null : User::query()->find($ownerId)?->teams()->value('teams.id'));

            return [$ownerId, $teamId];
        }

        $target = $applicant->high_school_id !== null
            ? HighSchool::query()->withoutGlobalScopes()->find($applicant->high_school_id)
            : ($applicant->province_id !== null ? Province::query()->find($applicant->province_id) : null);

        $found = $target instanceof Model ? $this->territories->ownerFor($target, $applicant->organization_id) : null;

        if ($found === null) {
            return [null, null];
        }

        return [$found->userId, $found->teamId ?? ($found->userId === null ? null : User::query()->find($found->userId)?->teams()->value('teams.id'))];
    }
}
