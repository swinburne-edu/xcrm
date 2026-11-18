<?php

namespace App\Http\Controllers\Major;

use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Major\Actions\DeleteMajor;
use App\Domain\Major\Actions\SaveMajor;
use App\Domain\Major\Actions\SaveMajorCycleSetup;
use App\Domain\Major\Enums\MajorLevel;
use App\Domain\Major\Enums\ProgramType;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Major\MajorCycleSetupRequest;
use App\Http\Requests\Major\MajorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /majors: list, create/edit, and the detail page whose "Theo mùa" tab sets the quota,
 * subject combinations and admission methods of the major for one cycle.
 */
class MajorController extends Controller
{
    public function index(Request $request, CurrentCycle $currentCycle): Response
    {
        $this->authorize('viewAny', Major::class);

        $cycleId = $currentCycle->id();
        $query = Major::query()->with(['faculty:id,name', 'campus:id,name']);

        if ($cycleId !== null) {
            $query->addSelect(['majors.*', 'cycle_quota' => MajorQuota::query()->select('quota_total')
                ->whereColumn('major_quotas.major_id', 'majors.id')->where('cycle_id', $cycleId)->limit(1)]);
        }

        $majors = ListQuery::for($query, $request)
            ->filters(
                Filter::search('q', ['name', 'admission_code', 'moet_code']),
                Filter::exact('faculty_id'),
                Filter::exact('campus_id'),
                Filter::exact('level'),
                Filter::exact('program_type'),
                Filter::boolean('is_active'),
            )
            ->sorts(['name', 'admission_code', 'created_at'])
            ->defaultSort('name')
            ->toProps(fn (Major $m) => $this->row($m) + ['cycle_quota' => $m->getAttribute('cycle_quota')]);

        return Inertia::render('Majors/Index', [
            'majors' => $majors,
            'cycle' => $currentCycle->get()?->only(['id', 'name']),
            'levelOptions' => self::options(MajorLevel::class),
            'programTypeOptions' => self::options(ProgramType::class),
            'can' => ['create' => $request->user()->can('create', Major::class)],
        ]);
    }

    public function show(Request $request, CurrentCycle $currentCycle, int $id): Response
    {
        $major = Major::query()->with(['faculty:id,name', 'campus:id,name'])->findOrFail($id);
        $this->authorize('view', $major);

        $user = $request->user();
        $cycles = AdmissionCycle::query()->orderByDesc('year')->get();
        $selected = $request->integer('cycle_id') ? $cycles->firstWhere('id', $request->integer('cycle_id')) : null;
        $selected ??= $currentCycle->get();

        return Inertia::render('Majors/Show', [
            'major' => $this->row($major) + [
                'description' => $major->description,
                'career_info' => $major->career_info,
            ],
            'cycles' => $cycles->map(fn (AdmissionCycle $c) => ['id' => $c->id, 'name' => $c->name, 'status' => $c->status->value])->all(),
            'selectedCycleId' => $selected?->id,
            'setup' => $selected ? $this->setup($major, $selected) : null,
            'combinationOptions' => SubjectCombination::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (SubjectCombination $c) => ['id' => $c->id, 'label' => $c->code.' – '.$c->name])->all(),
            'methodOptions' => $selected ? $this->methodOptions($selected) : [],
            'levelOptions' => self::options(MajorLevel::class),
            'programTypeOptions' => self::options(ProgramType::class),
            'can' => [
                'update' => $user->can('update', $major),
                'delete' => $user->can('delete', $major),
                'setup' => $selected !== null && $user->can('update', $major) && $user->can('update', $selected),
            ],
        ]);
    }

    public function store(MajorRequest $request, SaveMajor $save): RedirectResponse
    {
        $major = $save->handle(null, $request->validated());

        return $this->toast("Đã thêm ngành {$major->name}.", "/majors/{$major->id}");
    }

    public function update(MajorRequest $request, int $id, SaveMajor $save): RedirectResponse
    {
        $major = Major::query()->findOrFail($id);
        $save->handle($major, $request->validated());

        return $this->toast("Đã cập nhật ngành {$major->name}.");
    }

    public function destroy(int $id, DeleteMajor $delete): RedirectResponse
    {
        $major = Major::query()->findOrFail($id);
        $this->authorize('delete', $major);

        $delete->handle($major);

        return $this->toast("Đã xoá ngành {$major->name}.", '/majors');
    }

    public function saveCycle(MajorCycleSetupRequest $request, int $id, int $cycleId, SaveMajorCycleSetup $save): RedirectResponse
    {
        $major = Major::query()->findOrFail($id);
        $cycle = AdmissionCycle::query()->findOrFail($cycleId);

        $save->handle($major, $cycle, $request->validated());

        return $this->toast("Đã lưu cấu hình {$major->name} cho {$cycle->name}.");
    }

    /** @return array<string, mixed> */
    private function row(Major $m): array
    {
        return [
            'id' => $m->id,
            'moet_code' => $m->moet_code,
            'admission_code' => $m->admission_code,
            'name' => $m->name,
            'faculty_id' => $m->faculty_id,
            'faculty_name' => $m->faculty?->name,
            'campus_id' => $m->campus_id,
            'campus_name' => $m->campus?->name,
            'program_type' => $m->program_type->value,
            'program_type_label' => $m->program_type->label(),
            'level' => $m->level->value,
            'level_label' => $m->level->label(),
            'duration_years' => $m->duration_years === null ? null : (float) $m->duration_years,
            'is_active' => $m->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function setup(Major $major, AdmissionCycle $cycle): array
    {
        $quota = MajorQuota::query()->where('major_id', $major->id)->where('cycle_id', $cycle->id)->first();

        return [
            'cycle_id' => $cycle->id,
            'configured' => $quota !== null,
            'quota_total' => $quota?->quota_total,
            'quota_direct' => $quota?->quota_direct ?? 0,
            'target_enrolled' => $quota?->target_enrolled,
            'tuition_fee' => $quota?->tuition_fee === null ? null : (int) $quota->tuition_fee,
            'min_threshold' => $quota?->min_threshold === null ? null : (float) $quota->min_threshold,
            'previous_cutoffs' => $quota?->previous_cutoffs ?? [],
            'scholarship_info' => $quota?->scholarship_info,
            'combination_ids' => DB::table('major_combinations')->where('major_id', $major->id)->where('cycle_id', $cycle->id)->pluck('combination_id')->map(fn ($v) => (int) $v)->all(),
            'cycle_method_ids' => DB::table('major_cycle_methods')->where('major_id', $major->id)
                ->whereIn('cycle_method_id', CycleMethod::query()->where('cycle_id', $cycle->id)->select('id'))
                ->pluck('cycle_method_id')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /** @return list<array{id: int, label: string, is_direct: bool}> */
    private function methodOptions(AdmissionCycle $cycle): array
    {
        return $cycle->methods()->with('method')->get()->map(fn (CycleMethod $m) => [
            'id' => $m->id,
            'label' => $m->display_name ?: ($m->method?->name ?? '—'),
            'is_direct' => $m->is_direct,
        ])->all();
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     * @return list<array{value: string, label: string}>
     */
    private static function options(string $enum): array
    {
        return array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $enum::cases());
    }

    private function toast(string $message, ?string $to = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $to ? redirect($to) : back(fallback: '/majors');
    }
}
