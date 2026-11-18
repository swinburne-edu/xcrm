<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Admission\Models\Pipeline;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\Province;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Domain\Platform\Actions\SaveAssignmentRule;
use App\Domain\Platform\Enums\AssignmentStrategy;
use App\Domain\Platform\Models\AssignmentRule;
use App\Domain\Platform\Services\AssignmentSettings;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AssignmentRuleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/assignment-rules (task 2.4): the ordered rules that decide who gets a new case, plus the
 * organization's SLA-alert and automatic-recall switches. Authorization: AssignmentRulePolicy.
 */
class AssignmentRuleController extends Controller
{
    /** condition key => [label, picker entity] */
    private const CONDITIONS = [
        'source_ids' => ['Nguồn', 'sources'],
        'campaign_ids' => ['Chiến dịch', 'campaigns'],
        'province_ids' => ['Tỉnh/thành', 'provinces'],
        'high_school_ids' => ['Trường THPT', 'high_schools'],
        'major_ids' => ['Ngành', 'majors'],
    ];

    public function index(Request $request, AssignmentSettings $settings): Response
    {
        $this->authorize('viewAny', AssignmentRule::class);

        $rules = AssignmentRule::query()->orderBy('priority')->orderBy('id')->get();
        $names = $this->names($rules);
        $organization = Organization::query()->findOrFail(app(CurrentOrganization::class)->id());
        $pipeline = Pipeline::query()->where('is_default', true)->with(['stages' => fn ($q) => $q->orderBy('sort_order')])->first();

        return Inertia::render('AssignmentRules/Index', [
            'rules' => $rules->map(fn (AssignmentRule $r) => $this->row($r, $names))->all(),
            'options' => [
                'strategies' => array_map(fn (AssignmentStrategy $s) => ['value' => $s->value, 'label' => $s->label(), 'needs_people' => $s->needsPeople()], AssignmentStrategy::cases()),
                'sources' => LeadSource::query()->orderBy('name')->get(['id', 'name'])->map(fn (LeadSource $s) => ['value' => $s->id, 'label' => $s->name])->all(),
                'tiers' => [['value' => 'A', 'label' => 'Hạng A'], ['value' => 'B', 'label' => 'Hạng B'], ['value' => 'C', 'label' => 'Hạng C']],
            ],
            'settings' => $settings->all($organization),
            'stages' => $pipeline?->stages->map(fn ($s) => ['name' => $s->name, 'sla_hours' => $s->sla_hours])->all() ?? [],
            'can' => ['manage' => $request->user()->can('assignment_rule.manage')],
        ]);
    }

    public function store(AssignmentRuleRequest $request, SaveAssignmentRule $save): RedirectResponse
    {
        $rule = $save->handle(null, $request->validated());

        return $this->toast("Đã thêm quy tắc {$rule->name}.");
    }

    public function update(AssignmentRuleRequest $request, int $id, SaveAssignmentRule $save): RedirectResponse
    {
        $rule = AssignmentRule::query()->findOrFail($id);
        $save->handle($rule, $request->validated());

        return $this->toast("Đã cập nhật quy tắc {$rule->name}.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $rule = AssignmentRule::query()->findOrFail($id);
        $this->authorize('delete', $rule);
        $rule->delete();

        return $this->toast("Đã xoá quy tắc {$rule->name}.");
    }

    public function settings(Request $request, AssignmentSettings $settings): RedirectResponse
    {
        $this->authorize('manage', AssignmentRule::class);

        $data = $request->validate([
            'sla_alerts' => ['required', 'boolean'],
            'recall_after_days' => ['nullable', 'integer', 'between:1,'.AssignmentSettings::MAX_RECALL_DAYS],
        ], [], ['recall_after_days' => 'số ngày thu hồi']);

        $settings->save(Organization::query()->findOrFail(app(CurrentOrganization::class)->id()), $data);

        return $this->toast('Đã lưu cấu hình SLA và thu hồi.');
    }

    /**
     * Names of every record the rules mention, fetched once: entity => id => label.
     *
     * @param  \Illuminate\Support\Collection<int, AssignmentRule>  $rules
     * @return array<string, array<int, string>>
     */
    private function names($rules): array
    {
        $ids = fn (string $key) => $rules->flatMap(fn (AssignmentRule $r) => (array) ($r->conditions[$key] ?? []))->unique()->values()->all();
        $label = fn ($query) => $query->get(['id', 'name'])->pluck('name', 'id')->all();

        return [
            'sources' => $label(LeadSource::query()->whereIn('id', $ids('source_ids'))),
            'campaigns' => $label(Campaign::query()->whereIn('id', $ids('campaign_ids'))),
            'provinces' => $label(Province::query()->whereIn('id', $ids('province_ids'))),
            'high_schools' => $label(HighSchool::query()->whereIn('id', $ids('high_school_ids'))),
            'majors' => $label(Major::query()->whereIn('id', $ids('major_ids'))),
            'users' => $label(User::query()->whereIn('id', $rules->flatMap(fn (AssignmentRule $r) => (array) $r->user_ids)->unique()->values()->all())),
            'teams' => $label(Team::query()->whereIn('id', $rules->pluck('team_id')->filter()->unique()->values()->all())),
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $names
     * @return array<string, mixed>
     */
    private function row(AssignmentRule $r, array $names): array
    {
        $items = fn (string $entity, array $ids) => array_map(fn ($id) => ['id' => (int) $id, 'label' => $names[$entity][(int) $id] ?? "#{$id}"], $ids);
        $conditions = $r->conditions ?? [];
        $labels = [];

        foreach (self::CONDITIONS as $key => [$title, $entity]) {
            if (! empty($conditions[$key])) {
                $labels[] = ['key' => $key, 'title' => $title, 'values' => array_column($items($entity, $conditions[$key]), 'label')];
            }
        }

        if (! empty($conditions['school_tiers'])) {
            $labels[] = ['key' => 'school_tiers', 'title' => 'Hạng trường', 'values' => array_map(fn ($t) => "Hạng {$t}", $conditions['school_tiers'])];
        }

        return [
            'id' => $r->id,
            'name' => $r->name,
            'priority' => $r->priority,
            'strategy' => $r->strategy->value,
            'strategy_label' => $r->strategy->label(),
            'is_active' => $r->is_active,
            'team_id' => $r->team_id,
            'team' => $r->team_id === null ? null : ['id' => $r->team_id, 'label' => $names['teams'][$r->team_id] ?? "#{$r->team_id}"],
            'user_ids' => array_map('intval', (array) $r->user_ids),
            'users' => $items('users', (array) $r->user_ids),
            'conditions' => $conditions,
            'condition_labels' => $labels,
            'items' => [
                'campaigns' => $items('campaigns', (array) ($conditions['campaign_ids'] ?? [])),
                'provinces' => $items('provinces', (array) ($conditions['province_ids'] ?? [])),
                'high_schools' => $items('high_schools', (array) ($conditions['high_school_ids'] ?? [])),
                'majors' => $items('majors', (array) ($conditions['major_ids'] ?? [])),
                'users' => $items('users', (array) $r->user_ids),
                'teams' => $r->team_id === null ? [] : $items('teams', [$r->team_id]),
            ],
        ];
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/settings/assignment-rules');
    }
}
