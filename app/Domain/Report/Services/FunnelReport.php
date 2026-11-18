<?php

namespace App\Domain\Report\Services;

use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Major\Models\MajorQuota;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Numbers for the dashboard and the funnel reports (docs/06-giao-dien.md, M16). Everything is computed from the
 * admission cases of one cycle the current user may see (the CASE data scope applies, so a counselor sees their own
 * funnel and a head the whole organization's).
 *
 * Funnel columns of a breakdown row:
 *   total      cases
 *   contacted  first_contacted_at filled
 *   admitted   at stage "Trúng tuyển" or further (admitted / confirmed / enrolled)
 *   enrolled   stage of type won
 *   lost       stage of type lost
 *   rate       enrolled / total, in percent
 */
class FunnelReport
{
    /** dimension => label */
    public const DIMENSIONS = [
        'source' => 'Nguồn',
        'campaign' => 'Chiến dịch',
        'high_school' => 'Trường THPT',
        'province' => 'Tỉnh/thành phố',
        'major' => 'Ngành',
        'owner' => 'Tư vấn viên',
    ];

    /** Shown for cases without a value in the dimension. */
    private const BLANK = [
        'source' => 'Chưa rõ nguồn',
        'campaign' => 'Không thuộc chiến dịch',
        'high_school' => 'Chưa rõ trường',
        'province' => 'Chưa rõ tỉnh',
        'major' => 'Chưa chọn ngành',
        'owner' => 'Kho chung',
    ];

    private const ADMITTED = "s.code in ('admitted','confirmed','enrolled')";

    /**
     * @return array<string, mixed>
     */
    public function overview(AdmissionCycle $cycle, User $user): array
    {
        return [
            'kpis' => [
                'new_this_week' => $this->cases($cycle)->where('admission_cases.created_at', '>=', now()->subDays(7))->count(),
                'total' => $this->cases($cycle)->count(),
                'open' => $this->cases($cycle)->where('s.type', 'open')->count(),
                'admitted' => $this->cases($cycle)->whereRaw(self::ADMITTED)->count(),
                'enrolled' => $this->cases($cycle)->where('s.type', 'won')->count(),
                'target_enrolled' => $cycle->target_enrolled,
                'lost' => $this->cases($cycle)->where('s.type', 'lost')->count(),
            ],
            'funnel' => $this->funnel($cycle),
            'daily' => $this->daily($cycle),
            'top_sources' => array_slice($this->breakdown($cycle, 'source'), 0, 5),
            'top_schools' => array_slice($this->breakdown($cycle, 'high_school'), 0, 5),
            'quotas' => $this->quotas($cycle),
            'my_tasks' => $this->myOverdueTasks($user),
        ];
    }

    /**
     * Cases per stage of the default pipeline, in pipeline order.
     *
     * @return list<array{id: int, name: string, color: string|null, type: string, count: int}>
     */
    public function funnel(AdmissionCycle $cycle): array
    {
        $pipeline = Pipeline::query()->where('is_default', true)->where('level', $cycle->level->value)->first()
            ?? Pipeline::query()->where('is_default', true)->first();

        if ($pipeline === null) {
            return [];
        }

        $counts = $this->counts($this->cases($cycle), 'admission_cases.stage_id');

        return $pipeline->stages()->get()->map(fn ($s) => [
            'id' => $s->id, 'name' => $s->name, 'color' => $s->color, 'type' => $s->type->value, 'count' => (int) ($counts[$s->id] ?? 0),
        ])->all();
    }

    /**
     * New cases per day for the last $days days (days in the display timezone, zero-filled).
     *
     * @return list<array{date: string, count: int}>
     */
    public function daily(AdmissionCycle $cycle, int $days = 30): array
    {
        $tz = config('app.display_timezone');
        $from = now($tz)->subDays($days - 1)->startOfDay();
        $created = $this->cases($cycle)->where('admission_cases.created_at', '>=', $from->copy()->utc())->limit(50000)->toBase()->pluck('admission_cases.created_at');
        $perDay = $created->countBy(fn ($at) => Carbon::parse($at, 'UTC')->setTimezone($tz)->toDateString());

        return collect(range(0, $days - 1))->map(function (int $i) use ($from, $perDay): array {
            $date = $from->copy()->addDays($i)->toDateString();

            return ['date' => $date, 'count' => (int) ($perDay[$date] ?? 0)];
        })->all();
    }

    /**
     * Funnel per value of a dimension, biggest first.
     *
     * @return list<array{key: int|null, label: string, total: int, contacted: int, admitted: int, enrolled: int, lost: int, rate: float}>
     */
    public function breakdown(AdmissionCycle $cycle, string $dimension): array
    {
        $dimension = array_key_exists($dimension, self::DIMENSIONS) ? $dimension : 'source';
        $query = $this->cases($cycle);

        match ($dimension) {
            'source' => $query->leftJoin('lead_sources as dim', 'dim.id', '=', 'admission_cases.source_id'),
            'campaign' => $query->leftJoin('campaigns as dim', 'dim.id', '=', 'admission_cases.campaign_id'),
            'major' => $query->leftJoin('majors as dim', 'dim.id', '=', 'admission_cases.primary_major_id'),
            'owner' => $query->leftJoin('users as dim', 'dim.id', '=', 'admission_cases.owner_id'),
            'high_school' => $query->join('applicants as ap', 'ap.id', '=', 'admission_cases.applicant_id')->leftJoin('high_schools as dim', 'dim.id', '=', 'ap.high_school_id'),
            'province' => $query->join('applicants as ap', 'ap.id', '=', 'admission_cases.applicant_id')->leftJoin('provinces as dim', 'dim.id', '=', 'ap.province_id'),
        };

        $rows = $query
            ->selectRaw(
                'dim.id as k, dim.name as l, count(*) as total, '
                .'sum(case when admission_cases.first_contacted_at is not null then 1 else 0 end) as contacted, '
                .'sum(case when '.self::ADMITTED.' then 1 else 0 end) as admitted, '
                ."sum(case when s.type = 'won' then 1 else 0 end) as enrolled, "
                ."sum(case when s.type = 'lost' then 1 else 0 end) as lost",
            )
            ->groupBy('dim.id', 'dim.name')
            ->orderByDesc('total')
            ->limit(500)
            ->toBase()->get();

        return $rows->map(fn ($r) => [
            'key' => $r->k === null ? null : (int) $r->k,
            'label' => $r->l ?? self::BLANK[$dimension],
            'total' => (int) $r->total,
            'contacted' => (int) $r->contacted,
            'admitted' => (int) $r->admitted,
            'enrolled' => (int) $r->enrolled,
            'lost' => (int) $r->lost,
            'rate' => $r->total > 0 ? round($r->enrolled * 100 / $r->total, 1) : 0.0,
        ])->all();
    }

    /**
     * Quota progress per major of the cycle: quota, interested (cases choosing it), admitted+, enrolled.
     *
     * @return list<array{major_id: int, major: string, quota: int, target: int|null, interested: int, admitted: int, enrolled: int}>
     */
    public function quotas(AdmissionCycle $cycle): array
    {
        $interested = $this->counts($this->cases($cycle)->whereNotNull('admission_cases.primary_major_id'), 'admission_cases.primary_major_id');
        $admitted = $this->counts($this->cases($cycle)->whereNotNull('admission_cases.primary_major_id')->whereRaw(self::ADMITTED), 'admission_cases.primary_major_id');
        $enrolled = $this->counts($this->cases($cycle)->where('s.type', 'won')->whereNotNull('admission_cases.enrolled_major_id'), 'admission_cases.enrolled_major_id');

        return MajorQuota::query()->with('major:id,name')->where('cycle_id', $cycle->id)->orderByDesc('quota_total')->limit(30)->get()
            ->map(fn (MajorQuota $q) => [
                'major_id' => $q->major_id,
                'major' => $q->major?->name ?? '—',
                'quota' => (int) $q->quota_total,
                'target' => $q->target_enrolled,
                'interested' => (int) ($interested[$q->major_id] ?? 0),
                'admitted' => (int) ($admitted[$q->major_id] ?? 0),
                'enrolled' => (int) ($enrolled[$q->major_id] ?? 0),
            ])->all();
    }

    /** @return array{count: int, items: list<array{id: int, title: string, due_at: string|null}>} */
    public function myOverdueTasks(User $user): array
    {
        $query = Task::query()->where('assignee_id', $user->id)->where('status', 'todo')->where('due_at', '<', now());

        return [
            'count' => (clone $query)->count(),
            'items' => $query->orderBy('due_at')->limit(5)->get()->map(fn (Task $t) => ['id' => $t->id, 'title' => $t->title, 'due_at' => $t->due_at?->toIso8601String()])->all(),
        ];
    }

    /** Cases of the cycle with their stage joined as "s" (the user's data scope is applied by the model). */
    private function cases(AdmissionCycle $cycle): Builder
    {
        return AdmissionCase::query()
            ->join('pipeline_stages as s', 's.id', '=', 'admission_cases.stage_id')
            ->where('admission_cases.cycle_id', $cycle->id);
    }

    /**
     * @param  Builder<AdmissionCase>  $query
     * @return array<int|string, int>
     */
    private function counts(Builder $query, string $column): array
    {
        return $query->selectRaw("{$column} as k, count(*) as c")->groupBy($column)->toBase()->get()->pluck('c', 'k')->map(fn ($c) => (int) $c)->all();
    }
}
