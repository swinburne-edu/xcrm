<?php

namespace App\Http\Controllers\Campaign;

use App\Domain\Campaign\Actions\SaveCampaign;
use App\Domain\Campaign\Actions\TransitionCampaign;
use App\Domain\Campaign\Enums\CampaignChannel;
use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Enums\CampaignType;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignCost;
use App\Domain\Campaign\Services\CampaignKpi;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\Organization\Services\TerritoryTargets;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\CampaignRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /campaigns: campaigns of the cycle selected in the header, create/edit, the approval flow
 * (submit → approve / reject → pause / resume → end) and the detail page with provisional KPIs.
 */
class CampaignController extends Controller
{
    /** request action => status it leads to */
    private const ACTIONS = [
        'submit' => CampaignStatus::PendingApproval,
        'approve' => CampaignStatus::Running,
        'reject' => CampaignStatus::Draft,
        'pause' => CampaignStatus::Paused,
        'resume' => CampaignStatus::Running,
        'end' => CampaignStatus::Ended,
    ];

    private const ACTION_LABELS = [
        'submit' => 'Gửi duyệt', 'approve' => 'Duyệt', 'reject' => 'Trả về nháp',
        'pause' => 'Tạm dừng', 'resume' => 'Chạy tiếp', 'end' => 'Kết thúc',
    ];

    public function index(Request $request, CurrentCycle $currentCycle): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $cycle = $currentCycle->get();
        $query = Campaign::query()->with(['owner:id,name', 'parent:id,name'])->where('cycle_id', $cycle?->id ?? 0);

        $campaigns = ListQuery::for($query, $request)
            ->filters(
                Filter::search('q', ['name', 'code']),
                Filter::exact('status'),
                Filter::exact('type'),
                Filter::exact('channel'),
                Filter::exact('owner_id'),
            )
            ->sorts(['name', 'code', 'status', 'start_date', 'budget', 'created_at'])
            ->defaultSort('-created_at')
            ->toProps(fn (Campaign $c) => $this->row($c));

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
            'cycle' => $cycle?->only(['id', 'name']),
            'options' => $this->options(),
            'can' => ['create' => $cycle !== null && $request->user()->can('create', Campaign::class) && ! $cycle->isClosed()],
        ]);
    }

    public function show(Request $request, CampaignKpi $kpi, int $id): Response
    {
        $campaign = Campaign::query()->with(['owner:id,name', 'parent:id,name', 'approver:id,name', 'cycle', 'children', 'targets'])->findOrFail($id);
        $this->authorize('view', $campaign);

        $user = $request->user();
        $targets = $campaign->targets->groupBy('targetable_type');
        $labels = fn (string $type) => collect(TerritoryTargets::labels($type, $targets->get($type, collect())->pluck('targetable_id')->map(fn ($v) => (int) $v)->all()))
            ->map(fn (string $name, int $tid) => ['id' => $tid, 'label' => $name])->values()->all();

        $transitions = [];

        foreach (self::ACTIONS as $action => $to) {
            if ($this->actionFits($campaign->status, $action) && $user->can('transition', [$campaign, $to])) {
                $transitions[] = ['action' => $action, 'label' => self::ACTION_LABELS[$action], 'to' => $to->value];
            }
        }

        return Inertia::render('Campaigns/Show', [
            'campaign' => $this->row($campaign) + [
                'cycle_id' => $campaign->cycle_id,
                'cycle_name' => $campaign->cycle?->name,
                'target_leads' => $campaign->target_leads,
                'target_applications' => $campaign->target_applications,
                'target_enrolled' => $campaign->target_enrolled,
                'landing_url' => $campaign->landing_url,
                'description' => $campaign->description,
                'approved_by_name' => $campaign->approver?->name,
                'approved_at' => $campaign->approved_at?->toIso8601String(),
                'targets' => [
                    'majors' => $labels('major'),
                    'provinces' => $labels('province'),
                    'high_schools' => $labels('high_school'),
                ],
            ],
            'children' => $campaign->children->map(fn (Campaign $c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'status' => $c->status->value, 'status_label' => $c->status->label()])->all(),
            'kpi' => $kpi->for($campaign),
            'transitions' => $transitions,
            'costs' => $campaign->costs()->orderByDesc('date')->orderByDesc('id')->get()->map(fn (CampaignCost $c) => ['id' => $c->id, 'date' => $c->date->format('Y-m-d'), 'amount' => (int) $c->amount, 'note' => $c->note])->all(),
            'options' => $this->options(),
            'can' => [
                'costs' => $user->can('manageCosts', $campaign),
                'update' => $user->can('update', $campaign),
                'delete' => $user->can('delete', $campaign),
            ],
        ]);
    }

    public function store(CampaignRequest $request, SaveCampaign $save): RedirectResponse
    {
        $campaign = $save->handle(null, $request->validated());

        return $this->toast("Đã tạo chiến dịch {$campaign->name}.", "/campaigns/{$campaign->id}");
    }

    public function update(CampaignRequest $request, int $id, SaveCampaign $save): RedirectResponse
    {
        $campaign = Campaign::query()->findOrFail($id);
        $save->handle($campaign, $request->validated());

        return $this->toast("Đã cập nhật chiến dịch {$campaign->name}.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $campaign = Campaign::query()->with('cycle')->findOrFail($id);
        $this->authorize('delete', $campaign);

        $campaign->forceDelete();

        return $this->toast("Đã xoá chiến dịch {$campaign->name}.", '/campaigns');
    }

    public function transition(Request $request, int $id, TransitionCampaign $transition): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(array_keys(self::ACTIONS))]]);
        $campaign = Campaign::query()->with('cycle')->findOrFail($id);
        $to = self::ACTIONS[$data['action']];

        abort_unless($this->actionFits($campaign->status, $data['action']), 422, 'Thao tác không phù hợp với trạng thái hiện tại.');
        $this->authorize('transition', [$campaign, $to]);

        $transition->handle($campaign, $to, $request->user());

        return $this->toast(self::ACTION_LABELS[$data['action']].": {$campaign->name}.");
    }

    /** "approve"/"reject" only make sense from pending_approval, "resume" from paused, and so on. */
    private function actionFits(CampaignStatus $from, string $action): bool
    {
        return match ($action) {
            'submit' => $from === CampaignStatus::Draft,
            'approve', 'reject' => $from === CampaignStatus::PendingApproval,
            'pause' => $from === CampaignStatus::Running,
            'resume' => $from === CampaignStatus::Paused,
            'end' => in_array($from, [CampaignStatus::Running, CampaignStatus::Paused], true),
            default => false,
        };
    }

    /** @return array<string, mixed> */
    private function row(Campaign $c): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'type' => $c->type?->value,
            'type_label' => $c->type?->label(),
            'channel' => $c->channel?->value,
            'channel_label' => $c->channel?->label(),
            'status' => $c->status->value,
            'status_label' => $c->status->label(),
            'start_date' => $c->start_date?->format('Y-m-d'),
            'end_date' => $c->end_date?->format('Y-m-d'),
            'budget' => $c->budget === null ? null : (int) $c->budget,
            'actual_cost' => $c->actual_cost === null ? null : (int) $c->actual_cost,
            'owner_id' => $c->owner_id,
            'owner_name' => $c->owner?->name,
            'parent_id' => $c->parent_id,
            'parent_name' => $c->parent?->name,
        ];
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    private function options(): array
    {
        $enum = fn (string $class) => array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], $class::cases());

        return [
            'statuses' => $enum(CampaignStatus::class),
            'types' => $enum(CampaignType::class),
            'channels' => $enum(CampaignChannel::class),
        ];
    }

    private function toast(string $message, ?string $to = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $to ? redirect($to) : back(fallback: '/campaigns');
    }
}
