<?php

namespace App\Domain\Major\Services;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Major\Models\QuotaSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Progress of every major against its quota in a cycle (docs/03 M4), counted over the whole organization
 * (the numbers are aggregates, so the viewer's data scope does not apply).
 *
 * Per major, each tier counts distinct cases:
 *   interested  has the major as primary / interested major or a nguyện vọng not withdrawn
 *   submitted   a nguyện vọng beyond draft (submitted, eligible, admitted, rejected…)
 *   eligible    passed the review (eligible, conditional, admitted, confirmed)
 *   admitted    trúng tuyển (admitted, confirmed)
 *   confirmed   xác nhận nhập học
 *   enrolled    case in a "won" stage with this enrolled major
 *   secured     admitted ∪ confirmed ∪ enrolled: the places the school can count on
 *
 * Warning level of a major:
 *   over   secured > quota_total (admitted more than the quota)
 *   short  secured < target × time progress × SHORT_FACTOR, once the cycle is MIN_PROGRESS through its dates
 *          (target = target_enrolled, else quota_total; progress = elapsed share of start_date → end_date)
 *   ok     otherwise
 */
class QuotaProgress
{
    public const SHORT_FACTOR = 0.7;

    public const MIN_PROGRESS = 0.25;

    public const LEVELS = ['ok', 'short', 'over'];

    /**
     * Rows ordered by severity (over, short, ok) then by major name.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(AdmissionCycle $cycle): array
    {
        $quotas = MajorQuota::query()->with(['major:id,name,faculty_id', 'major.faculty:id,name'])->where('cycle_id', $cycle->id)->get();

        if ($quotas->isEmpty()) {
            return [];
        }

        $sets = $this->sets($cycle);
        $progress = $this->progress($cycle);
        $trend = $this->secured7DaysAgo($cycle);

        $rows = $quotas->map(function (MajorQuota $q) use ($sets, $progress, $trend): array {
            $id = $q->major_id;
            $count = fn (string $tier): int => count($sets[$tier][$id] ?? []);
            $secured = count(($sets['admitted'][$id] ?? []) + ($sets['enrolled'][$id] ?? []));
            $quota = (int) $q->quota_total;
            $target = (int) ($q->target_enrolled ?: $quota);
            [$level, $gap] = $this->level($secured, $quota, $target, $progress);

            return [
                'major_id' => $id,
                'major' => $q->major?->name ?? '—',
                'faculty' => $q->major?->faculty?->name,
                'quota' => $quota,
                'direct' => (int) $q->quota_direct,
                'target' => $target,
                'interested' => $count('interested'),
                'submitted' => $count('submitted'),
                'eligible' => $count('eligible'),
                'admitted' => $count('admitted'),
                'confirmed' => $count('confirmed'),
                'enrolled' => $count('enrolled'),
                'secured' => $secured,
                'fill' => $target > 0 ? round($secured / $target * 100, 1) : null,
                'level' => $level,
                'gap' => $gap,
                'change_7d' => isset($trend[$id]) ? $secured - $trend[$id] : null,
            ];
        })->all();

        usort($rows, fn (array $a, array $b) => [self::severity($b['level']), $a['major']] <=> [self::severity($a['level']), $b['major']]);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int|float|null>
     */
    public function summary(array $rows): array
    {
        $sum = fn (string $key): int => (int) array_sum(array_column($rows, $key));
        $target = $sum('target');

        return [
            'quota' => $sum('quota'),
            'target' => $target,
            'interested' => $sum('interested'),
            'submitted' => $sum('submitted'),
            'admitted' => $sum('admitted'),
            'confirmed' => $sum('confirmed'),
            'enrolled' => $sum('enrolled'),
            'secured' => $sum('secured'),
            'fill' => $target > 0 ? round($sum('secured') / $target * 100, 1) : null,
            'short' => count(array_filter($rows, fn (array $r) => $r['level'] === 'short')),
            'over' => count(array_filter($rows, fn (array $r) => $r['level'] === 'over')),
        ];
    }

    /** Share (0–1) of the cycle's dates that has passed; null when the cycle has no usable dates. */
    public function progress(AdmissionCycle $cycle): ?float
    {
        if ($cycle->isClosed()) {
            return 1.0;
        }

        if ($cycle->start_date === null || $cycle->end_date === null || $cycle->end_date->lte($cycle->start_date)) {
            return null;
        }

        $total = $cycle->start_date->diffInSeconds($cycle->end_date->endOfDay());
        $elapsed = $cycle->start_date->diffInSeconds(now(), false);

        return max(0.0, min(1.0, $elapsed / max(1, $total)));
    }

    /**
     * @return array{0: string, 1: int} the level and by how many places it is off (over: surplus, short: shortfall)
     */
    public function level(int $secured, int $quota, int $target, ?float $progress): array
    {
        if ($quota > 0 && $secured > $quota) {
            return ['over', $secured - $quota];
        }

        if ($progress !== null && $progress >= self::MIN_PROGRESS && $target > 0) {
            $expected = $target * $progress * self::SHORT_FACTOR;

            if ($secured < $expected) {
                return ['short', (int) ceil($expected - $secured)];
            }
        }

        return ['ok', 0];
    }

    public static function severity(string $level): int
    {
        return ['ok' => 0, 'short' => 1, 'over' => 2][$level] ?? 0;
    }

    /**
     * Distinct case ids per tier per major: $sets[tier][major_id][case_id] = true.
     *
     * @return array<string, array<int, array<int, true>>>
     */
    private function sets(AdmissionCycle $cycle): array
    {
        $cases = fn () => AdmissionCase::query()->withoutDataScope()->where('cycle_id', $cycle->id);
        $sets = ['interested' => [], 'submitted' => [], 'eligible' => [], 'admitted' => [], 'confirmed' => [], 'enrolled' => []];
        $add = function (string $tier, int $major, int $case) use (&$sets): void {
            $sets[$tier][$major][$case] = true;
        };

        foreach ($cases()->whereNotNull('primary_major_id')->toBase()->get(['id', 'primary_major_id']) as $row) {
            $add('interested', (int) $row->primary_major_id, (int) $row->id);
        }

        foreach (DB::table('case_interested_majors')->whereIn('case_id', $cases()->select('id'))->get(['case_id', 'major_id']) as $row) {
            $add('interested', (int) $row->major_id, (int) $row->case_id);
        }

        $beyondDraft = ['submitted', 'eligible', 'conditional_admit', 'admitted', 'confirmed', 'ineligible', 'rejected'];
        $tiers = [
            'eligible' => ['eligible', 'conditional_admit', 'admitted', 'confirmed'],
            'admitted' => ['admitted', 'confirmed'],
            'confirmed' => ['confirmed'],
        ];

        foreach (DB::table('applications')->whereIn('case_id', $cases()->select('id'))->where('status', '!=', 'withdrawn')->get(['case_id', 'major_id', 'status']) as $row) {
            [$case, $major] = [(int) $row->case_id, (int) $row->major_id];
            $add('interested', $major, $case);

            if (in_array($row->status, $beyondDraft, true)) {
                $add('submitted', $major, $case);
            }

            foreach ($tiers as $tier => $statuses) {
                in_array($row->status, $statuses, true) && $add($tier, $major, $case);
            }
        }

        $won = $cases()->join('pipeline_stages as s', 's.id', '=', 'admission_cases.stage_id')->where('s.type', 'won')->whereNotNull('admission_cases.enrolled_major_id')
            ->toBase()->get(['admission_cases.id as id', 'admission_cases.enrolled_major_id as major']);

        foreach ($won as $row) {
            $add('enrolled', (int) $row->major, (int) $row->id);
            $add('interested', (int) $row->major, (int) $row->id);
        }

        return $sets;
    }

    /** @return array<int, int> major_id => secured places in the last snapshot taken at least 7 days ago */
    private function secured7DaysAgo(AdmissionCycle $cycle): array
    {
        $day = now()->subDays(7)->toDateString();
        $result = [];

        foreach (QuotaSnapshot::query()->where('cycle_id', $cycle->id)->where('snapshot_date', '<=', $day)->orderByDesc('snapshot_date')->get(['major_id', 'secured']) as $s) {
            $result[$s->major_id] ??= $s->secured;
        }

        return $result;
    }
}
