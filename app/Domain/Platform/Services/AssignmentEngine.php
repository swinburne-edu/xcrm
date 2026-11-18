<?php

namespace App\Domain\Platform\Services;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\Province;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Services\TerritoryResolver;
use App\Domain\Platform\Enums\AssignmentStrategy;
use App\Domain\Platform\Models\AssignmentRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Decides who a new case goes to (docs/03 M8). Active rules are tried in ascending priority; the first rule whose
 * conditions match the case AND whose strategy finds someone wins. A rule that matches but finds nobody (no territory,
 * no active user) is skipped, so a later rule or, finally, the caller's default (territory of the school / province,
 * then the shared pool) still applies. Returns null when no rule decides.
 *
 * Strategies:
 *   territory     the owner / team of the territory of the applicant's school (else province)
 *   fixed         the first active user of the list
 *   round_robin   among the candidates, whoever was given a case longest ago (never given = first)
 *   load_balance  among the candidates, whoever has the fewest open cases (ties: longest ago)
 * Candidates: the rule's user_ids, or the members of its team when no users are listed; only active users of the organization.
 */
class AssignmentEngine
{
    public function __construct(private readonly TerritoryResolver $territories) {}

    /**
     * @param  array<string, mixed>  $data  case data of CreateAdmissionCase: source_id, campaign_id, primary_major_id…
     * @return array{0: int|null, 1: int|null}|null owner_id and team_id (a team without a user = the team's pool)
     */
    public function resolve(Applicant $applicant, array $data, int $organizationId): ?array
    {
        $rules = AssignmentRule::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->where('is_active', true)->whereNull('deleted_at')->orderBy('priority')->orderBy('id')->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $context = $this->context($applicant, $data);

        foreach ($rules as $rule) {
            if (! $this->matches($rule, $context)) {
                continue;
            }

            $picked = $this->pick($rule, $applicant, $organizationId);

            if ($picked !== null) {
                return $picked;
            }
        }

        return null;
    }

    /**
     * What the conditions are compared with.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    private function context(Applicant $applicant, array $data): array
    {
        $school = $applicant->high_school_id === null ? null : HighSchool::query()->withoutGlobalScopes()->find($applicant->high_school_id);
        $majors = array_filter([$data['primary_major_id'] ?? null, ...array_values((array) ($applicant->interests ?? []))], fn ($v) => is_int($v) || (is_string($v) && ctype_digit($v)));
        $one = fn (mixed $v): array => $v === null || $v === '' ? [] : [(string) $v];

        return [
            'source_ids' => $one($data['source_id'] ?? $applicant->first_source_id),
            'campaign_ids' => $one($data['campaign_id'] ?? $applicant->first_campaign_id),
            'province_ids' => $one($applicant->province_id ?? $school?->province_id),
            'high_school_ids' => $one($applicant->high_school_id),
            'major_ids' => array_map('strval', array_values($majors)),
            'school_tiers' => $one($school?->tier?->value),
        ];
    }

    /** @param  array<string, list<string>>  $context */
    private function matches(AssignmentRule $rule, array $context): bool
    {
        foreach (AssignmentRule::CONDITION_KEYS as $key) {
            $wanted = array_map('strval', (array) ($rule->conditions[$key] ?? []));

            if ($wanted !== [] && array_intersect($wanted, $context[$key] ?? []) === []) {
                return false;
            }
        }

        return true;
    }

    /** @return array{0: int|null, 1: int|null}|null */
    private function pick(AssignmentRule $rule, Applicant $applicant, int $organizationId): ?array
    {
        if ($rule->strategy === AssignmentStrategy::Territory) {
            return $this->territory($applicant, $organizationId);
        }

        $candidates = $this->candidates($rule, $organizationId);

        if ($candidates === []) {
            return null;
        }

        $userId = match ($rule->strategy) {
            AssignmentStrategy::Fixed => $candidates[0],
            AssignmentStrategy::RoundRobin => $this->leastRecent($candidates),
            AssignmentStrategy::LoadBalance => $this->leastLoaded($candidates),
            default => null,
        };

        if ($userId === null) {
            return null;
        }

        return [$userId, $rule->team_id ?? User::query()->find($userId)?->teams()->value('teams.id')];
    }

    /** @return array{0: int|null, 1: int|null}|null */
    private function territory(Applicant $applicant, int $organizationId): ?array
    {
        $target = $applicant->high_school_id !== null
            ? HighSchool::query()->withoutGlobalScopes()->find($applicant->high_school_id)
            : ($applicant->province_id !== null ? Province::query()->find($applicant->province_id) : null);

        $found = $target instanceof Model ? $this->territories->ownerFor($target, $organizationId) : null;

        if ($found === null || ($found->userId === null && $found->teamId === null)) {
            return null;
        }

        return [$found->userId, $found->teamId ?? ($found->userId === null ? null : User::query()->find($found->userId)?->teams()->value('teams.id'))];
    }

    /**
     * Active users of the organization, in the order the rule lists them (team members by id when none are listed).
     *
     * @return list<int>
     */
    private function candidates(AssignmentRule $rule, int $organizationId): array
    {
        $listed = array_values(array_unique(array_map('intval', (array) $rule->user_ids)));

        if ($listed === [] && $rule->team_id !== null) {
            $listed = DB::table('team_user')->where('team_id', $rule->team_id)->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        }

        if ($listed === []) {
            return [];
        }

        $active = User::query()->where('organization_id', $organizationId)->where('is_active', true)->whereIn('id', $listed)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_filter($listed, fn (int $id) => in_array($id, $active, true)));
    }

    /** @param  list<int>  $candidates */
    private function leastRecent(array $candidates): int
    {
        $last = $this->stats($candidates)['last'];
        usort($candidates, fn (int $a, int $b) => [$last[$a] ?? '', $a] <=> [$last[$b] ?? '', $b]);

        return $candidates[0];
    }

    /** @param  list<int>  $candidates */
    private function leastLoaded(array $candidates): int
    {
        ['last' => $last, 'open' => $open] = $this->stats($candidates);
        usort($candidates, fn (int $a, int $b) => [$open[$a] ?? 0, $last[$a] ?? '', $a] <=> [$open[$b] ?? 0, $last[$b] ?? '', $b]);

        return $candidates[0];
    }

    /**
     * @param  list<int>  $candidates
     * @return array{last: array<int, string>, open: array<int, int>} latest assigned_at and open-case count per user
     */
    private function stats(array $candidates): array
    {
        $rows = AdmissionCase::query()->withoutGlobalScopes()->whereNull('deleted_at')->whereIn('owner_id', $candidates)
            ->selectRaw("owner_id, max(assigned_at) as last_assigned, sum(case when status = 'open' then 1 else 0 end) as open_count")
            ->groupBy('owner_id')->toBase()->get();

        return [
            'last' => $rows->mapWithKeys(fn ($r) => [(int) $r->owner_id => (string) $r->last_assigned])->all(),
            'open' => $rows->mapWithKeys(fn ($r) => [(int) $r->owner_id => (int) $r->open_count])->all(),
        ];
    }
}
