<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Actions\DeleteApplication;
use App\Domain\Admission\Actions\RecalculateApplication;
use App\Domain\Admission\Actions\SaveApplication;
use App\Domain\Admission\Actions\TransitionApplication;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Admission\Services\ApplicationPanel;
use App\Domain\Applicant\Actions\SaveApplicantScores;
use App\Domain\Applicant\Enums\ScoreType;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in applicant (task 3.4): sees where the application stands, types own scores (unverified until the registrar
 * checks them), adds, submits and withdraws preferences, and confirms enrolment once admitted. Every action goes through
 * the same actions as the staff screens, so the rules (15 preferences, combinations, closed cycle) are the same.
 */
class PortalController extends Controller
{
    /** What an applicant may do to a preference; the registrar decisions stay with staff. */
    private const OWN_ACTIONS = ['submit' => 'Nộp', 'withdraw' => 'Rút', 'confirm' => 'Xác nhận nhập học'];

    public function home(Request $request, CurrentCycle $currentCycle, ApplicationPanel $panel): Response
    {
        /** @var Applicant $applicant */
        $applicant = $request->attributes->get('portal_applicant');
        $cycle = $currentCycle->get();
        $case = $cycle === null ? null : AdmissionCase::query()->with('stage:id,name')->where('applicant_id', $applicant->id)->where('cycle_id', $cycle->id)->first();
        $applications = $case === null ? collect() : Application::query()->with(['major:id,name', 'cycleMethod.method:id,name', 'combination:id,code', 'round:id,name'])->where('case_id', $case->id)->orderBy('round_id')->orderBy('preference_order')->get();
        $scores = $applicant->scores()->whereIn('score_type', ['transcript', 'thpt_exam'])->get();

        return Inertia::render('Portal/Home', [
            'applicant' => ['full_name' => $applicant->full_name, 'phone' => $applicant->phone, 'email' => $applicant->email, 'code' => $applicant->code],
            'cycle' => $cycle === null ? null : ['name' => $cycle->name, 'closed' => $cycle->isClosed()],
            'stage' => $case?->stage?->name,
            'subjects' => Subject::query()->orderByDesc('is_core')->orderBy('id')->get(['id', 'code', 'name'])->map(fn (Subject $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'transcript' => $this->scoreOf($scores, 'transcript', $s->id),
                'thpt' => $this->scoreOf($scores, 'thpt_exam', $s->id),
            ])->all(),
            'applications' => $applications->map(fn (Application $a) => [
                'id' => $a->id,
                'preference_order' => $a->preference_order,
                'round' => $a->round?->name,
                'major' => $a->major?->name,
                'method' => $a->cycleMethod?->display_name ?: $a->cycleMethod?->method?->name,
                'combination' => $a->combination?->code,
                'final_score' => $a->final_score === null ? null : (float) $a->final_score,
                'missing' => $a->score_breakdown['missing'] ?? [],
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'actions' => $this->actionsFor($a, $cycle),
                'can_delete' => $a->status === ApplicationStatus::Draft && ! $cycle?->isClosed(),
            ])->all(),
            'options' => $cycle === null || $cycle->isClosed() ? ['majors' => [], 'rounds' => []] : $panel->options($cycle),
        ]);
    }

    /** @return list<array{action: string, label: string}> */
    private function actionsFor(Application $a, ?AdmissionCycle $cycle): array
    {
        if ($cycle === null || $cycle->isClosed()) {
            return [];
        }

        $actions = [];

        foreach (self::OWN_ACTIONS as $action => $label) {
            if (in_array($a->status, TransitionApplication::ACTIONS[$action][0], true)) {
                $actions[] = ['action' => $action, 'label' => $label];
            }
        }

        return $actions;
    }

    public function saveScores(Request $request, SaveApplicantScores $save, RecalculateApplication $recalculate): RedirectResponse
    {
        /** @var Applicant $applicant */
        $applicant = $request->attributes->get('portal_applicant');
        $data = $request->validate([
            'scores' => ['required', 'array', 'max:40'],
            'scores.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'scores.*.transcript' => ['nullable', 'numeric', 'between:0,10'],
            'scores.*.thpt' => ['nullable', 'numeric', 'between:0,10'],
        ]);
        $rows = [];

        foreach ($data['scores'] as $row) {
            $rows[] = ['score_type' => ScoreType::Transcript->value, 'subject_id' => $row['subject_id'], 'term' => '12', 'score' => $row['transcript'] ?? null, 'max_score' => 10];
            $rows[] = ['score_type' => ScoreType::ThptExam->value, 'subject_id' => $row['subject_id'], 'score' => $row['thpt'] ?? null, 'max_score' => 10, 'year' => $applicant->graduation_year];
        }

        $save->handle($applicant, $rows);

        // Drafts are scored again with the new numbers, so they can be submitted.
        Application::query()->where('status', ApplicationStatus::Draft->value)->whereHas('case', fn ($q) => $q->where('applicant_id', $applicant->id))->get()
            ->each(function (Application $draft) use ($recalculate): void {
                try {
                    $recalculate->handle($draft);
                } catch (ValidationException) {
                    // closed cycle: nothing to re-score
                }
            });

        return $this->done('Đã lưu điểm. Điểm sẽ được nhà trường kiểm tra.');
    }

    public function addApplication(Request $request, SaveApplication $save, CurrentCycle $currentCycle): RedirectResponse
    {
        $data = $request->validate([
            'major_id' => ['required', 'integer'],
            'cycle_method_id' => ['required', 'integer'],
            'combination_id' => ['nullable', 'integer'],
            'round_id' => ['required', 'integer'],
        ]);

        $save->handle($this->caseOf($request, $currentCycle), null, $data);

        return $this->done('Đã thêm nguyện vọng.');
    }

    public function transition(Request $request, int $id, string $action, TransitionApplication $transition): RedirectResponse
    {
        abort_unless(isset(self::OWN_ACTIONS[$action]), 404);

        $application = $this->applicationOf($request, $id);
        $transition->handle($application, $action);

        return $this->done(self::OWN_ACTIONS[$action].': '.$application->refresh()->status->label().'.');
    }

    public function destroy(Request $request, int $id, DeleteApplication $delete): RedirectResponse
    {
        $delete->handle($this->applicationOf($request, $id));

        return $this->done('Đã xoá nguyện vọng nháp.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function applicationOf(Request $request, int $id): Application
    {
        /** @var Applicant $applicant */
        $applicant = $request->attributes->get('portal_applicant');

        return Application::query()->whereHas('case', fn ($q) => $q->where('applicant_id', $applicant->id))->findOrFail($id);
    }

    /** The case of the applicant in the current cycle, opened on first use. */
    private function caseOf(Request $request, CurrentCycle $currentCycle): AdmissionCase
    {
        /** @var Applicant $applicant */
        $applicant = $request->attributes->get('portal_applicant');
        $cycle = $currentCycle->get() ?? throw ValidationException::withMessages(['major_id' => 'Nhà trường chưa mở mùa tuyển sinh.']);

        return AdmissionCase::query()->where('applicant_id', $applicant->id)->where('cycle_id', $cycle->id)->first()
            ?? app(CreateAdmissionCase::class)->handle($applicant, $cycle, ['source_id' => LeadSource::query()->where('code', 'website')->value('id')]);
    }

    /** @param  \Illuminate\Support\Collection<int, \App\Domain\Applicant\Models\ApplicantScore>  $scores */
    private function scoreOf($scores, string $type, int $subjectId): ?float
    {
        $score = $scores->first(fn ($s) => $s->score_type->value === $type && $s->subject_id === $subjectId);

        return $score?->score === null ? null : (float) $score->score;
    }
}
