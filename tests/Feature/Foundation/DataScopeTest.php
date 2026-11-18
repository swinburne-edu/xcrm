<?php

use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Services\RoleDefinitions;
use App\Domain\Shared\Concerns\HasDataScope;
use App\Domain\Shared\Policies\ResourcePolicy;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Stand-in for admission_cases (owner_id + team_id, with a shared pool) until task 1.11.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    Schema::create('scoped_cases', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('owner_id')->nullable();
        $table->unsignedBigInteger('team_id')->nullable();
        $table->unsignedBigInteger('province_id')->nullable();
        $table->string('title');
    });

    $this->modelClass = (new class extends Model
    {
        use HasDataScope;

        public $timestamps = false;

        protected $table = 'scoped_cases';

        protected $guarded = [];

        public function dataScopeIncludesPool(): bool
        {
            return true;
        }

        // Territory hook of this fixture: cases in provinces 10 and 11.
        public function applyDataScopeTerritory(Builder $query, User $user): void
        {
            $query->whereIn('province_id', [10, 11]);
        }
    })::class;

    $this->org = Organization::factory()->create();
    $this->teamA = Team::factory()->for($this->org)->create(['name' => 'Nhóm A']);
    $this->teamB = Team::factory()->for($this->org)->create(['name' => 'Nhóm B']);

    $make = function (string $role, ?Team $team = null): User {
        $user = User::factory()->for($this->org)->create(['data_scope' => RoleDefinitions::defaultScope($role)]);
        $user->assignRole($role);
        $team?->members()->attach($user);

        return $user;
    };

    $this->alice = $make('counselor', $this->teamA);   // own
    $this->bob = $make('counselor', $this->teamB);     // own, another team
    $this->lead = $make('team_lead', $this->teamA);    // territory
    $this->head = $make('admission_head');             // all

    $case = fn (string $title, ?User $owner, ?Team $team, ?int $province = null) => $this->modelClass::create([
        'title' => $title, 'owner_id' => $owner?->id, 'team_id' => $team?->id, 'province_id' => $province,
    ]);

    $case('của Alice', $this->alice, $this->teamA);
    $case('của Bob', $this->bob, $this->teamB);
    $case('kho chung nhóm A', null, $this->teamA);
    $case('kho chung nhóm B', null, $this->teamB);
    $case('của Lead', $this->lead, $this->teamA, 10);
    $case('tỉnh 10 của Bob', $this->bob, $this->teamB, 10);
    $case('tỉnh 99 của Bob', $this->bob, $this->teamB, 99);
});

/** Titles of the cases $user sees when logged in. */
function titlesFor(User $user): array
{
    test()->actingAs($user);

    return test()->modelClass::query()->orderBy('id')->pluck('title')->all();
}

it('does not restrict code that runs without a logged-in user', function () {
    expect($this->modelClass::count())->toBe(7);
});

it('lets a counselor (own scope) see only own cases and the pool of own teams', function () {
    expect(titlesFor($this->alice))->toBe(['của Alice', 'kho chung nhóm A'])
        ->and(titlesFor($this->bob))->toBe(['của Bob', 'kho chung nhóm B', 'tỉnh 10 của Bob', 'tỉnh 99 của Bob']);
});

it('keeps a counselor from loading another user case by id', function () {
    $bobsCase = $this->modelClass::query()->where('title', 'của Bob')->first();

    $this->actingAs($this->alice);

    expect($this->modelClass::find($bobsCase->id))->toBeNull()
        ->and($this->modelClass::where('title', 'của Bob')->exists())->toBeFalse();
});

it('shows a team scope the cases of the teams the user belongs to', function () {
    $this->alice->forceFill(['data_scope' => DataScopeLevel::Team])->save();

    expect(titlesFor($this->alice->fresh()))->toBe(['của Alice', 'kho chung nhóm A', 'của Lead']);
});

it('resolves territory scope through the model hook, plus own cases and the pool of own teams', function () {
    expect(titlesFor($this->lead))->toBe(['kho chung nhóm A', 'của Lead', 'tỉnh 10 của Bob']);
});

it('fails closed for territory scope on a model without a territory rule', function () {
    $plain = (new class extends Model
    {
        use HasDataScope;

        public $timestamps = false;

        protected $table = 'scoped_cases';
    })::class;

    $this->actingAs($this->lead);

    expect($plain::query()->pluck('title')->all())->toBe(['của Lead']);
});

it('shows everything to the all scope', function () {
    expect(titlesFor($this->head))->toHaveCount(7);
});

it('computes visibility for any user with visibleTo and can bypass the scope', function () {
    $this->actingAs($this->head);

    expect($this->modelClass::query()->visibleTo($this->alice)->pluck('title')->all())->toBe(['của Alice', 'kho chung nhóm A'])
        ->and($this->modelClass::query()->visibleTo($this->lead)->count())->toBe(3);

    $this->actingAs($this->alice);

    expect($this->modelClass::query()->withoutDataScope()->count())->toBe(7);
});

describe('ResourcePolicy', function () {
    beforeEach(function () {
        // Fixture policy: permission object "case" (case.view, case.update…) + data scope.
        $policy = new class extends ResourcePolicy
        {
            protected function object(): string
            {
                return 'case';
            }
        };

        Gate::policy($this->modelClass, $policy::class);
    });

    it('needs both the permission and the record inside the data scope', function () {
        $mine = $this->modelClass::query()->withoutDataScope()->where('title', 'của Alice')->first();
        $bobs = $this->modelClass::query()->withoutDataScope()->where('title', 'của Bob')->first();

        expect($this->alice->can('view', $mine))->toBeTrue()
            ->and($this->alice->can('update', $mine))->toBeTrue()
            ->and($this->alice->can('view', $bobs))->toBeFalse()
            ->and($this->alice->can('update', $bobs))->toBeFalse()
            ->and($this->alice->can('delete', $mine))->toBeFalse()   // counselor has no case.delete
            ->and($this->alice->can('assign', $mine))->toBeFalse()   // nor case.assign
            ->and($this->lead->can('assign', $mine))->toBeFalse()    // has the permission, record outside territory
            ->and($this->head->can('delete', $bobs))->toBeTrue()
            ->and($this->head->can('assign', $bobs))->toBeTrue();
    });

    it('checks the permission alone for list-level actions', function () {
        expect($this->alice->can('viewAny', $this->modelClass))->toBeTrue()
            ->and($this->alice->can('export', $this->modelClass))->toBeFalse()
            ->and($this->head->can('export', $this->modelClass))->toBeTrue()
            ->and($this->alice->can('create', $this->modelClass))->toBeTrue();
    });
});
