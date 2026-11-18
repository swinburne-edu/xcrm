<?php

use App\Domain\Catalog\Models\Province;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Models\TerritoryAssignment;
use App\Domain\Organization\Services\RoleDefinitions;
use App\Domain\Organization\Services\TerritoryResolver;
use App\Domain\Organization\Services\TerritoryScope;
use App\Domain\Shared\Concerns\HasDataScope;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->hanoi = Province::factory()->create(['code' => '01', 'name' => 'Hà Nội']);
    $this->hue = Province::factory()->create(['code' => '46', 'name' => 'Huế']);
    $this->nghean = Province::factory()->create(['code' => '40', 'name' => 'Nghệ An']);
});

function territoryPayload(array $overrides = []): array
{
    return ['name' => 'Cụm Bắc Bộ', 'user_id' => null, 'team_id' => null, 'targets' => ['province' => []], ...$overrides];
}

/** Territory of $org covering provinces, owned by a user and/or team. */
function makeTerritory(Organization $org, array $provinces, ?User $user = null, ?Team $team = null, string $name = 'Vùng'): Territory
{
    $territory = Territory::factory()->for($org)->create(['name' => $name.' '.uniqid(), 'user_id' => $user?->id, 'team_id' => $team?->id]);

    foreach ($provinces as $province) {
        TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'province', 'assignable_id' => $province->id]);
    }

    return $territory;
}

describe('screen', function () {
    it('is limited to territory.view and territory.manage', function () {
        $head = User::factory()->for($this->org)->create();
        $head->assignRole('admission_head'); // territory.* via "*"
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');
        $viewer = User::factory()->for($this->org)->create();
        $viewer->givePermissionTo('territory.view');

        $this->actingAs($counselor)->get('/settings/territories')->assertForbidden();
        $this->actingAs($counselor)->post('/settings/territories', territoryPayload(['user_id' => $head->id]))->assertForbidden();

        $this->actingAs($viewer)->get('/settings/territories')
            ->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
        $this->actingAs($viewer)->post('/settings/territories', territoryPayload(['user_id' => $head->id]))->assertForbidden();

        $this->actingAs($head)->get('/settings/territories')
            ->assertInertia(fn (Assert $page) => $page->component('Territories/Index')->where('can.manage', true));
    });

    it('lists territories of the organization with owner, team and province labels', function () {
        $team = Team::factory()->for($this->org)->create(['name' => 'Nhóm Bắc']);
        $user = User::factory()->for($this->org)->create(['name' => 'An']);
        makeTerritory($this->org, [$this->hanoi, $this->hue], $user, $team, 'Cụm A');
        makeTerritory($this->other, [$this->nghean], null, null, 'Vùng tổ chức khác');

        $this->actingAs($this->admin)->get('/settings/territories')
            ->assertInertia(fn (Assert $page) => $page
                ->where('territories.meta.total', 1)
                ->where('territories.data.0.user_name', 'An')
                ->where('territories.data.0.team_name', 'Nhóm Bắc')
                ->where('territories.data.0.provinces_count', 2)
                ->where('territories.data.0.scope_label', 'Hà Nội, Huế')
                ->where('territories.data.0.targets.province.0.label', 'Hà Nội')
                ->where('targets', [['type' => 'province', 'label' => 'Tỉnh/Thành phố', 'lookup' => 'provinces']]));
    });

    it('filters by province covered', function () {
        makeTerritory($this->org, [$this->hanoi], $this->admin, null, 'A');
        makeTerritory($this->org, [$this->hue], $this->admin, null, 'B');

        $this->actingAs($this->admin)->get("/settings/territories?filter[province_id]={$this->hue->id}")
            ->assertInertia(fn (Assert $page) => $page->where('territories.meta.total', 1));
    });
});

describe('create and update', function () {
    it('creates a territory with the provinces it covers', function () {
        $team = Team::factory()->for($this->org)->create();

        $this->actingAs($this->admin)
            ->post('/settings/territories', territoryPayload(['team_id' => $team->id, 'targets' => ['province' => [$this->hanoi->id, $this->hue->id]]]))
            ->assertSessionHasNoErrors();

        $territory = Territory::where('name', 'Cụm Bắc Bộ')->first();

        expect($territory->organization_id)->toBe($this->org->id)
            ->and($territory->team_id)->toBe($team->id)
            ->and($territory->assignments->pluck('assignable_id')->sort()->values()->all())->toBe([$this->hanoi->id, $this->hue->id])
            ->and($territory->assignments->pluck('assignable_type')->unique()->all())->toBe(['province']);
    });

    it('needs a user or a team, a name, and valid provinces', function () {
        $this->actingAs($this->admin)->post('/settings/territories', territoryPayload())
            ->assertSessionHasErrors(['user_id' => 'Chọn người phụ trách hoặc nhóm phụ trách.']);

        $this->actingAs($this->admin)->post('/settings/territories', territoryPayload(['name' => '', 'user_id' => $this->admin->id]))
            ->assertSessionHasErrors(['name' => 'Vui lòng nhập tên vùng.']);

        $this->actingAs($this->admin)->post('/settings/territories', territoryPayload(['user_id' => $this->admin->id, 'targets' => ['province' => [999999]]]))
            ->assertSessionHasErrors('targets.province.0');

        $this->actingAs($this->admin)->post('/settings/territories', territoryPayload(['user_id' => $this->admin->id, 'targets' => ['planet' => [1]]]))
            ->assertSessionHasErrors('targets');
    });

    it('rejects users and teams of another organization', function () {
        $foreignUser = User::factory()->for($this->other)->create();
        $foreignTeam = Team::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->post('/settings/territories', territoryPayload(['user_id' => $foreignUser->id]))->assertSessionHasErrors('user_id');
        $this->actingAs($this->admin)->post('/settings/territories', territoryPayload(['team_id' => $foreignTeam->id]))->assertSessionHasErrors('team_id');
    });

    it('lets a province belong to one territory only', function () {
        $first = makeTerritory($this->org, [$this->hanoi], $this->admin, null, 'Cụm Bắc');

        $this->actingAs($this->admin)
            ->post('/settings/territories', territoryPayload(['name' => 'Cụm khác', 'user_id' => $this->admin->id, 'targets' => ['province' => [$this->hanoi->id, $this->hue->id]]]))
            ->assertSessionHasErrors(['targets.province' => "Hà Nội đã thuộc vùng {$first->name}."]);

        expect(Territory::where('name', 'Cụm khác')->exists())->toBeFalse();

        // Another organization may use the same province.
        $otherAdmin = User::factory()->for($this->other)->create();
        $otherAdmin->assignRole('admin');
        $this->actingAs($otherAdmin)
            ->post('/settings/territories', territoryPayload(['name' => 'Vùng của trường khác', 'user_id' => $otherAdmin->id, 'targets' => ['province' => [$this->hanoi->id]]]))
            ->assertSessionHasNoErrors();
    });

    it('can keep its own provinces when updated and release the ones removed', function () {
        $territory = makeTerritory($this->org, [$this->hanoi, $this->hue], $this->admin, null, 'Cụm A');

        $this->actingAs($this->admin)
            ->put("/settings/territories/{$territory->id}", territoryPayload(['name' => $territory->name, 'user_id' => $this->admin->id, 'targets' => ['province' => [$this->hanoi->id, $this->nghean->id]]]))
            ->assertSessionHasNoErrors();

        expect($territory->assignments()->pluck('assignable_id')->sort()->values()->all())->toBe([$this->hanoi->id, $this->nghean->id]);

        // Huế is free again.
        $this->actingAs($this->admin)
            ->post('/settings/territories', territoryPayload(['name' => 'Cụm B', 'user_id' => $this->admin->id, 'targets' => ['province' => [$this->hue->id]]]))
            ->assertSessionHasNoErrors();
    });

    it('cannot touch territories of another organization', function () {
        $foreign = makeTerritory($this->other, [$this->hanoi], null, null, 'Ngoài');

        $this->actingAs($this->admin)->put("/settings/territories/{$foreign->id}", territoryPayload(['user_id' => $this->admin->id]))->assertNotFound();
        $this->actingAs($this->admin)->delete("/settings/territories/{$foreign->id}")->assertNotFound();
    });

    it('frees its provinces when deleted', function () {
        $territory = makeTerritory($this->org, [$this->hanoi], $this->admin, null, 'Cụm A');

        $this->actingAs($this->admin)->delete("/settings/territories/{$territory->id}")->assertRedirect();

        $this->assertSoftDeleted($territory);
        expect(TerritoryAssignment::count())->toBe(0);

        $this->actingAs($this->admin)
            ->post('/settings/territories', territoryPayload(['name' => 'Cụm mới', 'user_id' => $this->admin->id, 'targets' => ['province' => [$this->hanoi->id]]]))
            ->assertSessionHasNoErrors();
    });
});

describe('TerritoryResolver', function () {
    beforeEach(function () {
        Schema::create('school_fixtures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('province_id');
            $table->string('name');
        });

        $this->schoolClass = (new class extends Model
        {
            public $timestamps = false;

            protected $table = 'school_fixtures';

            protected $guarded = [];
        })::class;

        Relation::morphMap(['high_school' => $this->schoolClass]);

        $this->resolver = app(TerritoryResolver::class);
    });

    it('finds the owner of a province', function () {
        $user = User::factory()->for($this->org)->create();
        $team = Team::factory()->for($this->org)->create();
        $territory = makeTerritory($this->org, [$this->hanoi], $user, $team);

        $owner = $this->resolver->ownerFor($this->hanoi, $this->org->id);

        expect($owner->territory->is($territory))->toBeTrue()
            ->and($owner->userId)->toBe($user->id)
            ->and($owner->teamId)->toBe($team->id)
            ->and($this->resolver->ownerFor($this->hue, $this->org->id))->toBeNull()
            ->and($this->resolver->ownerFor($this->hanoi, $this->other->id))->toBeNull();
    });

    it('uses the current organization when none is given', function () {
        $user = User::factory()->for($this->org)->create();
        makeTerritory($this->org, [$this->hanoi], $user);

        expect($this->resolver->ownerFor($this->hanoi))->toBeNull(); // no tenant context

        $this->actingAs($this->admin);
        expect($this->resolver->ownerFor($this->hanoi)->userId)->toBe($user->id);
    });

    it('prefers the school own assignment over its province', function () {
        $provinceOwner = User::factory()->for($this->org)->create();
        $schoolOwner = User::factory()->for($this->org)->create();
        makeTerritory($this->org, [$this->hanoi], $provinceOwner);

        $own = $this->schoolClass::create(['organization_id' => $this->org->id, 'province_id' => $this->hanoi->id, 'name' => 'THPT Chu Văn An']);
        $plain = $this->schoolClass::create(['organization_id' => $this->org->id, 'province_id' => $this->hanoi->id, 'name' => 'THPT Kim Liên']);
        $nowhere = $this->schoolClass::create(['organization_id' => $this->org->id, 'province_id' => $this->hue->id, 'name' => 'THPT Quốc Học']);

        $special = Territory::factory()->for($this->org)->create(['user_id' => $schoolOwner->id]);
        TerritoryAssignment::create(['territory_id' => $special->id, 'assignable_type' => 'high_school', 'assignable_id' => $own->id]);

        expect($this->resolver->ownerFor($own)->userId)->toBe($schoolOwner->id)
            ->and($this->resolver->ownerFor($plain)->userId)->toBe($provinceOwner->id)
            ->and($this->resolver->ownerFor($nowhere))->toBeNull();
    });

    it('ignores deleted territories', function () {
        makeTerritory($this->org, [$this->hanoi], $this->admin)->delete();

        expect($this->resolver->ownerFor($this->hanoi, $this->org->id))->toBeNull();
    });

    it('rejects models that are neither a province nor a school', function () {
        $this->resolver->ownerFor($this->admin, $this->org->id);
    })->throws(InvalidArgumentException::class);
});

describe('territory data scope', function () {
    beforeEach(function () {
        Schema::create('lead_fixtures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('high_school_id')->nullable();
            $table->string('title');
        });

        $this->leadClass = (new class extends Model
        {
            use HasDataScope;

            public $timestamps = false;

            protected $table = 'lead_fixtures';

            protected $guarded = [];

            public function applyDataScopeTerritory(Builder $query, User $user): void
            {
                TerritoryScope::apply($query, $this, $user, provinceColumn: 'province_id', highSchoolColumn: 'high_school_id');
            }
        })::class;

        $this->team = Team::factory()->for($this->org)->create();
        $this->me = User::factory()->for($this->org)->create(['data_scope' => RoleDefinitions::defaultScope('team_lead')]);
        $this->me->assignRole('team_lead');
        $this->team->members()->attach($this->me);
        $this->colleague = User::factory()->for($this->org)->create();

        // My territory: Hà Nội (as a person) and Huế (through my team), plus school 5 on its own.
        makeTerritory($this->org, [$this->hanoi], $this->me, null, 'Của tôi');
        makeTerritory($this->org, [$this->hue], null, $this->team, 'Của nhóm tôi');
        $mySchool = Territory::factory()->for($this->org)->create(['user_id' => $this->me->id]);
        TerritoryAssignment::create(['territory_id' => $mySchool->id, 'assignable_type' => 'high_school', 'assignable_id' => 5]);
        // School 9 (in Hà Nội) belongs to a colleague's own territory: it leaves my Hà Nội territory.
        $theirs = Territory::factory()->for($this->org)->create(['user_id' => $this->colleague->id]);
        TerritoryAssignment::create(['territory_id' => $theirs->id, 'assignable_type' => 'high_school', 'assignable_id' => 9]);
        // A territory of another organization must never count.
        makeTerritory($this->other, [$this->nghean], $this->me);

        $lead = fn (string $title, ?int $province, ?int $school = null, ?User $owner = null) => $this->leadClass::create([
            'title' => $title, 'province_id' => $province, 'high_school_id' => $school, 'owner_id' => $owner?->id,
        ]);

        $lead('Hà Nội, không trường', $this->hanoi->id);
        $lead('Hà Nội, trường chưa gán', $this->hanoi->id, 1);
        $lead('Hà Nội, trường của đồng nghiệp', $this->hanoi->id, 9);
        $lead('Huế (vùng của nhóm)', $this->hue->id);
        $lead('Nghệ An', $this->nghean->id);
        $lead('Nghệ An, trường tôi phụ trách', $this->nghean->id, 5);
        $lead('Nghệ An, của tôi', $this->nghean->id, null, $this->me);
        $lead('Không rõ tỉnh', null);
    });

    it('shows the rows in the territories of the user and of the user teams', function () {
        $this->actingAs($this->me);

        expect($this->leadClass::query()->orderBy('id')->pluck('title')->all())->toBe([
            'Hà Nội, không trường',
            'Hà Nội, trường chưa gán',
            'Huế (vùng của nhóm)',
            'Nghệ An, trường tôi phụ trách',
            'Nghệ An, của tôi',
        ]);
    });

    it('does not cover a school with its own territory through the province', function () {
        $this->colleague->forceFill(['data_scope' => DataScopeLevel::Territory])->save();
        $this->actingAs($this->colleague);

        expect($this->leadClass::query()->pluck('title')->all())->toBe(['Hà Nội, trường của đồng nghiệp']);
    });

    it('shows nothing of a territory to a user in own scope', function () {
        $this->actingAs($this->colleague); // data_scope own (default)

        expect($this->leadClass::query()->count())->toBe(0);
    });

    it('fails closed for a user without an organization', function () {
        $loner = User::factory()->create(['organization_id' => null, 'data_scope' => DataScopeLevel::Territory]);
        $this->actingAs($loner);

        expect($this->leadClass::query()->count())->toBe(0);
    });

    it('stops covering rows once their territory is deleted', function () {
        $territory = Territory::where('name', 'like', 'Của nhóm tôi%')->first();
        $territory->assignments()->delete();
        $territory->delete();
        $this->actingAs($this->me);

        expect($this->leadClass::query()->pluck('title')->all())->not->toContain('Huế (vùng của nhóm)');
    });
});
