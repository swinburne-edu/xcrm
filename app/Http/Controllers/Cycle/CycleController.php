<?php

namespace App\Http\Controllers\Cycle;

use App\Domain\Catalog\Models\AdmissionMethod;
use App\Domain\Cycle\Actions\CloneCycle;
use App\Domain\Cycle\Actions\DeleteCycle;
use App\Domain\Cycle\Actions\SaveCycle;
use App\Domain\Cycle\Actions\SetCurrentCycle;
use App\Domain\Cycle\Enums\CycleLevel;
use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Enums\RoundType;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Cycle\Services\ScoreFormulaValidator;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cycle\CloneCycleRequest;
use App\Http\Requests\Cycle\CycleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/cycles: admission cycles, and the detail page with rounds and methods.
 * Rounds and methods have their own controllers (CycleRoundController, CycleMethodController).
 */
class CycleController extends Controller
{
    private const SOURCE_LABELS = [
        'thpt_exam' => 'Thi tốt nghiệp THPT', 'transcript' => 'Học bạ THPT', 'competency_exam' => 'Đánh giá năng lực / tư duy',
        'direct' => 'Xét tuyển thẳng', 'talent' => 'Năng khiếu',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AdmissionCycle::class);

        $cycles = ListQuery::for(AdmissionCycle::query()->withCount(['rounds', 'methods']), $request)
            ->filters(Filter::search('q', ['name']), Filter::exact('status'), Filter::exact('level'))
            ->sorts(['year', 'name', 'status'])
            ->defaultSort('-year')
            ->toProps(fn (AdmissionCycle $cycle) => $this->row($cycle));

        return Inertia::render('Cycles/Index', [
            'cycles' => $cycles,
            'levelOptions' => self::options(CycleLevel::class),
            'statusOptions' => self::options(CycleStatus::class),
            'can' => ['create' => $request->user()->can('create', AdmissionCycle::class)],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $cycle = AdmissionCycle::query()->withCount(['rounds', 'methods'])->findOrFail($id);
        $this->authorize('view', $cycle);

        $user = $request->user();
        $usedMethodIds = $cycle->methods()->pluck('method_id');

        return Inertia::render('Cycles/Show', [
            'cycle' => $this->row($cycle),
            'rounds' => $cycle->rounds->map(fn (AdmissionRound $round) => $this->roundRow($round))->all(),
            'methods' => $cycle->methods()->with('method')->get()->map(fn (CycleMethod $m) => $this->methodRow($m))->all(),
            'methodOptions' => AdmissionMethod::query()->orderBy('code')->get()
                ->map(fn (AdmissionMethod $m) => ['value' => (string) $m->id, 'label' => "{$m->code} – {$m->name}", 'used' => $usedMethodIds->contains($m->id)])->all(),
            'levelOptions' => self::options(CycleLevel::class),
            'statusOptions' => self::options(CycleStatus::class),
            'roundTypeOptions' => self::options(RoundType::class),
            'formulaOptions' => [
                'sources' => array_map(fn (string $s) => ['value' => $s, 'label' => self::SOURCE_LABELS[$s]], ScoreFormulaValidator::SOURCES),
                'aggregates' => [['value' => 'avg', 'label' => 'Trung bình'], ['value' => 'sum', 'label' => 'Tổng']],
                'terms' => ['10', '11', '12', '10-1', '10-2', '11-1', '11-2', '12-1', '12-2'],
                'documents' => [
                    ['value' => 'transcript', 'label' => 'Học bạ'], ['value' => 'citizen_id', 'label' => 'CCCD'],
                    ['value' => 'priority_proof', 'label' => 'Minh chứng ưu tiên'], ['value' => 'certificate', 'label' => 'Chứng chỉ'],
                    ['value' => 'photo', 'label' => 'Ảnh'], ['value' => 'other', 'label' => 'Khác'],
                ],
                'maxMethods' => AdmissionCycle::MAX_METHODS,
            ],
            'can' => [
                'update' => $user->can('update', $cycle),
                'delete' => $user->can('delete', $cycle) && $cycle->status === CycleStatus::Preparing && ! $cycle->is_current,
                'reopen' => $user->can('reopen', $cycle),
                'clone' => $user->can('create', AdmissionCycle::class),
            ],
        ]);
    }

    public function store(CycleRequest $request, SaveCycle $save): RedirectResponse
    {
        $cycle = $save->handle(null, $request->validated());

        return $this->toast('success', "Đã tạo {$cycle->name}.", "/settings/cycles/{$cycle->id}");
    }

    public function update(CycleRequest $request, int $id, SaveCycle $save): RedirectResponse
    {
        $cycle = AdmissionCycle::query()->findOrFail($id);
        $this->authorize('update', $cycle);

        $save->handle($cycle, $request->validated());

        return $this->toast('success', "Đã cập nhật {$cycle->name}.");
    }

    public function destroy(int $id, DeleteCycle $delete): RedirectResponse
    {
        $cycle = AdmissionCycle::query()->findOrFail($id);
        $this->authorize('delete', $cycle);

        $delete->handle($cycle);

        return $this->toast('success', "Đã xoá {$cycle->name}.", '/settings/cycles');
    }

    /** Flow F1: new cycle in preparation copied from this one (rounds, methods, quotas, major links). */
    public function clone(CloneCycleRequest $request, int $id, CloneCycle $clone): RedirectResponse
    {
        $source = AdmissionCycle::query()->findOrFail($id);

        $result = $clone->handle($source, $request->validated());
        $c = $result['counts'];

        return $this->toast(
            'success',
            "Đã sao chép {$source->name} sang {$result['cycle']->name}: {$c['rounds']} đợt, {$c['methods']} phương thức, {$c['quotas']} chỉ tiêu ngành.",
            "/settings/cycles/{$result['cycle']->id}",
        );
    }

    /** Rule R10: becomes the only current cycle of its level, and is activated. */
    public function setCurrent(int $id, SetCurrentCycle $setCurrent): RedirectResponse
    {
        $cycle = AdmissionCycle::query()->findOrFail($id);
        $this->authorize('update', $cycle);

        $setCurrent->handle($cycle);

        return $this->toast('success', "{$cycle->name} là mùa hiện tại.");
    }

    /** Rule R8 makes a closed cycle read-only; reopening is the one change allowed. */
    public function reopen(int $id): RedirectResponse
    {
        $cycle = AdmissionCycle::query()->findOrFail($id);
        $this->authorize('reopen', $cycle);

        $cycle->forceFill(['status' => CycleStatus::Active])->save();

        return $this->toast('success', "Đã mở lại {$cycle->name}.");
    }

    /** @return array<string, mixed> */
    private function row(AdmissionCycle $cycle): array
    {
        return [
            'id' => $cycle->id,
            'name' => $cycle->name,
            'year' => $cycle->year,
            'level' => $cycle->level->value,
            'level_label' => $cycle->level->label(),
            'status' => $cycle->status->value,
            'status_label' => $cycle->status->label(),
            'is_current' => $cycle->is_current,
            'start_date' => $cycle->start_date?->format('Y-m-d'),
            'end_date' => $cycle->end_date?->format('Y-m-d'),
            'target_enrolled' => $cycle->target_enrolled,
            'regulation_ref' => $cycle->regulation_ref,
            'rounds_count' => $cycle->rounds_count ?? null,
            'methods_count' => $cycle->methods_count ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function roundRow(AdmissionRound $round): array
    {
        return [
            'id' => $round->id,
            'name' => $round->name,
            'type' => $round->type?->value,
            'type_label' => $round->type?->label(),
            'apply_start' => DisplayTime::toInput($round->apply_start),
            'apply_end' => DisplayTime::toInput($round->apply_end),
            'result_date' => $round->result_date?->format('Y-m-d'),
            'confirm_deadline' => DisplayTime::toInput($round->confirm_deadline),
            'sort_order' => $round->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    private function methodRow(CycleMethod $m): array
    {
        $formula = $m->score_formula;

        return [
            'id' => $m->id,
            'method_id' => $m->method_id,
            'method_code' => $m->method?->code,
            'method_name' => $m->method?->name,
            'display_name' => $m->display_name,
            'is_direct' => $m->is_direct,
            'score_formula' => $formula,
            'conditions' => $m->conditions,
            'required_documents' => $m->required_documents ?? [],
            'formula_summary' => $formula ? self::summarize($formula) : ($m->is_direct ? 'Xét tuyển thẳng' : null),
        ];
    }

    /**
     * @param  array<string, mixed>  $formula
     */
    private static function summarize(array $formula): string
    {
        $parts = [self::SOURCE_LABELS[$formula['source'] ?? ''] ?? ($formula['source'] ?? '?')];

        if (! empty($formula['terms'])) {
            $parts[] = 'kỳ '.implode(', ', $formula['terms']);
        }

        if (isset($formula['aggregate'])) {
            $parts[] = $formula['aggregate'] === 'avg' ? 'trung bình' : 'tổng';
        }

        if (isset($formula['scale_to'])) {
            $parts[] = 'thang '.$formula['scale_to'];
        }

        if (isset($formula['min_total'])) {
            $parts[] = 'sàn '.$formula['min_total'];
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     * @return list<array{value: string, label: string}>
     */
    private static function options(string $enum): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $enum::cases());
    }

    private function toast(string $type, string $message, ?string $to = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return $to ? redirect($to) : back(fallback: '/settings/cycles');
    }
}
