<?php

namespace App\Domain\Admission\Services;

use App\Domain\Admission\Actions\TransitionApplication;
use App\Domain\Admission\Enums\ApplicationStatus;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Applicant\Enums\DocumentStatus;
use App\Domain\Applicant\Enums\DocumentType;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantDocument;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Props of the "Nguyện vọng" and "Giấy tờ" tabs of the applicant page: the nguyện vọng of the case in the
 * selected cycle with what the user may do to each, the choices of the add form, and the documents.
 */
class ApplicationPanel
{
    public const ACTION_LABELS = [
        'submit' => 'Nộp', 'eligible' => 'Đủ điều kiện', 'ineligible' => 'Không đủ điều kiện', 'conditional' => 'Trúng tuyển có điều kiện',
        'admit' => 'Trúng tuyển', 'reject' => 'Không trúng tuyển', 'confirm' => 'Xác nhận nhập học', 'withdraw' => 'Rút',
    ];

    /** @return array<string, mixed> */
    public function applications(Applicant $applicant, ?AdmissionCycle $cycle, User $user): array
    {
        $case = $cycle === null ? null : AdmissionCase::query()->where('applicant_id', $applicant->id)->where('cycle_id', $cycle->id)->first();

        if ($case === null) {
            return ['cycle' => $cycle?->name, 'case_uuid' => null, 'closed' => (bool) $cycle?->isClosed(), 'items' => [], 'options' => null, 'can_create' => false];
        }

        $approved = $this->approvedTypes($applicant);
        $items = Application::query()
            ->with(['major:id,name', 'cycleMethod.method:id,name', 'combination:id,code', 'round:id,name', 'case.cycle'])
            ->where('case_id', $case->id)
            ->orderBy('round_id')->orderBy('preference_order')
            ->get()
            ->map(fn (Application $a) => $this->row($a, $user, $approved))
            ->all();

        $closed = (bool) $cycle->isClosed();

        return [
            'cycle' => $cycle->name,
            'case_uuid' => $case->uuid,
            'closed' => $closed,
            'items' => $items,
            'options' => $closed ? null : $this->options($cycle),
            'can_create' => ! $closed && $user->can('application.create') && $user->can('update', $case) && count($items) < Application::MAX_PER_CASE,
        ];
    }

    /**
     * @param  list<string>  $approvedTypes
     * @return array<string, mixed>
     */
    public function row(Application $a, User $user, array $approvedTypes = []): array
    {
        $required = array_values(array_filter($a->cycleMethod?->required_documents ?? []));
        $breakdown = $a->score_breakdown ?? [];
        $actions = [];

        foreach (TransitionApplication::ACTIONS as $action => [$from]) {
            $allowed = in_array($a->status, $from, true)
                && ($user->can(in_array($action, TransitionApplication::REVIEW_ACTIONS, true) ? 'review' : 'update', $a));

            if ($allowed) {
                $actions[] = ['action' => $action, 'label' => self::ACTION_LABELS[$action]];
            }
        }

        return [
            'id' => $a->id,
            'case_uuid' => $a->case?->uuid,
            'preference_order' => $a->preference_order,
            'round_id' => $a->round_id,
            'round' => $a->round?->name,
            'major_id' => $a->major_id,
            'major' => $a->major?->name,
            'cycle_method_id' => $a->cycle_method_id,
            'method' => $a->cycleMethod?->display_name ?: $a->cycleMethod?->method?->name,
            'combination_id' => $a->combination_id,
            'combination' => $a->combination?->code,
            'raw_score' => $a->raw_score === null ? null : (float) $a->raw_score,
            'priority_bonus' => $a->priority_bonus === null ? null : (float) $a->priority_bonus,
            'encourage_bonus' => $a->encourage_bonus === null ? null : (float) $a->encourage_bonus,
            'final_score' => $a->final_score === null ? null : (float) $a->final_score,
            'missing' => $breakdown['missing'] ?? [],
            'meets_minimum' => $breakdown['meets_minimum'] ?? null,
            'breakdown' => $breakdown,
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'status_tone' => $a->status->tone(),
            'submitted_at' => $a->submitted_at?->toIso8601String(),
            'required_documents' => array_map(fn (string $t) => ['type' => $t, 'label' => DocumentType::tryFrom($t)?->label() ?? $t, 'approved' => in_array($t, $approvedTypes, true)], $required),
            'actions' => $actions,
            'can' => [
                'update' => $a->status === ApplicationStatus::Draft && $user->can('update', $a),
                'delete' => $user->can('delete', $a),
                'score' => $a->status !== ApplicationStatus::Withdrawn && $user->can('review', $a),
            ],
        ];
    }

    /** @return array<string, mixed> choices of the add / edit dialog */
    private function options(AdmissionCycle $cycle): array
    {
        $majors = Major::query()
            ->where('is_active', true)
            ->whereHas('cycleMethods', fn (Builder $q) => $q->where('cycle_id', $cycle->id))
            ->with([
                'cycleMethods' => fn ($q) => $q->where('cycle_id', $cycle->id)->with('method:id,name'),
                'combinations' => fn ($q) => $q->wherePivot('cycle_id', $cycle->id),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Major $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'methods' => $m->cycleMethods->map(fn (CycleMethod $c) => [
                    'id' => $c->id,
                    'label' => $c->display_name ?: $c->method?->name,
                    'direct' => $c->is_direct,
                    'needs_combination' => ! $c->is_direct && in_array($c->score_formula['source'] ?? null, ['transcript', 'thpt_exam'], true),
                ])->values()->all(),
                'combinations' => $m->combinations->map(fn ($c) => ['id' => $c->id, 'code' => $c->code])->values()->all(),
            ])->values()->all();

        return [
            'majors' => $majors,
            'rounds' => AdmissionRound::query()->where('cycle_id', $cycle->id)->orderBy('sort_order')->orderBy('id')->get(['id', 'name'])
                ->map(fn (AdmissionRound $r) => ['id' => $r->id, 'name' => $r->name])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function documents(Applicant $applicant, User $user): array
    {
        $items = ApplicantDocument::query()
            ->with(['media', 'reviewer:id,name'])
            ->where('applicant_id', $applicant->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (ApplicantDocument $d) use ($user) {
                $media = $d->getFirstMedia(ApplicantDocument::COLLECTION);

                return [
                    'id' => $d->id,
                    'doc_type' => $d->doc_type->value,
                    'type_label' => $d->doc_type->label(),
                    'status' => $d->status->value,
                    'status_label' => $d->status->label(),
                    'status_tone' => $d->status->tone(),
                    'note' => $d->note,
                    'file_name' => $media?->file_name,
                    'size' => $media?->size,
                    'mime' => $media?->mime_type,
                    'uploaded_at' => $d->created_at?->toIso8601String(),
                    'reviewer' => $d->reviewer?->name,
                    'reviewed_at' => $d->reviewed_at?->toIso8601String(),
                    'can_delete' => $user->can('applicant.update') && $d->status !== DocumentStatus::Approved,
                ];
            })->all();

        return [
            'items' => $items,
            'types' => array_map(fn (DocumentType $t) => ['value' => $t->value, 'label' => $t->label()], DocumentType::cases()),
            'can' => ['upload' => $user->can('applicant.update'), 'review' => $user->can('application.review')],
        ];
    }

    /** @return list<string> document types that already have an approved file */
    private function approvedTypes(Applicant $applicant): array
    {
        return ApplicantDocument::query()->where('applicant_id', $applicant->id)->where('status', DocumentStatus::Approved->value)->pluck('doc_type')
            ->map(fn ($t) => $t instanceof DocumentType ? $t->value : (string) $t)->unique()->values()->all();
    }
}
