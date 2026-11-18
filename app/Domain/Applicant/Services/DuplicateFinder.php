<?php

namespace App\Domain\Applicant\Services;

use App\Domain\Applicant\Models\Applicant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Có thể trùng" list (flow F6): applicants that were not caught by the duplicate check at entry time.
 *   same_school   same name (ignoring case, accents and spacing) at the same high school
 *   guardian      same guardian phone number shared by different applicants
 * Only applicants the current user may see are compared (Applicant's data scope applies); merged ones are skipped.
 */
class DuplicateFinder
{
    public const LIMIT = 5000;

    public const REASONS = [
        'same_school' => 'Cùng họ tên và cùng trường THPT',
        'guardian' => 'Cùng số điện thoại phụ huynh',
    ];

    /**
     * @return list<array{key: string, reason: string, reason_label: string, applicants: Collection<int, Applicant>}>
     */
    public function groups(): array
    {
        $groups = [];

        $bySchool = Applicant::query()->whereNull('merged_into_id')->whereNotNull('high_school_id')
            ->orderBy('id')->limit(self::LIMIT)->get(['id', 'full_name', 'high_school_id'])
            ->groupBy(fn (Applicant $a) => $a->high_school_id.'|'.$this->name($a->full_name))
            ->filter(fn (Collection $g) => $g->count() > 1);

        foreach ($bySchool as $key => $members) {
            $groups[] = $this->group("same_school:{$key}", 'same_school', $members->pluck('id')->all());
        }

        $visible = Applicant::query()->whereNull('merged_into_id')->select('applicants.id');

        $byGuardian = DB::table('applicant_guardian as ag')
            ->join('guardians as g', 'g.id', '=', 'ag.guardian_id')
            ->whereNotNull('g.phone_normalized')
            ->whereIn('ag.applicant_id', $visible)
            ->get(['g.phone_normalized as phone', 'ag.applicant_id as applicant_id'])
            ->groupBy('phone')
            ->map(fn (Collection $rows) => $rows->pluck('applicant_id')->unique()->values())
            ->filter(fn (Collection $ids) => $ids->count() > 1);

        foreach ($byGuardian as $phone => $ids) {
            $groups[] = $this->group("guardian:{$phone}", 'guardian', $ids->all());
        }

        return $groups;
    }

    /**
     * @param  list<int>  $ids
     * @return array{key: string, reason: string, reason_label: string, applicants: Collection<int, Applicant>}
     */
    private function group(string $key, string $reason, array $ids): array
    {
        return [
            'key' => $key,
            'reason' => $reason,
            'reason_label' => self::REASONS[$reason],
            'applicants' => Applicant::query()->with('highSchool:id,name')->whereIn('id', $ids)->orderBy('id')->get(),
        ];
    }

    private function name(string $name): string
    {
        return preg_replace('/\s+/', ' ', trim(Str::lower(Str::ascii($name)))) ?? '';
    }
}
