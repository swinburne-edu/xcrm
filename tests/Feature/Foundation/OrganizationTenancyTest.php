<?php

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Models\User;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();

    // Seed teams without a tenant context (like a seeder or console command).
    Team::factory()->count(2)->for($this->orgA)->create();
    Team::factory()->count(3)->for($this->orgB)->create();
});

it('does not filter queries without a tenant context', function () {
    expect(Team::count())->toBe(5);
});

it('limits queries to the organization of the logged-in user', function () {
    $this->actingAs(User::factory()->for($this->orgA)->create());

    expect(Team::count())->toBe(2)
        ->and(Team::pluck('organization_id')->unique()->all())->toBe([$this->orgA->id]);
});

it('cannot load a record of another organization by id', function () {
    $foreignTeam = Team::acrossOrganizations()->where('organization_id', $this->orgB->id)->first();

    $this->actingAs(User::factory()->for($this->orgA)->create());

    expect(Team::find($foreignTeam->id))->toBeNull();
});

it('shows nothing to a logged-in user without an organization', function () {
    $this->actingAs(User::factory()->create(['organization_id' => null]));

    expect(Team::count())->toBe(0);
});

it('can query across organizations explicitly', function () {
    $this->actingAs(User::factory()->for($this->orgA)->create());

    expect(Team::acrossOrganizations()->count())->toBe(5);
});

it('fills organization_id from the logged-in user on create', function () {
    $this->actingAs(User::factory()->for($this->orgA)->create());

    $team = Team::create(['name' => 'Nhóm Bắc Trung Bộ']);

    expect($team->organization_id)->toBe($this->orgA->id);
});

it('keeps an explicitly given organization_id', function () {
    $this->actingAs(User::factory()->for($this->orgA)->create());

    $team = Team::create(['name' => 'Nhóm khác', 'organization_id' => $this->orgB->id]);

    expect($team->organization_id)->toBe($this->orgB->id);
});

it('uses an organization set explicitly for jobs and seeders', function () {
    $current = app(CurrentOrganization::class);

    $result = $current->run($this->orgB->id, function () {
        $team = Team::create(['name' => 'Nhóm từ job']);

        return [Team::count(), $team->organization_id];
    });

    expect($result)->toBe([4, $this->orgB->id])
        ->and($current->id())->toBeNull()
        ->and(Team::count())->toBe(6);
});

it('fills created_by and updated_by with the logged-in user', function () {
    $creator = User::factory()->for($this->orgA)->create();
    $this->actingAs($creator);

    $team = Team::create(['name' => 'Nhóm Tây Nguyên']);

    expect($team->created_by)->toBe($creator->id)
        ->and($team->updated_by)->toBe($creator->id)
        ->and($team->creator->is($creator))->toBeTrue();

    $editor = User::factory()->for($this->orgA)->create();
    $this->actingAs($editor);

    $team->update(['name' => 'Nhóm Tây Nguyên 2']);

    expect($team->fresh()->created_by)->toBe($creator->id)
        ->and($team->fresh()->updated_by)->toBe($editor->id);
});

it('leaves audit columns empty without a logged-in user', function () {
    $team = Team::factory()->for($this->orgA)->create();

    expect($team->created_by)->toBeNull()
        ->and($team->updated_by)->toBeNull();
});

it('soft deletes teams and links members', function () {
    $this->actingAs($leader = User::factory()->for($this->orgA)->create());
    $team = Team::create(['name' => 'Nhóm Đông Nam Bộ', 'leader_id' => $leader->id]);
    $team->members()->attach($leader);

    expect($leader->teams->pluck('id')->all())->toBe([$team->id])
        ->and($team->leader->is($leader))->toBeTrue();

    $team->delete();

    $this->assertSoftDeleted($team);
});
