<?php

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Domain\Shared\Support\Listing\Filter;
use App\Domain\Shared\Support\Listing\ListQuery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

function listRequest(array $query): Request
{
    return Request::create('/teams', 'GET', $query);
}

function teamList(array $query): ListQuery
{
    return ListQuery::for(Team::query(), listRequest($query))
        ->filters(
            Filter::search('q', ['name']),
            Filter::exact('leader_id'),
            Filter::dateRange('created_at'),
        )
        ->sorts(['name', 'created_at'])
        ->defaultSort('name');
}

beforeEach(function () {
    $this->org = Organization::factory()->create();

    foreach (['Ha Noi', 'Hue', 'Da Nang', '100% online', 'Can Tho'] as $name) {
        Team::factory()->for($this->org)->create(['name' => $name]);
    }
});

it('applies the default sort', function () {
    expect(teamList([])->query()->pluck('name')->all())
        ->toBe(['100% online', 'Can Tho', 'Da Nang', 'Ha Noi', 'Hue']);
});

it('sorts descending with a minus prefix and ignores unknown sorts', function () {
    $list = teamList(['sort' => '-name,password,organization_id']);

    expect($list->query()->pluck('name')->first())->toBe('Hue')
        ->and($list->state()['sort'])->toBe('-name');
});

it('searches with LIKE and escapes wildcards', function () {
    expect(teamList(['filter' => ['q' => 'Hu']])->query()->pluck('name')->all())->toBe(['Hue'])
        ->and(teamList(['filter' => ['q' => '100%']])->query()->count())->toBe(1)
        ->and(teamList(['filter' => ['q' => '%']])->query()->count())->toBe(1);
});

it('filters exact values, comma lists and arrays', function () {
    $leaders = User::factory()->count(2)->for($this->org)->create();
    Team::query()->where('name', 'Hue')->update(['leader_id' => $leaders[0]->id]);
    Team::query()->where('name', 'Ha Noi')->update(['leader_id' => $leaders[1]->id]);

    expect(teamList(['filter' => ['leader_id' => (string) $leaders[0]->id]])->query()->count())->toBe(1)
        ->and(teamList(['filter' => ['leader_id' => $leaders[0]->id.','.$leaders[1]->id]])->query()->count())->toBe(2)
        ->and(teamList(['filter' => ['leader_id' => [$leaders[0]->id, $leaders[1]->id]]])->query()->count())->toBe(2);
});

it('ignores filters that are not declared or empty', function () {
    $list = teamList(['filter' => ['organization_id' => 999, 'q' => '  ', 'leader_id' => '']]);

    expect($list->query()->count())->toBe(5)
        ->and($list->state()['filters'])->toBe([]);
});

it('treats date ranges as calendar days in Vietnam time', function () {
    Team::query()->update(['created_at' => '2026-11-10 00:00:00']);

    // 2026-11-16 17:30 UTC is 2026-11-17 00:30 in Asia/Ho_Chi_Minh.
    Team::query()->where('name', 'Hue')->update(['created_at' => '2026-11-16 17:30:00']);
    // 2026-11-16 16:59 UTC is still 2026-11-16 23:59 in Vietnam.
    Team::query()->where('name', 'Ha Noi')->update(['created_at' => '2026-11-16 16:59:00']);

    expect(teamList(['filter' => ['created_at' => ['from' => '2026-11-17']]])->query()->pluck('name')->all())
        ->toBe(['Hue'])
        ->and(teamList(['filter' => ['created_at' => ['from' => '2026-11-16', 'to' => '2026-11-16']]])->query()->pluck('name')->all())
        ->toBe(['Ha Noi'])
        ->and(teamList(['filter' => ['created_at' => ['from' => '17/11/2026']]])->query()->count())->toBe(5);
});

it('filters booleans', function () {
    User::factory()->count(2)->for($this->org)->create(['is_active' => true]);
    User::factory()->for($this->org)->create(['is_active' => false]);

    $list = fn (string $value) => ListQuery::for(User::query(), listRequest(['filter' => ['is_active' => $value]]))
        ->filters(Filter::boolean('is_active'));

    expect($list('1')->query()->count())->toBe(2)
        ->and($list('false')->query()->count())->toBe(1)
        ->and($list('maybe')->query()->count())->toBe(3);
});

it('paginates with allowed page sizes only', function () {
    Team::factory()->count(30)->for($this->org)->create();

    expect(teamList([])->paginate()->perPage())->toBe(ListQuery::DEFAULT_PER_PAGE)
        ->and(teamList(['per_page' => '10'])->paginate()->perPage())->toBe(10)
        ->and(teamList(['per_page' => '5000'])->paginate()->perPage())->toBe(ListQuery::DEFAULT_PER_PAGE)
        ->and(teamList(['per_page' => '10', 'page' => '4'])->paginate()->items())->toHaveCount(5);
});

it('returns the DataTable props shape', function () {
    $props = teamList(['filter' => ['q' => 'a N'], 'sort' => '-name', 'per_page' => '10'])
        ->toProps(fn (Team $team) => ['id' => $team->id, 'name' => $team->name]);

    expect($props['data'])->toBe([
        ['id' => Team::where('name', 'Ha Noi')->value('id'), 'name' => 'Ha Noi'],
        ['id' => Team::where('name', 'Da Nang')->value('id'), 'name' => 'Da Nang'],
    ])
        ->and($props['meta'])->toBe([
            'current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 2, 'from' => 1, 'to' => 2,
        ])
        ->and($props['query'])->toBe([
            'filters' => ['q' => 'a N'],
            'sort' => '-name',
            'per_page' => 10,
        ]);
});

it('still respects the organization scope', function () {
    $other = Organization::factory()->create();
    Team::factory()->for($other)->create(['name' => 'Nhóm tổ chức khác']);

    $this->actingAs(User::factory()->for($this->org)->create());

    expect(teamList(['filter' => ['q' => 'khác']])->query()->count())->toBe(0)
        ->and(teamList([])->query()->count())->toBe(5);
});

it('keeps a stable order across pages when sort values tie', function () {
    Team::query()->update(['created_at' => CarbonImmutable::parse('2026-01-01')]);

    $ids = ListQuery::for(Team::query(), listRequest(['sort' => 'created_at']))
        ->sorts(['created_at'])
        ->query()
        ->pluck('id')
        ->all();

    expect($ids)->toBe(Team::query()->orderByDesc('id')->pluck('id')->all());
});
