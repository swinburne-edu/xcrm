<?php

namespace App\Http\Controllers\Admission;

use App\Domain\Admission\Actions\AssignCase;
use App\Domain\Admission\Actions\BulkCaseAction;
use App\Domain\Admission\Actions\ClaimCase;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Actions\MoveCaseStage;
use App\Domain\Admission\Actions\UpdateAdmissionCase;
use App\Domain\Admission\Enums\CaseStatus;
use App\Domain\Admission\Enums\Temperature;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\LostReason;
use App\Domain\Catalog\Models\Tag;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Shared\Services\SensitiveData;
use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\Like;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\CaseBulkRequest;
use App\Http\Requests\Admission\CaseMoveRequest;
use App\Http\Requests\Admission\CaseStoreRequest;
use App\Http\Requests\Admission\CaseUpdateRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /cases: admission cases of the cycle selected in the header, as a Kanban board (/cases) or a table (/cases/table)
 * with the same filters; quick-view JSON for the drawer; drag-and-drop stage moves, assign / claim from the
 * shared pool, and bulk actions. The rules live in the Admission actions; permissions in CasePolicy.
 */
class CaseController extends Controller
{
    public const COLUMN_SIZE = 20;

    public function __construct(private readonly SensitiveData $sensitive) {}

    public function index(Request $request, CurrentCycle $currentCycle): Response
    {
        return $this->screen($request, $currentCycle, 'kanban');
    }

    public function table(Request $request, CurrentCycle $currentCycle): Response
    {
        return $this->screen($request, $currentCycle, 'table');
    }

    /** More cards of one Kanban column ("tải thêm"), same filters as the board. */
    public function column(Request $request, CurrentCycle $currentCycle): JsonResponse
    {
        $this->authorize('viewAny', AdmissionCase::class);

        $list = $this->listQuery($request, $currentCycle->id());
        $query = $list->query()->where('admission_cases.stage_id', $request->integer('stage_id'));

        return response()->json([
            'total' => (clone $query)->reorder()->count(),
            'cards' => $this->cards($query->with($this->with())->offset(max(0, $request->integer('offset')))->limit(self::COLUMN_SIZE)->get()),
        ]);
    }

    /** Quick-view data for the record drawer. */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $case = AdmissionCase::query()->with([...$this->with(), 'pipeline.stages', 'team:id,name', 'source:id,name', 'campaign:id,name', 'lostReason:id,name', 'stageHistories.fromStage:id,name', 'stageHistories.toStage:id,name', 'stageHistories.user:id,name', 'cycle'])
            ->where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $case);

        $user = $request->user();
        $applicant = $case->applicant;

        return response()->json(['case' => $this->card($case) + [
            'applicant_uuid' => $applicant?->uuid,
            'phone' => $this->sensitive->phone($applicant?->phone, $user),
            'email' => $applicant?->email,
            'primary_major_id' => $case->primary_major_id,
            'source_id' => $case->source_id,
            'source_name' => $case->source?->name,
            'campaign_id' => $case->campaign_id,
            'campaign_name' => $case->campaign?->name,
            'team_name' => $case->team?->name,
            'next_action_input' => DisplayTime::toInput($case->next_action_at),
            'assigned_at' => $case->assigned_at?->toIso8601String(),
            'first_contacted_at' => $case->first_contacted_at?->toIso8601String(),
            'last_activity_at' => $case->last_activity_at?->toIso8601String(),
            'lost_reason' => $case->lostReason?->name,
            'lost_note' => $case->lost_note,
            'enrolled_at' => $case->enrolled_at?->toIso8601String(),
            'cycle_closed' => $case->cycle->isClosed(),
            'stages' => $case->pipeline->stages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'color' => $s->color, 'type' => $s->type->value, 'current' => $s->id === $case->stage_id])->all(),
            'history' => $case->stageHistories->reverse()->take(8)->map(fn ($h) => [
                'from' => $h->fromStage?->name, 'to' => $h->toStage?->name, 'by' => $h->user?->name,
                'at' => $h->changed_at->toIso8601String(), 'hours' => $h->hours_in_previous === null ? null : (float) $h->hours_in_previous,
            ])->values()->all(),
            'can' => [
                'update' => $user->can('update', $case),
                'assign' => $user->can('assign', $case),
                'claim' => $user->can('claim', $case),
            ],
        ]]);
    }

    public function store(CaseStoreRequest $request, CurrentCycle $currentCycle, CreateAdmissionCase $create): RedirectResponse
    {
        $cycle = $currentCycle->get() ?? abort(422, 'Chưa có mùa tuyển sinh.');
        $applicant = Applicant::query()->findOrFail($request->integer('applicant_id'));
        $data = array_filter($request->safe()->only(['temperature', 'primary_major_id', 'source_id', 'campaign_id']), fn ($v) => $v !== null);

        if ($request->ownerChosen()) {
            $data['owner_id'] = $request->input('owner_id');
        }

        $create->handle($applicant, $cycle, $data, $request->user());

        return $this->toast("Đã tạo hồ sơ cho {$applicant->full_name}.", 'success', "/cases/table?filter[q]={$applicant->code}&filter[owner]=all");
    }

    public function update(CaseUpdateRequest $request, string $uuid, UpdateAdmissionCase $update): RedirectResponse
    {
        $case = AdmissionCase::query()->where('uuid', $uuid)->firstOrFail();
        $update->handle($case, $request->validated());

        return $this->toast('Đã cập nhật hồ sơ.');
    }

    public function move(CaseMoveRequest $request, string $uuid, MoveCaseStage $move): RedirectResponse
    {
        $case = AdmissionCase::query()->where('uuid', $uuid)->firstOrFail();
        $stage = $request->stage();

        $move->handle($case, $stage, $request->user(), $request->safe()->only(['lost_reason_id', 'lost_note', 'enrolled_major_id']));

        return $this->toast("Đã chuyển sang {$stage->name}.");
    }

    public function assign(Request $request, string $uuid, AssignCase $assign): RedirectResponse
    {
        $case = AdmissionCase::query()->with('cycle')->where('uuid', $uuid)->firstOrFail();
        $this->authorize('assign', $case);

        $data = $request->validate([
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
            'team_id' => ['nullable', 'integer'],
        ]);

        $assign->handle($case, ($data['owner_id'] ?? null) === null ? null : User::query()->findOrFail($data['owner_id']), $data['team_id'] ?? null, $request->user());

        return $this->toast(($data['owner_id'] ?? null) === null ? 'Đã trả hồ sơ về kho chung.' : 'Đã giao hồ sơ.');
    }

    public function claim(Request $request, string $uuid, ClaimCase $claim): RedirectResponse
    {
        $case = AdmissionCase::query()->with('cycle')->where('uuid', $uuid)->firstOrFail();
        $this->authorize('claim', $case);

        $claim->handle($case, $request->user());

        return $this->toast('Đã nhận hồ sơ.');
    }

    public function bulk(CaseBulkRequest $request, BulkCaseAction $bulk): RedirectResponse
    {
        $result = $bulk->handle($request->string('action')->toString(), $request->input('ids'), $request->safe()->except(['action', 'ids']), $request->user());

        Inertia::flash('bulk', $result);

        $failed = count($result['failed']);

        return $this->toast(
            $failed === 0 ? "Đã xử lý {$result['done']} hồ sơ." : "Đã xử lý {$result['done']} hồ sơ, {$failed} hồ sơ không thực hiện được.",
            $failed === 0 ? 'success' : 'warning',
        );
    }

    private function screen(Request $request, CurrentCycle $currentCycle, string $mode): Response
    {
        $this->authorize('viewAny', AdmissionCase::class);

        $user = $request->user();
        $cycle = $currentCycle->get();
        $list = $this->listQuery($request, $cycle?->id);

        if ($mode === 'table') {
            $list->query()->with($this->with());
            $cases = $list->toProps(fn (AdmissionCase $c) => $this->card($c));
        } else {
            $cases = ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0, 'from' => null, 'to' => null], 'query' => $list->state()];
        }

        if (($request->query('filter')['owner'] ?? null) === 'all') {
            $cases['query']['filters']['owner'] = 'all';
        }

        return Inertia::render('Cases/Index', [
            'mode' => $mode,
            'cycle' => $cycle?->only(['id', 'name']),
            'cases' => $cases,
            'board' => $mode === 'kanban' ? $this->board($list, $cycle) : null,
            'options' => [
                'sources' => LeadSource::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->name])->all(),
                'temperatures' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], Temperature::cases()),
                'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], CaseStatus::cases()),
                'tags' => Tag::query()->orderBy('name')->get(['id', 'name'])->map(fn ($t) => ['value' => (string) $t->id, 'label' => $t->name])->all(),
                'lostReasons' => LostReason::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name'])->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->name])->all(),
                'stages' => $this->pipelineFor($cycle)?->stages->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->name, 'type' => $s->type->value])->all() ?? [],
            ],
            'can' => [
                'create' => $cycle !== null && ! $cycle->isClosed() && $user->can('create', AdmissionCase::class),
                'assign' => $user->can('case.assign'),
                'update' => $user->can('case.update'),
            ],
        ]);
    }

    /** Filters shared by the board, the table and "load more". */
    private function listQuery(Request $request, ?int $cycleId): ListQuery
    {
        $user = $request->user();
        $filter = $request->query('filter', []);

        // Counselors (own scope) start on "Của tôi" until they pick another view.
        if ($user->data_scope === DataScopeLevel::Own && is_array($filter) && ! array_key_exists('owner', $filter)) {
            $request->query->set('filter', $filter + ['owner' => 'mine']);
        }

        $ids = fn (mixed $v): ?array => ($list = array_values(array_filter(array_map('intval', is_array($v) ? $v : explode(',', (string) $v))))) === [] ? null : $list;
        $viaApplicant = fn (string $column) => fn (Builder $q, array $values) => $q->whereIn('admission_cases.applicant_id', Applicant::query()->withoutDataScope()->whereIn($column, $values)->select('id'));

        return ListQuery::for(AdmissionCase::query()->where('admission_cases.cycle_id', $cycleId ?? 0), $request)
            ->filters(
                Filter::callback('q', function (Builder $q, mixed $term): void {
                    $applicants = Applicant::query()->withoutDataScope();
                    Like::anyColumn($applicants, ['full_name', 'code', 'email', 'phone', 'phone_normalized'], mb_substr((string) $term, 0, 100));
                    $q->whereIn('admission_cases.applicant_id', $applicants->select('id'));
                }, fn (mixed $v) => is_string($v) && trim($v) !== '' ? trim($v) : null),
                Filter::callback('owner', function (Builder $q, mixed $value) use ($user): void {
                    match (true) {
                        $value === 'mine' => $q->where('admission_cases.owner_id', $user->id),
                        $value === 'pool' => $q->whereNull('admission_cases.owner_id'),
                        default => $q->where('admission_cases.owner_id', (int) $value),
                    };
                }, fn (mixed $v) => is_string($v) && ($v === 'mine' || $v === 'pool' || ctype_digit($v)) ? $v : null),
                Filter::exact('stage_id', 'admission_cases.stage_id'),
                Filter::exact('status', 'admission_cases.status'),
                Filter::exact('temperature', 'admission_cases.temperature'),
                Filter::exact('source_id', 'admission_cases.source_id'),
                Filter::exact('campaign_id', 'admission_cases.campaign_id'),
                Filter::exact('primary_major_id', 'admission_cases.primary_major_id'),
                Filter::callback('province_id', $viaApplicant('province_id'), $ids),
                Filter::callback('high_school_id', $viaApplicant('high_school_id'), $ids),
                Filter::callback('tag_id', fn (Builder $q, array $values) => $q->whereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $values)), $ids),
                Filter::callback('overdue', fn (Builder $q) => $q->where('admission_cases.status', 'open')->where('admission_cases.next_action_at', '<', now()),
                    fn (mixed $v) => in_array($v, ['1', 1, true, 'true'], true) ? true : null),
            )
            ->sorts(['last_activity_at', 'created_at', 'next_action_at', 'score'])
            ->defaultSort('-last_activity_at');
    }

    /**
     * Kanban columns: one per stage of the cycle's default pipeline, with the number of matching cases
     * and the first cards.
     *
     * @return array{pipeline: array{id: int, name: string}|null, columns: list<array<string, mixed>>}
     */
    private function board(ListQuery $list, mixed $cycle): array
    {
        $pipeline = $this->pipelineFor($cycle);

        if ($pipeline === null) {
            return ['pipeline' => null, 'columns' => []];
        }

        $filtered = $list->query()->where('admission_cases.pipeline_id', $pipeline->id);
        $counts = (clone $filtered)->reorder()->select('admission_cases.stage_id', DB::raw('count(*) as total'))->groupBy('admission_cases.stage_id')->pluck('total', 'stage_id');

        return [
            'pipeline' => ['id' => $pipeline->id, 'name' => $pipeline->name],
            'columns' => $pipeline->stages->map(fn ($stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'type' => $stage->type->value,
                'total' => (int) ($counts[$stage->id] ?? 0),
                'cards' => $this->cards((clone $filtered)->where('admission_cases.stage_id', $stage->id)->with($this->with())->limit(self::COLUMN_SIZE)->get()),
            ])->all(),
        ];
    }

    private function pipelineFor(mixed $cycle): ?Pipeline
    {
        if ($cycle === null) {
            return null;
        }

        $defaults = Pipeline::query()->with('stages')->where('is_default', true);

        return (clone $defaults)->where('level', $cycle->level->value)->first() ?? $defaults->first();
    }

    /** @return list<string> */
    private function with(): array
    {
        return ['applicant', 'applicant.highSchool:id,name', 'applicant.province:id,name', 'owner:id,name', 'primaryMajor:id,name', 'tags:id,name', 'stage:id,name,color,type'];
    }

    /**
     * @param  iterable<AdmissionCase>  $cases
     * @return list<array<string, mixed>>
     */
    private function cards(iterable $cases): array
    {
        $out = [];

        foreach ($cases as $case) {
            $out[] = $this->card($case);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function card(AdmissionCase $c): array
    {
        $applicant = $c->applicant;

        return [
            'id' => $c->uuid,
            'uuid' => $c->uuid,
            'applicant_name' => $applicant?->full_name,
            'applicant_code' => $applicant?->code,
            'high_school_name' => $applicant?->highSchool?->name,
            'province_name' => $applicant?->province?->name,
            'major_name' => $c->primaryMajor?->name,
            'stage_id' => $c->stage_id,
            'stage_name' => $c->stage?->name,
            'stage_color' => $c->stage?->color,
            'status' => $c->status->value,
            'owner_id' => $c->owner_id,
            'owner_name' => $c->owner?->name,
            'in_pool' => $c->owner_id === null,
            'temperature' => $c->temperature?->value,
            'score' => $c->score,
            'next_action_at' => $c->next_action_at?->toIso8601String(),
            'overdue' => $c->status === CaseStatus::Open && $c->next_action_at !== null && $c->next_action_at->isPast(),
            'last_activity_at' => $c->last_activity_at?->toIso8601String(),
            'tags' => $c->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all(),
        ];
    }

    private function toast(string $message, string $type = 'success', ?string $to = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return $to ? redirect($to) : back(fallback: '/cases');
    }
}
