<?php

namespace App\Http\Controllers\Admission;

use App\Domain\Admission\Actions\DeleteApplication;
use App\Domain\Admission\Actions\RecalculateApplication;
use App\Domain\Admission\Actions\SaveApplication;
use App\Domain\Admission\Actions\TransitionApplication;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Admission\Services\ApplicationPanel;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Domain\Shared\Support\Listing\Like;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\ApplicationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nguyện vọng xét tuyển (M11, task 2.2).
 *  GET  /applications                    admission list by major: ranked by score against the quota
 *  POST /cases/{uuid}/applications       add a nguyện vọng to a case (draft, scored)
 *  PUT  /applications/{id}               edit a draft
 *  DELETE /applications/{id}             delete a draft
 *  POST /applications/{id}/transition    submit / decide / confirm / withdraw
 *  POST /applications/{id}/score         re-score; the registrar may enter the encouragement bonus
 */
class ApplicationController extends Controller
{
    public function index(Request $request, CurrentCycle $currentCycle): Response
    {
        $this->authorize('viewAny', Application::class);

        $cycle = $currentCycle->get();
        $user = $request->user();

        $list = ListQuery::for(
            Application::query()
                ->whereHas('case', fn (Builder $q) => $q->where('cycle_id', $cycle?->id ?? 0))
                ->where('status', '!=', ApplicationStatus::Draft->value)
                ->with(['case.applicant:id,uuid,code,full_name', 'case.owner:id,name', 'major:id,name', 'cycleMethod.method:id,name', 'combination:id,code', 'round:id,name']),
            $request,
        )
            ->filters(
                Filter::exact('major_id'),
                Filter::exact('round_id'),
                Filter::exact('cycle_method_id'),
                Filter::exact('status'),
                Filter::callback('q', function (Builder $q, mixed $term): void {
                    $q->whereHas('case.applicant', fn (Builder $a) => Like::anyColumn($a, ['full_name', 'code'], (string) $term));
                }, fn (mixed $v) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, 100) : null),
            )
            ->sorts(['final_score', 'preference_order', 'submitted_at', 'created_at'])
            ->defaultSort('-final_score');

        $props = $list->toProps(fn (Application $a) => $this->listRow($a));
        $from = $props['meta']['from'] ?? 1;

        foreach ($props['data'] as $i => &$row) {
            $row['rank'] = $from + $i;
        }

        unset($row);

        $majorId = $props['query']['filters']['major_id'][0] ?? null;

        return Inertia::render('Applications/Index', [
            'cycle' => $cycle ? ['id' => $cycle->id, 'name' => $cycle->name, 'closed' => $cycle->isClosed()] : null,
            'applications' => $props,
            'quota' => $majorId === null || $cycle === null ? null : $this->quota((int) $majorId, $cycle->id),
            'options' => [
                'majors' => Major::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn (Major $m) => ['value' => (string) $m->id, 'label' => $m->name])->all(),
                'rounds' => $cycle === null ? [] : AdmissionRound::query()->where('cycle_id', $cycle->id)->orderBy('sort_order')->orderBy('id')->get(['id', 'name'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->name])->all(),
                'methods' => $cycle === null ? [] : CycleMethod::query()->where('cycle_id', $cycle->id)->with('method:id,name')->get()->map(fn (CycleMethod $c) => ['value' => (string) $c->id, 'label' => $c->display_name ?: $c->method?->name])->all(),
                'statuses' => collect(ApplicationStatus::cases())->reject(fn ($s) => $s === ApplicationStatus::Draft)->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values()->all(),
            ],
            'can' => ['review' => $user->can('application.review')],
        ]);
    }

    public function store(ApplicationRequest $request, string $uuid, SaveApplication $save): RedirectResponse
    {
        $case = AdmissionCase::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('create', Application::class);
        $this->authorize('update', $case);

        $application = $save->handle($case, null, $request->validated(), $request->user());

        return $this->toast('Đã thêm nguyện vọng '.$application->preference_order.'.');
    }

    public function update(ApplicationRequest $request, int $id, SaveApplication $save): RedirectResponse
    {
        $application = Application::query()->with('case')->findOrFail($id);
        $this->authorize('update', $application);

        $save->handle($application->case, $application, $request->validated(), $request->user());

        return $this->toast('Đã cập nhật nguyện vọng.');
    }

    public function destroy(int $id, DeleteApplication $delete): RedirectResponse
    {
        $application = Application::query()->findOrFail($id);
        $this->authorize('delete', $application);

        $delete->handle($application);

        return $this->toast('Đã xoá nguyện vọng nháp.');
    }

    public function transition(Request $request, int $id, TransitionApplication $transition): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(array_keys(TransitionApplication::ACTIONS))]]);
        $application = Application::query()->findOrFail($id);

        $this->authorize(in_array($data['action'], TransitionApplication::REVIEW_ACTIONS, true) ? 'review' : 'update', $application);

        $application = $transition->handle($application, $data['action'], $request->user());

        return $this->toast(ApplicationPanel::ACTION_LABELS[$data['action']].": nguyện vọng {$application->preference_order}.");
    }

    public function recalculate(Request $request, int $id, RecalculateApplication $recalculate): RedirectResponse
    {
        $data = $request->validate(['encourage_bonus' => ['nullable', 'numeric', 'between:0,30']]);
        $application = Application::query()->findOrFail($id);
        $this->authorize('review', $application);

        $recalculate->handle($application, isset($data['encourage_bonus']) ? (float) $data['encourage_bonus'] : null, $request->has('encourage_bonus'));

        return $this->toast('Đã tính lại điểm.');
    }

    /** @return array<string, mixed> */
    private function listRow(Application $a): array
    {
        $applicant = $a->case?->applicant;

        return [
            'id' => $a->id,
            'applicant_uuid' => $applicant?->uuid,
            'applicant_code' => $applicant?->code,
            'applicant_name' => $applicant?->full_name,
            'owner' => $a->case?->owner?->name,
            'major' => $a->major?->name,
            'method' => $a->cycleMethod?->display_name ?: $a->cycleMethod?->method?->name,
            'combination' => $a->combination?->code,
            'round' => $a->round?->name,
            'preference_order' => $a->preference_order,
            'raw_score' => $a->raw_score === null ? null : (float) $a->raw_score,
            'priority_bonus' => $a->priority_bonus === null ? null : (float) $a->priority_bonus,
            'final_score' => $a->final_score === null ? null : (float) $a->final_score,
            'meets_minimum' => $a->score_breakdown['meets_minimum'] ?? null,
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'status_tone' => $a->status->tone(),
        ];
    }

    /** @return array<string, mixed>|null quota of the major in the cycle, with what is already admitted */
    private function quota(int $majorId, int $cycleId): ?array
    {
        $quota = MajorQuota::query()->where('major_id', $majorId)->where('cycle_id', $cycleId)->first();

        if ($quota === null) {
            return null;
        }

        $count = fn (array $statuses) => Application::query()->where('major_id', $majorId)->whereHas('case', fn (Builder $q) => $q->where('cycle_id', $cycleId))
            ->whereIn('status', $statuses)->count();

        return [
            'total' => $quota->quota_total,
            'direct' => $quota->quota_direct,
            'by_score' => max(0, $quota->quota_total - $quota->quota_direct),
            'min_threshold' => $quota->min_threshold === null ? null : (float) $quota->min_threshold,
            'admitted' => $count(['admitted', 'confirmed']),
            'confirmed' => $count(['confirmed']),
        ];
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/applications');
    }
}
