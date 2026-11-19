<?php

namespace App\Domain\Platform\Services;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\Province;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use Illuminate\Support\Collection;

/**
 * Numbers behind the map (task 3.6): per province (heat circles) and per high school (markers), for one cycle.
 * applicants = applicants of the province / school; leads = their cases in the cycle; enrolled = those cases that
 * were won. Every query goes through the scoped models, so people only see what their data scope allows.
 */
class MapData
{
    /** @return list<array{id: int, code: string, name: string, lat: float, lng: float, applicants: int, leads: int, enrolled: int}> */
    public function provinces(?AdmissionCycle $cycle): array
    {
        $applicants = Applicant::query()->whereNull('merged_into_id')->whereNotNull('province_id')->selectRaw('province_id as k, count(*) as n')->groupBy('province_id')->pluck('n', 'k');
        [$leads, $enrolled] = $this->caseCounts($cycle, 'province_id');
        $centroids = config('province_centroids');

        return Province::query()->orderBy('name')->get(['id', 'code', 'name'])
            ->filter(fn (Province $p) => isset($centroids[$p->code]))
            ->map(fn (Province $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'lat' => $centroids[$p->code][0],
                'lng' => $centroids[$p->code][1],
                'applicants' => (int) ($applicants[$p->id] ?? 0),
                'leads' => (int) ($leads[$p->id] ?? 0),
                'enrolled' => (int) ($enrolled[$p->id] ?? 0),
            ])->values()->all();
    }

    /**
     * Schools that have coordinates.
     *
     * @return array{schools: list<array<string, mixed>>, without_coordinates: int}
     */
    public function schools(?AdmissionCycle $cycle): array
    {
        $applicants = Applicant::query()->whereNull('merged_into_id')->whereNotNull('high_school_id')->selectRaw('high_school_id as k, count(*) as n')->groupBy('high_school_id')->pluck('n', 'k');
        [$leads, $enrolled] = $this->caseCounts($cycle, 'high_school_id');

        $schools = HighSchool::query()->whereNotNull('lat')->whereNotNull('lng')->with('province:id,name')->orderBy('name')->get();

        return [
            'schools' => $schools->map(fn (HighSchool $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'province' => $s->province?->name,
                'lat' => (float) $s->lat,
                'lng' => (float) $s->lng,
                'applicants' => (int) ($applicants[$s->id] ?? 0),
                'leads' => (int) ($leads[$s->id] ?? 0),
                'enrolled' => (int) ($enrolled[$s->id] ?? 0),
            ])->all(),
            'without_coordinates' => HighSchool::query()->where(fn ($q) => $q->whereNull('lat')->orWhereNull('lng'))->count(),
        ];
    }

    /**
     * Cases (all, and won) of the cycle grouped by an applicant column.
     *
     * @return array{0: Collection<int, int>, 1: Collection<int, int>}
     */
    private function caseCounts(?AdmissionCycle $cycle, string $column): array
    {
        if ($cycle === null) {
            return [collect(), collect()];
        }

        $base = fn () => AdmissionCase::query()->where('admission_cases.cycle_id', $cycle->id)
            ->join('applicants', 'applicants.id', '=', 'admission_cases.applicant_id')->whereNotNull("applicants.{$column}")
            ->groupBy("applicants.{$column}");

        return [
            $base()->selectRaw("applicants.{$column} as k, count(*) as n")->pluck('n', 'k'),
            $base()->where('admission_cases.status', 'won')->selectRaw("applicants.{$column} as k, count(*) as n")->pluck('n', 'k'),
        ];
    }
}
