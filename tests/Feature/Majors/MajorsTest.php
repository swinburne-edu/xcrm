<?php

use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Campus;
use App\Domain\Major\Models\Faculty;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
});

function majorPayload(array $overrides = []): array
{
    return ['admission_code' => 'cntt01', 'name' => 'Công nghệ thông tin', 'moet_code' => '7480201', 'program_type' => 'standard', 'level' => 'university', 'is_active' => true, ...$overrides];
}

function ownMajor(array $attrs = []): Major
{
    return Major::factory()->for(test()->org)->create($attrs);
}

function ownCycleFor(string $state = 'active', array $attrs = []): AdmissionCycle
{
    $factory = AdmissionCycle::factory()->for(test()->org);

    return ($state === 'preparing' ? $factory : $factory->{$state}())->create($attrs);
}

describe('list and permissions', function () {
    it('lists majors of the own organization with the quota of the current cycle', function () {
        $cycle = ownCycleFor('current');
        $mine = ownMajor();
        MajorQuota::factory()->create(['organization_id' => $this->org->id, 'major_id' => $mine->id, 'cycle_id' => $cycle->id, 'quota_total' => 120]);
        Major::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->get('/majors')
            ->assertInertia(fn (Assert $page) => $page->component('Majors/Index')
                ->has('majors.data', 1)->where('majors.data.0.id', $mine->id)->where('majors.data.0.cycle_quota', 120));
    });

    it('filters by faculty, level and search', function () {
        $it = Faculty::factory()->for($this->org)->create();
        $a = ownMajor(['name' => 'Khoa học máy tính', 'faculty_id' => $it->id]);
        ownMajor(['name' => 'Kế toán', 'level' => 'college']);

        $this->actingAs($this->admin)->get('/majors?filter[faculty_id]='.$it->id)
            ->assertInertia(fn (Assert $page) => $page->has('majors.data', 1)->where('majors.data.0.id', $a->id));
        $this->actingAs($this->admin)->get('/majors?filter[level]=college')
            ->assertInertia(fn (Assert $page) => $page->has('majors.data', 1)->where('majors.data.0.name', 'Kế toán'));
        $this->actingAs($this->admin)->get('/majors?filter[q]=máy')
            ->assertInertia(fn (Assert $page) => $page->has('majors.data', 1));
    });

    it('lets a counselor view but not change majors', function () {
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');
        $major = ownMajor();

        $this->actingAs($counselor)->get('/majors')->assertOk();
        $this->actingAs($counselor)->get("/majors/{$major->id}")->assertInertia(fn (Assert $page) => $page->where('can.update', false)->where('can.delete', false));
        $this->actingAs($counselor)->post('/majors', majorPayload())->assertForbidden();
        $this->actingAs($counselor)->put("/majors/{$major->id}", majorPayload())->assertForbidden();
        $this->actingAs($counselor)->delete("/majors/{$major->id}")->assertForbidden();
    });

    it('hides a major of another organization', function () {
        $foreign = Major::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->get("/majors/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->admin)->put("/majors/{$foreign->id}", majorPayload())->assertForbidden();
    });

    it('is in the menu for users with major.view', function () {
        $this->actingAs($this->admin)->get('/majors')
            ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($nav) => str_contains(json_encode($nav, JSON_UNESCAPED_SLASHES), '/majors')));
    });
});

describe('create, update, delete', function () {
    it('creates a major with an upper-case admission code and redirects to it', function () {
        $faculty = Faculty::factory()->for($this->org)->create();
        $campus = Campus::factory()->for($this->org)->create();

        $response = $this->actingAs($this->admin)->post('/majors', majorPayload(['faculty_id' => $faculty->id, 'campus_id' => $campus->id, 'duration_years' => 4.5]));

        $major = Major::withoutGlobalScopes()->where('admission_code', 'CNTT01')->firstOrFail();
        $response->assertRedirect("/majors/{$major->id}");
        expect($major->organization_id)->toBe($this->org->id)->and($major->faculty_id)->toBe($faculty->id)->and((float) $major->duration_years)->toBe(4.5);
    });

    it('keeps the admission code unique within an organization only', function () {
        ownMajor(['admission_code' => 'CNTT01']);
        Major::factory()->for($this->other)->create(['admission_code' => 'KT01']);

        $this->actingAs($this->admin)->post('/majors', majorPayload())->assertSessionHasErrors('admission_code');
        $this->actingAs($this->admin)->post('/majors', majorPayload(['admission_code' => 'kt01']))->assertSessionDoesntHaveErrors();
    });

    it('validates the Ministry code, enums and organization-bound references', function () {
        $foreignFaculty = Faculty::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->post('/majors', majorPayload(['moet_code' => '12345']))->assertSessionHasErrors('moet_code');
        $this->actingAs($this->admin)->post('/majors', majorPayload(['level' => 'phd']))->assertSessionHasErrors('level');
        $this->actingAs($this->admin)->post('/majors', majorPayload(['program_type' => 'x']))->assertSessionHasErrors('program_type');
        $this->actingAs($this->admin)->post('/majors', majorPayload(['faculty_id' => $foreignFaculty->id]))->assertSessionHasErrors('faculty_id');
    });

    it('updates a major', function () {
        $major = ownMajor(['admission_code' => 'OLD1']);

        $this->actingAs($this->admin)->put("/majors/{$major->id}", majorPayload(['admission_code' => 'OLD1', 'name' => 'Tên mới', 'is_active' => false]))
            ->assertSessionHasNoErrors();

        expect($major->fresh())->name->toBe('Tên mới')->is_active->toBeFalse();
    });

    it('deletes a major that was never configured', function () {
        $major = ownMajor();

        $this->actingAs($this->admin)->delete("/majors/{$major->id}")->assertRedirect('/majors');

        expect(Major::withoutGlobalScopes()->find($major->id))->toBeNull();
    });

    it('refuses to delete a major configured for a cycle', function () {
        $major = ownMajor();
        MajorQuota::factory()->create(['organization_id' => $this->org->id, 'major_id' => $major->id, 'cycle_id' => ownCycleFor()->id]);

        $this->actingAs($this->admin)->delete("/majors/{$major->id}")->assertSessionHasErrors('major');

        expect(Major::find($major->id))->not->toBeNull();
    });
});

describe('faculty and campus catalogs', function () {
    it('are managed as catalog tabs, per organization', function () {
        $this->actingAs($this->admin)->post('/settings/catalogs/faculties', ['code' => 'cntt', 'name' => 'Công nghệ thông tin'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/settings/catalogs/campuses', ['code' => 'hn', 'name' => 'Hà Nội', 'address' => '1 Đại Cồ Việt'])->assertSessionHasNoErrors();

        expect(Faculty::query()->pluck('code')->all())->toBe(['CNTT'])
            ->and(Campus::query()->pluck('code')->all())->toBe(['HN']);

        $this->actingAs($this->admin)->post('/settings/catalogs/faculties', ['code' => 'CNTT', 'name' => 'Trùng'])->assertSessionHasErrors('code');

        // the same code is free in another organization
        Faculty::factory()->for($this->other)->create(['code' => 'KT']);
        $this->actingAs($this->admin)->post('/settings/catalogs/faculties', ['code' => 'KT', 'name' => 'Kinh tế'])->assertSessionDoesntHaveErrors();
    });

    it('cannot be deleted while a major uses them', function () {
        $faculty = Faculty::factory()->for($this->org)->create();
        ownMajor(['faculty_id' => $faculty->id]);

        $this->actingAs($this->admin)->delete("/settings/catalogs/faculties/{$faculty->id}");

        expect(Faculty::find($faculty->id))->not->toBeNull();
    });

    it('feed the EntityPicker lookups of the own organization', function () {
        Faculty::factory()->for($this->org)->create(['name' => 'Khoa Điện']);
        Faculty::factory()->for($this->other)->create(['name' => 'Khoa Điện lạnh']);
        ownMajor(['name' => 'Điện tử viễn thông']);
        ownMajor(['name' => 'Ngừng', 'is_active' => false]);

        $this->actingAs($this->admin)->getJson('/lookups/faculties?q=Điện')->assertJsonCount(1);
        $this->actingAs($this->admin)->getJson('/lookups/majors?q=')->assertJsonCount(1);
    });
});

describe('per-cycle setup ("Theo mùa")', function () {
    beforeEach(function () {
        $this->cycle = ownCycleFor('current');
        $this->major = ownMajor();
        $this->combos = SubjectCombination::factory()->count(3)->create();
        $this->methods = CycleMethod::factory()->count(2)->for($this->cycle, 'cycle')->create();
    });

    function setupPayload(array $overrides = []): array
    {
        return ['quota_total' => 150, 'quota_direct' => 15, 'target_enrolled' => 120, 'tuition_fee' => 25000000, 'min_threshold' => 15.5, ...$overrides];
    }

    it('shows the current cycle by default and any cycle on request', function () {
        $older = ownCycleFor('closed');

        $this->actingAs($this->admin)->get("/majors/{$this->major->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Majors/Show')->where('selectedCycleId', $this->cycle->id)
                ->where('setup.configured', false)->where('can.setup', true)->has('methodOptions', 2));

        $this->actingAs($this->admin)->get("/majors/{$this->major->id}?cycle_id={$older->id}")
            ->assertInertia(fn (Assert $page) => $page->where('selectedCycleId', $older->id)->where('can.setup', false));
    });

    it('saves quota, combinations and methods of the cycle', function () {
        $this->actingAs($this->admin)->put("/majors/{$this->major->id}/cycles/{$this->cycle->id}", setupPayload([
            'combination_ids' => [$this->combos[0]->id, $this->combos[1]->id],
            'cycle_method_ids' => [$this->methods[0]->id],
            'previous_cutoffs' => [['year' => 2026, 'method' => '100', 'score' => 24.5]],
            'scholarship_info' => 'Học bổng 50%',
        ]))->assertSessionHasNoErrors();

        $quota = MajorQuota::query()->where('major_id', $this->major->id)->firstOrFail();
        expect($quota->cycle_id)->toBe($this->cycle->id)->and($quota->quota_total)->toBe(150)->and($quota->quota_direct)->toBe(15)
            ->and($quota->previous_cutoffs)->toBe([['year' => 2026, 'method' => '100', 'score' => 24.5]])
            ->and($quota->organization_id)->toBe($this->org->id)
            ->and(DB::table('major_combinations')->where('major_id', $this->major->id)->count())->toBe(2)
            ->and(DB::table('major_cycle_methods')->where('major_id', $this->major->id)->pluck('cycle_method_id')->all())->toBe([$this->methods[0]->id]);

        $this->actingAs($this->admin)->get("/majors/{$this->major->id}")
            ->assertInertia(fn (Assert $page) => $page->where('setup.configured', true)->where('setup.quota_total', 150)
                ->where('setup.combination_ids', [$this->combos[0]->id, $this->combos[1]->id])->where('setup.cycle_method_ids', [$this->methods[0]->id]));
    });

    it('replaces the previous selection of that cycle and leaves other cycles alone', function () {
        $other = ownCycleFor('preparing');
        $otherMethod = CycleMethod::factory()->for($other, 'cycle')->create();
        $this->actingAs($this->admin)->put("/majors/{$this->major->id}/cycles/{$other->id}", setupPayload(['combination_ids' => [$this->combos[2]->id], 'cycle_method_ids' => [$otherMethod->id]]));

        $url = "/majors/{$this->major->id}/cycles/{$this->cycle->id}";
        $this->actingAs($this->admin)->put($url, setupPayload(['combination_ids' => [$this->combos[0]->id], 'cycle_method_ids' => [$this->methods[0]->id]]));
        $this->actingAs($this->admin)->put($url, setupPayload(['quota_total' => 90, 'combination_ids' => [$this->combos[1]->id], 'cycle_method_ids' => []]));

        expect(DB::table('major_combinations')->where('cycle_id', $this->cycle->id)->pluck('combination_id')->all())->toBe([$this->combos[1]->id])
            ->and(DB::table('major_cycle_methods')->where('cycle_method_id', $this->methods[0]->id)->count())->toBe(0)
            ->and(DB::table('major_cycle_methods')->where('cycle_method_id', $otherMethod->id)->count())->toBe(1)
            ->and(DB::table('major_combinations')->where('cycle_id', $other->id)->count())->toBe(1)
            ->and(MajorQuota::query()->where('major_id', $this->major->id)->count())->toBe(2)
            ->and(MajorQuota::query()->where('cycle_id', $this->cycle->id)->value('quota_total'))->toBe(90);
    });

    it('R11: the direct quota cannot exceed the total quota', function () {
        $this->actingAs($this->admin)->put("/majors/{$this->major->id}/cycles/{$this->cycle->id}", setupPayload(['quota_total' => 50, 'quota_direct' => 60]))
            ->assertSessionHasErrors('quota_direct');
        $this->actingAs($this->admin)->put("/majors/{$this->major->id}/cycles/{$this->cycle->id}", setupPayload(['quota_total' => 50, 'quota_direct' => 50]))
            ->assertSessionHasNoErrors();
    });

    it('rejects a method of another cycle and unknown combinations', function () {
        $foreignMethod = CycleMethod::factory()->for(ownCycleFor('preparing'), 'cycle')->create();
        $url = "/majors/{$this->major->id}/cycles/{$this->cycle->id}";

        $this->actingAs($this->admin)->put($url, setupPayload(['cycle_method_ids' => [$foreignMethod->id]]))->assertSessionHasErrors('cycle_method_ids.0');
        $this->actingAs($this->admin)->put($url, setupPayload(['combination_ids' => [999999]]))->assertSessionHasErrors('combination_ids.0');
        $this->actingAs($this->admin)->put($url, ['quota_direct' => 0])->assertSessionHasErrors('quota_total');
    });

    it('R8: a closed cycle is read-only', function () {
        $closed = ownCycleFor('closed');

        $this->actingAs($this->admin)->put("/majors/{$this->major->id}/cycles/{$closed->id}", setupPayload())->assertForbidden();
        expect(MajorQuota::query()->count())->toBe(0);
    });

    it('needs major.update', function () {
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');

        $this->actingAs($counselor)->put("/majors/{$this->major->id}/cycles/{$this->cycle->id}", setupPayload())->assertForbidden();
    });

    it('does not reach a cycle or major of another organization', function () {
        $foreignCycle = AdmissionCycle::factory()->for($this->other)->active()->create();
        $foreignMajor = Major::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->put("/majors/{$this->major->id}/cycles/{$foreignCycle->id}", setupPayload())->assertForbidden();
        $this->actingAs($this->admin)->put("/majors/{$foreignMajor->id}/cycles/{$this->cycle->id}", setupPayload())->assertForbidden();
        expect(MajorQuota::withoutGlobalScopes()->count())->toBe(0);
    });

    it('drops the major-method link when the method leaves the cycle', function () {
        $this->actingAs($this->admin)->put("/majors/{$this->major->id}/cycles/{$this->cycle->id}", setupPayload(['cycle_method_ids' => [$this->methods[0]->id]]));

        $this->actingAs($this->admin)->delete("/settings/cycles/{$this->cycle->id}/methods/{$this->methods[0]->id}");

        expect(DB::table('major_cycle_methods')->count())->toBe(0);
    });
});
