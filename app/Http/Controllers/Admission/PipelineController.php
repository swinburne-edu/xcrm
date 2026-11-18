<?php

namespace App\Http\Controllers\Admission;

use App\Domain\Admission\Actions\DeletePipeline;
use App\Domain\Admission\Actions\DeleteStage;
use App\Domain\Admission\Actions\SavePipeline;
use App\Domain\Admission\Actions\SaveStage;
use App\Domain\Admission\Enums\StageType;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Cycle\Enums\CycleLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\PipelineRequest;
use App\Http\Requests\Admission\StageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/pipelines: the pipelines of the organization and their stages (screen "Pipeline").
 */
class PipelineController extends Controller
{
    private const REQUIRABLE_LABELS = [
        'primary_major_id' => 'Ngành quan tâm chính', 'source_id' => 'Nguồn', 'campaign_id' => 'Chiến dịch',
        'temperature' => 'Mức độ quan tâm', 'owner_id' => 'Người phụ trách', 'next_action_at' => 'Ngày hành động tiếp theo',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Pipeline::class);

        $caseCounts = AdmissionCase::query()->withoutGlobalScopes()->where('organization_id', $request->user()->organization_id)
            ->selectRaw('stage_id, count(*) as total')->groupBy('stage_id')->pluck('total', 'stage_id');

        $pipelines = Pipeline::query()->with('stages')->orderBy('level')->orderByDesc('is_default')->orderBy('name')->get()
            ->map(fn (Pipeline $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'level' => $p->level->value,
                'level_label' => $p->level->label(),
                'is_default' => $p->is_default,
                'cases_count' => $p->stages->sum(fn (PipelineStage $s) => (int) ($caseCounts[$s->id] ?? 0)),
                'stages' => $p->stages->map(fn (PipelineStage $s) => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'name' => $s->name,
                    'color' => $s->color,
                    'sort_order' => $s->sort_order,
                    'type' => $s->type->value,
                    'type_label' => $s->type->label(),
                    'probability' => $s->probability,
                    'sla_hours' => $s->sla_hours,
                    'required_fields' => $s->required_fields ?? [],
                    'cases_count' => (int) ($caseCounts[$s->id] ?? 0),
                ])->all(),
            ])->all();

        return Inertia::render('Pipelines/Index', [
            'pipelines' => $pipelines,
            'options' => [
                'levels' => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], CycleLevel::cases()),
                'types' => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], StageType::cases()),
                'colors' => StageRequest::COLORS,
                'requirable' => array_map(fn ($f) => ['value' => $f, 'label' => self::REQUIRABLE_LABELS[$f] ?? $f], PipelineStage::REQUIRABLE),
            ],
            'can' => ['manage' => $request->user()->can('create', Pipeline::class)],
        ]);
    }

    public function store(PipelineRequest $request, SavePipeline $save): RedirectResponse
    {
        $pipeline = $save->handle(null, $request->validated());

        return $this->back("Đã tạo pipeline {$pipeline->name}.");
    }

    public function update(PipelineRequest $request, int $id, SavePipeline $save): RedirectResponse
    {
        $pipeline = Pipeline::query()->findOrFail($id);
        $save->handle($pipeline, $request->validated());

        return $this->back("Đã cập nhật pipeline {$pipeline->name}.");
    }

    public function destroy(int $id, DeletePipeline $delete): RedirectResponse
    {
        $pipeline = Pipeline::query()->findOrFail($id);
        $this->authorize('delete', $pipeline);

        $delete->handle($pipeline);

        return $this->back("Đã xoá pipeline {$pipeline->name}.");
    }

    public function storeStage(StageRequest $request, int $id, SaveStage $save): RedirectResponse
    {
        $pipeline = Pipeline::query()->findOrFail($id);
        $stage = $save->handle($pipeline, null, $request->validated());

        return $this->back("Đã thêm giai đoạn {$stage->name}.");
    }

    public function updateStage(StageRequest $request, int $id, int $stageId, SaveStage $save): RedirectResponse
    {
        $pipeline = Pipeline::query()->findOrFail($id);
        $stage = $pipeline->stages()->findOrFail($stageId);
        $save->handle($pipeline, $stage, $request->validated());

        return $this->back("Đã cập nhật giai đoạn {$stage->name}.");
    }

    public function destroyStage(int $id, int $stageId, DeleteStage $delete): RedirectResponse
    {
        $pipeline = Pipeline::query()->findOrFail($id);
        $this->authorize('update', $pipeline);
        $stage = $pipeline->stages()->findOrFail($stageId);

        $delete->handle($pipeline, $stage);

        return $this->back("Đã xoá giai đoạn {$stage->name}.");
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
