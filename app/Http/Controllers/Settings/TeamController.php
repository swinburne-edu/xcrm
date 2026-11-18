<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Organization\Actions\SaveTeam;
use App\Domain\Organization\Models\Team;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TeamRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tab "Nhóm" of /settings/users: teams with a leader and members (OrganizationScope applies).
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Team::class);

        $teams = ListQuery::for(Team::query()->with(['leader:id,name', 'members:id,name'])->withCount('members'), $request)
            ->filters(Filter::search('q', ['name']))
            ->sorts(['name', 'members_count'])
            ->defaultSort('name')
            ->toProps(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'leader_id' => $team->leader_id,
                'leader_name' => $team->leader?->name,
                'members_count' => $team->members_count,
                'member_ids' => $team->members->pluck('id')->all(),
                'members' => $team->members->map(fn ($m) => ['id' => $m->id, 'label' => $m->name])->all(),
            ]);

        return Inertia::render('Users/Index', [
            'tab' => 'teams',
            'tabs' => UserController::tabs(),
            'teams' => $teams,
            'can' => [
                'create' => $request->user()->can('create', Team::class),
                'update' => $request->user()->can('team.update'),
                'delete' => $request->user()->can('team.delete'),
            ],
            'breadcrumbs' => UserController::breadcrumbs('Nhóm'),
        ]);
    }

    public function store(TeamRequest $request, SaveTeam $save): RedirectResponse
    {
        $team = $save->handle(null, $request->validated());

        return $this->back("Đã thêm nhóm {$team->name}.");
    }

    public function update(TeamRequest $request, int $id, SaveTeam $save): RedirectResponse
    {
        $team = Team::query()->findOrFail($id);
        $this->authorize('update', $team);

        $save->handle($team, $request->validated());

        return $this->back("Đã cập nhật nhóm {$team->name}.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $team = Team::query()->findOrFail($id);
        $this->authorize('delete', $team);

        $team->members()->detach();
        $team->delete();

        return $this->back("Đã xoá nhóm {$team->name}.");
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/settings/users/teams');
    }
}
