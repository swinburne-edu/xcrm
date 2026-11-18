<?php

namespace App\Domain\Activity\Services;

use App\Domain\Activity\Models\Activity;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Finds the record a timeline / task belongs to, through its data scope (outside it = not found),
 * and builds the list of entries to show for it.
 *
 *   applicant      uuid or id   shows the applicant's entries and those of all their cases
 *   case           uuid or id   the case's own entries
 *   high_school    id           the school's entries and those of its contacts
 *   campaign       id
 */
class TimelineSubject
{
    /** request type => model class */
    public const TYPES = [
        'applicant' => Applicant::class,
        'case' => AdmissionCase::class,
        'high_school' => HighSchool::class,
        'campaign' => Campaign::class,
    ];

    /** @throws ModelNotFoundException */
    public function resolve(string $type, string|int $key): Model
    {
        $class = self::TYPES[$type] ?? throw new ModelNotFoundException;
        $query = $class::query();
        $isNumeric = is_int($key) || ctype_digit((string) $key);

        if (! $isNumeric && ! in_array($type, ['applicant', 'case'], true)) {
            throw new ModelNotFoundException;
        }

        return $isNumeric ? $query->findOrFail((int) $key) : $query->where('uuid', (string) $key)->firstOrFail();
    }

    /** @return Builder<Activity> */
    public function entries(Model $subject): Builder
    {
        $pairs = [[$subject->getMorphClass(), [$subject->getKey()]]];

        if ($subject instanceof Applicant) {
            $pairs[] = ['admission_case', AdmissionCase::query()->withoutGlobalScopes()->where('applicant_id', $subject->id)->pluck('id')->all()];
        }

        if ($subject instanceof HighSchool) {
            $pairs[] = ['school_contact', SchoolContact::query()->withoutGlobalScopes()->where('high_school_id', $subject->id)->pluck('id')->all()];
        }

        return Activity::query()->where(function (Builder $q) use ($pairs): void {
            foreach ($pairs as [$type, $ids]) {
                if ($ids !== []) {
                    $q->orWhere(fn (Builder $p) => $p->where('subject_type', $type)->whereIn('subject_id', $ids));
                }
            }
        });
    }
}
