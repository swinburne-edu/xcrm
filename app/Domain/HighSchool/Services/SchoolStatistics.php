<?php

namespace App\Domain\HighSchool\Services;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolActivity;
use Illuminate\Support\Facades\DB;

/**
 * "Thống kê theo mùa" of a high school (docs/03 M2): for every cycle with data, the students from the school who have
 * a case (applicants, cases, admitted = stage Trúng tuyển / Xác nhận / Nhập học, enrolled = a "won" stage) and what the
 * school cost / brought through activities (not cancelled): count, check-ins, reported attendance, cost.
 * Aggregates over the whole organization (the data scope of the viewer does not apply).
 */
class SchoolStatistics
{
    /**
     * @return list<array<string, mixed>> newest cycle first
     */
    public function forSchool(HighSchool $school): array
    {
        $cases = AdmissionCase::query()->withoutDataScope()
            ->join('applicants as a', 'a.id', '=', 'admission_cases.applicant_id')
            ->join('pipeline_stages as s', 's.id', '=', 'admission_cases.stage_id')
            ->where('a.high_school_id', $school->id)->whereNull('a.deleted_at')
            ->selectRaw("admission_cases.cycle_id as cycle_id, count(distinct admission_cases.applicant_id) as applicants, count(*) as cases, sum(case when s.code in ('admitted', 'confirmed', 'enrolled') then 1 else 0 end) as admitted, sum(case when s.type = 'won' then 1 else 0 end) as enrolled")
            ->groupBy('admission_cases.cycle_id')->toBase()->get()->keyBy('cycle_id');

        $activities = SchoolActivity::query()->withoutDataScope()->where('high_school_id', $school->id)->where('status', '!=', 'cancelled')
            ->selectRaw('cycle_id, count(*) as activities, coalesce(sum(actual_attendees), 0) as attendance, coalesce(sum(cost), 0) as cost')
            ->groupBy('cycle_id')->toBase()->get()->keyBy('cycle_id');

        $checkins = DB::table('activity_attendees as t')->join('school_activities as sa', 'sa.id', '=', 't.school_activity_id')
            ->where('sa.high_school_id', $school->id)->whereNull('sa.deleted_at')->where('sa.status', '!=', 'cancelled')
            ->selectRaw('sa.cycle_id as cycle_id, count(*) as total')->groupBy('sa.cycle_id')->pluck('total', 'cycle_id');

        $ids = $cases->keys()->merge($activities->keys())->unique()->all();

        return AdmissionCycle::query()->whereIn('id', $ids)->orderByDesc('year')->orderByDesc('id')->get()->map(function (AdmissionCycle $cycle) use ($cases, $activities, $checkins): array {
            $c = $cases->get($cycle->id);
            $a = $activities->get($cycle->id);
            $applicants = (int) ($c->applicants ?? 0);
            $enrolled = (int) ($c->enrolled ?? 0);

            return [
                'cycle_id' => $cycle->id,
                'cycle' => $cycle->name,
                'year' => $cycle->year,
                'applicants' => $applicants,
                'cases' => (int) ($c->cases ?? 0),
                'admitted' => (int) ($c->admitted ?? 0),
                'enrolled' => $enrolled,
                'enrolled_rate' => $applicants > 0 ? round($enrolled / $applicants * 100, 1) : null,
                'activities' => (int) ($a->activities ?? 0),
                'checkins' => (int) ($checkins[$cycle->id] ?? 0),
                'attendance' => (int) ($a->attendance ?? 0),
                'cost' => (int) ($a->cost ?? 0),
            ];
        })->all();
    }
}
