<?php

namespace App\Domain\Applicant\Actions;

use App\Domain\Activity\Actions\CaseNextAction;
use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Activity\Models\Activity;
use App\Domain\Activity\Models\Appointment;
use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Admission\Models\CaseStageHistory;
use App\Domain\Admission\Models\CaseTouch;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantCertificate;
use App\Domain\Applicant\Models\ApplicantDocument;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flow F6 (docs/05-luong-nghiep-vu.md): merges a duplicate applicant ($merge) into the one to keep ($keep).
 *
 *  - Fields: $choices[field] = 'merge' takes the duplicate's value, anything else keeps the survivor's;
 *    a blank survivor value is always filled from the duplicate.
 *  - Guardians, certificates, scores (the survivor's score wins on the same subject/term), timeline entries and tasks
 *    move to the survivor.
 *  - Cases: a cycle only the duplicate has is handed over; when both have a case in the same cycle they become one — the
 *    survivor's case stays and takes over the state of the duplicate's case if that one is further along the pipeline;
 *    its timeline, tasks, appointments, touches, stage history, interested majors and tags all move to it.
 *  - The duplicate gets merged_into_id and is soft-deleted; the survivor's timeline records the merge.
 */
class MergeApplicants
{
    /** Fields the user can choose between. */
    public const FIELDS = [
        'full_name', 'gender', 'dob', 'phone', 'email', 'citizen_id', 'zalo_id', 'facebook_id', 'province_id', 'ward_id',
        'address', 'high_school_id', 'class_name', 'current_grade', 'graduation_year', 'priority_area_id', 'priority_group_id',
    ];

    public function __construct(private readonly RecordActivity $record, private readonly CaseNextAction $nextAction) {}

    /**
     * @param  array<string, string>  $choices  field => 'keep' | 'merge'
     */
    public function handle(Applicant $keep, Applicant $merge, array $choices, User $by): Applicant
    {
        if ($keep->is($merge)) {
            throw ValidationException::withMessages(['merge' => 'Không thể gộp một thí sinh với chính nó.']);
        }

        if ($keep->organization_id !== $merge->organization_id) {
            throw ValidationException::withMessages(['merge' => 'Hai thí sinh thuộc hai tổ chức khác nhau.']);
        }

        if ($keep->merged_into_id !== null || $merge->merged_into_id !== null) {
            throw ValidationException::withMessages(['merge' => 'Một trong hai thí sinh đã được gộp trước đó.']);
        }

        DB::transaction(function () use ($keep, $merge, $choices, $by): void {
            $this->fields($keep, $merge, $choices);
            $this->guardians($keep, $merge);
            ApplicantCertificate::query()->where('applicant_id', $merge->id)->update(['applicant_id' => $keep->id]);
            ApplicantDocument::query()->withoutGlobalScopes()->where('applicant_id', $merge->id)->update(['applicant_id' => $keep->id]);
            $this->scores($keep, $merge);
            Activity::query()->withoutGlobalScopes()->where('subject_type', 'applicant')->where('subject_id', $merge->id)->update(['subject_id' => $keep->id]);
            Task::query()->withoutGlobalScopes()->where('subject_type', 'applicant')->where('subject_id', $merge->id)->update(['subject_id' => $keep->id]);
            $cases = $this->cases($keep, $merge);

            $merge->merged_into_id = $keep->id;
            $merge->save();
            $merge->delete();

            $this->record->handle($keep, ActivityType::System, [
                'title' => "Gộp thí sinh trùng: {$merge->full_name} ({$merge->code})",
                'meta' => ['merged_applicant_id' => $merge->id, 'merged_code' => $merge->code, 'cases' => $cases],
            ], $by);
        });

        return $keep->refresh();
    }

    /** @param  array<string, string>  $choices */
    private function fields(Applicant $keep, Applicant $merge, array $choices): void
    {
        foreach (self::FIELDS as $field) {
            $mine = $keep->getAttribute($field);
            $theirs = $merge->getAttribute($field);
            $hasTheirs = $theirs !== null && $theirs !== '';
            $blankMine = $mine === null || $mine === '';

            if ($hasTheirs && (($choices[$field] ?? 'keep') === 'merge' || $blankMine)) {
                $keep->setAttribute($field, $theirs);
            }
        }

        if ($keep->isDirty()) {
            $keep->save();
        }
    }

    private function guardians(Applicant $keep, Applicant $merge): void
    {
        $existing = $keep->guardians()->pluck('guardians.id')->all();

        foreach ($merge->guardians()->get() as $guardian) {
            if (! in_array($guardian->id, $existing, true)) {
                $keep->guardians()->attach($guardian->id, [
                    'relationship' => $guardian->pivot->relationship,
                    'is_decision_maker' => $guardian->pivot->is_decision_maker,
                    'is_primary_contact' => $guardian->pivot->is_primary_contact,
                ]);
            }
        }

        $merge->guardians()->detach();
    }

    private function scores(Applicant $keep, Applicant $merge): void
    {
        foreach (ApplicantScore::query()->where('applicant_id', $merge->id)->get() as $score) {
            $clash = ApplicantScore::query()->where('applicant_id', $keep->id)
                ->where('score_type', $score->score_type)
                ->where(fn ($q) => $score->subject_id === null ? $q->whereNull('subject_id') : $q->where('subject_id', $score->subject_id))
                ->where(fn ($q) => $score->term === null ? $q->whereNull('term') : $q->where('term', $score->term))
                ->where(fn ($q) => $score->exam_name === null ? $q->whereNull('exam_name') : $q->where('exam_name', $score->exam_name))
                ->exists();

            $clash ? $score->delete() : $score->update(['applicant_id' => $keep->id]);
        }
    }

    /** @return int number of cases that were joined or handed over */
    private function cases(Applicant $keep, Applicant $merge): int
    {
        $count = 0;
        $keepCases = AdmissionCase::query()->withoutGlobalScopes()->with('stage')->where('applicant_id', $keep->id)->get()->keyBy('cycle_id');

        AdmissionCase::withoutEvents(function () use ($keep, $merge, $keepCases, &$count): void {
            foreach (AdmissionCase::query()->withoutGlobalScopes()->with('stage')->where('applicant_id', $merge->id)->get() as $other) {
                $count++;
                $survivor = $keepCases->get($other->cycle_id);

                $survivor === null ? $other->update(['applicant_id' => $keep->id]) : $this->joinCases($survivor, $other);
            }
        });

        return $count;
    }

    private function joinCases(AdmissionCase $survivor, AdmissionCase $other): void
    {
        $state = [];

        if ($other->stage !== null && $survivor->stage !== null && $other->stage->sort_order > $survivor->stage->sort_order) {
            $state = [
                'pipeline_id' => $other->pipeline_id,
                'stage_id' => $other->stage_id,
                'status' => $other->status,
                'lost_reason_id' => $other->lost_reason_id,
                'lost_note' => $other->lost_note,
                'enrolled_major_id' => $other->enrolled_major_id,
                'enrolled_at' => $other->enrolled_at,
            ];
        }

        foreach (['owner_id', 'team_id', 'source_id', 'campaign_id', 'primary_major_id', 'temperature'] as $column) {
            if ($survivor->getAttribute($column) === null && $other->getAttribute($column) !== null) {
                $state[$column] = $other->getAttribute($column);
            }
        }

        $state['first_contacted_at'] = collect([$survivor->first_contacted_at, $other->first_contacted_at])->filter()->min();
        $state['last_activity_at'] = collect([$survivor->last_activity_at, $other->last_activity_at])->filter()->max();

        $survivor->update($state);

        Activity::query()->withoutGlobalScopes()->where('subject_type', 'admission_case')->where('subject_id', $other->id)->update(['subject_id' => $survivor->id]);
        Task::query()->withoutGlobalScopes()->where('subject_type', 'admission_case')->where('subject_id', $other->id)->update(['subject_id' => $survivor->id]);
        Appointment::query()->withoutGlobalScopes()->where('case_id', $other->id)->update(['case_id' => $survivor->id]);
        CaseTouch::query()->where('case_id', $other->id)->update(['case_id' => $survivor->id]);
        CaseStageHistory::query()->where('case_id', $other->id)->update(['case_id' => $survivor->id]);

        foreach (DB::table('case_interested_majors')->where('case_id', $other->id)->get() as $row) {
            DB::table('case_interested_majors')->insertOrIgnore(['case_id' => $survivor->id, 'major_id' => $row->major_id, 'priority' => $row->priority]);
        }

        DB::table('case_interested_majors')->where('case_id', $other->id)->delete();

        foreach (DB::table('taggables')->where('taggable_type', 'admission_case')->where('taggable_id', $other->id)->get() as $row) {
            DB::table('taggables')->insertOrIgnore(['tag_id' => $row->tag_id, 'taggable_type' => 'admission_case', 'taggable_id' => $survivor->id]);
        }

        DB::table('taggables')->where('taggable_type', 'admission_case')->where('taggable_id', $other->id)->delete();

        $this->applications($survivor, $other);
        ApplicantDocument::query()->withoutGlobalScopes()->where('case_id', $other->id)->update(['case_id' => $survivor->id]);

        $other->delete();
        $this->nextAction->sync('admission_case', $survivor->id);
    }

    /**
     * Nguyện vọng of the merged case move to the surviving one. A round keeps its order numbers unique (R3):
     * a clashing order takes the next free one; two cases cannot together exceed 15 in a round.
     */
    private function applications(AdmissionCase $survivor, AdmissionCase $other): void
    {
        foreach (Application::query()->withoutGlobalScopes()->where('case_id', $other->id)->orderBy('round_id')->orderBy('preference_order')->get() as $application) {
            $taken = Application::query()->withoutGlobalScopes()->where('case_id', $survivor->id)->where('round_id', $application->round_id)->pluck('preference_order')->all();
            $order = in_array($application->preference_order, $taken, true)
                ? collect(range(1, Application::MAX_PER_CASE))->first(fn (int $n) => ! in_array($n, $taken, true))
                : $application->preference_order;

            if ($order === null) {
                throw ValidationException::withMessages(['merge' => 'Hai hồ sơ có tổng cộng quá '.Application::MAX_PER_CASE.' nguyện vọng trong cùng một đợt; hãy rút bớt trước khi gộp.']);
            }

            $application->forceFill(['case_id' => $survivor->id, 'preference_order' => $order])->save();
        }
    }
}
