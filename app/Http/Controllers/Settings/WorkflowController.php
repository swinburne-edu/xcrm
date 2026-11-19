<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Admission\Enums\CaseStatus;
use App\Domain\Admission\Enums\Temperature;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Communication\Models\MessageTemplate;
use App\Domain\Major\Models\Major;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Workflow\Actions\SaveWorkflow;
use App\Domain\Workflow\Enums\WorkflowActionType;
use App\Domain\Workflow\Enums\WorkflowObject;
use App\Domain\Workflow\Enums\WorkflowTrigger;
use App\Domain\Workflow\Models\Workflow;
use App\Domain\Workflow\Models\WorkflowRun;
use App\Domain\Workflow\Services\WorkflowSchema;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WorkflowRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** /settings/workflows (task 3.2): simple automations (trigger, conditions, actions) and their latest runs. Authorization: WorkflowPolicy. */
class WorkflowController extends Controller
{
    public function index(Request $request, WorkflowSchema $schema): Response
    {
        $this->authorize('viewAny', Workflow::class);

        $opt = fn ($rows) => collect($rows)->map(fn ($r) => ['value' => $r->id, 'label' => $r->name])->all();
        $enum = fn (array $cases) => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], $cases);

        return Inertia::render('Workflows/Index', [
            'workflows' => Workflow::query()->orderBy('name')->get()->map(fn (Workflow $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'object' => $w->object->value,
                'object_label' => $w->object->label(),
                'trigger' => $w->trigger,
                'trigger_label' => WorkflowTrigger::from($w->trigger['type'])->label(),
                'conditions' => $w->conditions ?? [],
                'actions' => $w->actions,
                'is_active' => $w->is_active,
                'runs' => $w->runs()->limit(5)->get()->map(fn (WorkflowRun $r) => [
                    'id' => $r->id,
                    'status' => $r->status->value,
                    'status_label' => $r->status->label(),
                    'subject_type' => $r->subject_type,
                    'subject_id' => $r->subject_id,
                    'log' => $r->log ?? [],
                    'ran_at' => str_replace('T', ' ', (string) DisplayTime::toInput($r->ran_at)),
                ])->all(),
            ])->all(),
            'options' => [
                'objects' => $enum(WorkflowObject::cases()),
                'triggers' => $enum(WorkflowTrigger::cases()),
                'operators' => collect(WorkflowSchema::OPERATORS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                'action_types' => array_map(fn (WorkflowActionType $t) => [
                    'value' => $t->value,
                    'label' => $t->label(),
                    'objects' => array_values(array_map(fn ($o) => $o->value, array_filter(WorkflowObject::cases(), fn ($o) => $t->supports($o)))),
                ], WorkflowActionType::cases()),
                'fields' => collect(WorkflowObject::cases())->mapWithKeys(fn ($o) => [$o->value => collect($schema->fields($o))->map(fn ($f, $key) => ['key' => $key] + $f)->values()->all()])->all(),
                'settable' => collect(WorkflowSchema::SETTABLE)->map(fn ($k) => ['value' => $k, 'label' => $k === 'temperature' ? 'Mức độ quan tâm' : 'Ngành quan tâm chính'])->all(),
                'lists' => [
                    'stages' => PipelineStage::query()->orderBy('pipeline_id')->orderBy('sort_order')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
                    'statuses' => $enum(CaseStatus::cases()),
                    'temperatures' => $enum(Temperature::cases()),
                    'sources' => $opt(LeadSource::query()->orderBy('name')->get(['id', 'name'])),
                    'campaigns' => $opt(Campaign::query()->orderBy('name')->get(['id', 'name'])),
                    'majors' => $opt(Major::query()->orderBy('name')->get(['id', 'name'])),
                ],
                'templates' => $opt(MessageTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])),
            ],
            'can' => ['manage' => $request->user()->can('create', Workflow::class)],
        ]);
    }

    public function store(WorkflowRequest $request, SaveWorkflow $save): RedirectResponse
    {
        $workflow = $save->handle(null, $request->validated());

        return $this->toast("Đã tạo workflow {$workflow->name}.");
    }

    public function update(WorkflowRequest $request, int $id, SaveWorkflow $save): RedirectResponse
    {
        $workflow = Workflow::query()->findOrFail($id);
        $save->handle($workflow, $request->validated());

        return $this->toast("Đã cập nhật workflow {$workflow->name}.");
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $workflow = Workflow::query()->findOrFail($id);
        $this->authorize('update', $workflow);
        $workflow->update(['is_active' => $request->boolean('is_active')]);

        return $this->toast($workflow->is_active ? "Đã bật {$workflow->name}." : "Đã tắt {$workflow->name}.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $workflow = Workflow::query()->findOrFail($id);
        $this->authorize('delete', $workflow);
        $workflow->delete();

        return $this->toast("Đã xoá workflow {$workflow->name}.");
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
