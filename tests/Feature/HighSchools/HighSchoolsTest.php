<?php

use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Catalog\Models\Ward;
use App\Domain\HighSchool\Enums\PartnershipStatus;
use App\Domain\HighSchool\Enums\SchoolTier;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolContact;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Models\TerritoryAssignment;
use App\Domain\Organization\Services\TerritoryTargets;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->hanoi = Province::factory()->create(['code' => '01', 'name' => 'Hà Nội']);
    $this->hue = Province::factory()->create(['code' => '46', 'name' => 'Huế']);
});

function schoolPayload(array $overrides = []): array
{
    return ['code' => 'th001', 'name' => 'THPT Chu Văn An', 'type' => 'public', 'tier' => 'A', 'partnership_status' => 'contacting', ...$overrides];
}

function ownSchool(array $attrs = []): HighSchool
{
    return HighSchool::factory()->for(test()->org)->create($attrs);
}

describe('list, scope and permissions', function () {
    it('lists the schools of the own organization with filters', function () {
        $a = ownSchool(['name' => 'THPT A', 'province_id' => $this->hanoi->id, 'tier' => 'A', 'partnership_status' => 'mou_signed']);
        ownSchool(['name' => 'THPT B', 'province_id' => $this->hue->id, 'tier' => 'C']);
        HighSchool::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->get('/high-schools')
            ->assertInertia(fn (Assert $page) => $page->component('HighSchools/Index')->has('schools.data', 2));

        foreach (["filter[province_id]={$this->hanoi->id}", 'filter[tier]=A', 'filter[partnership_status]=mou_signed', 'filter[q]=THPT A'] as $query) {
            $this->actingAs($this->admin)->get("/high-schools?{$query}")
                ->assertInertia(fn (Assert $page) => $page->has('schools.data', 1)->where('schools.data.0.id', $a->id));
        }
    });

    it('filters by owner', function () {
        $owner = User::factory()->for($this->org)->create();
        $mine = ownSchool(['owner_id' => $owner->id]);
        ownSchool();

        $this->actingAs($this->admin)->get("/high-schools?filter[owner_id]={$owner->id}")
            ->assertInertia(fn (Assert $page) => $page->has('schools.data', 1)->where('schools.data.0.id', $mine->id));
    });

    it('shows a counselor only the schools they own (data scope: own)', function () {
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');
        $mine = ownSchool(['owner_id' => $counselor->id]);
        $notMine = ownSchool();

        $this->actingAs($counselor)->get('/high-schools')
            ->assertInertia(fn (Assert $page) => $page->has('schools.data', 1)->where('schools.data.0.id', $mine->id));
        $this->actingAs($counselor)->get("/high-schools/{$notMine->id}")->assertNotFound();
    });

    it('applies the territory scope through provinces and schools', function () {
        $user = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Territory]);
        $user->givePermissionTo('high_school.view');
        ownSchool(['province_id' => $this->hanoi->id]);
        $ownTerritory = ownSchool(['province_id' => $this->hue->id]);
        ownSchool(['province_id' => $this->hue->id]); // same province as above but outside the user's territories

        $territory = Territory::factory()->for($this->org)->create(['user_id' => $user->id]);
        TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'province', 'assignable_id' => $this->hanoi->id]);
        TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'high_school', 'assignable_id' => $ownTerritory->id]);

        $this->actingAs($user)->get('/high-schools')
            ->assertInertia(fn (Assert $page) => $page->has('schools.data', 2));
    });

    it('is limited by high_school permissions', function () {
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('high_school.view');
        $nobody = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $school = ownSchool();

        $this->actingAs($nobody)->get('/high-schools')->assertForbidden();
        $this->actingAs($viewer)->get('/high-schools')->assertInertia(fn (Assert $page) => $page->where('can.create', false)->where('can.import', false));
        $this->actingAs($viewer)->post('/high-schools', schoolPayload())->assertForbidden();
        $this->actingAs($viewer)->put("/high-schools/{$school->id}", schoolPayload())->assertForbidden();
        $this->actingAs($viewer)->delete("/high-schools/{$school->id}")->assertForbidden();
    });

    it('does not show a school of another organization', function () {
        $foreign = HighSchool::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->get("/high-schools/{$foreign->id}")->assertNotFound();
    });

    it('appears in the menu and as a territory target and lookup', function () {
        $school = ownSchool(['name' => 'THPT Lê Quý Đôn']);
        HighSchool::factory()->for($this->other)->create(['name' => 'THPT Lê Lợi']);

        $this->actingAs($this->admin)->get('/high-schools')
            ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($nav) => str_contains(json_encode($nav, JSON_UNESCAPED_SLASHES), '/high-schools')));
        $this->actingAs($this->admin)->getJson('/lookups/high_schools?q=Lê')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $school->id);
        expect(TerritoryTargets::types())->toContain('high_school');
    });
});

describe('create, update, delete', function () {
    it('creates a school with an upper-case code and the detail fields', function () {
        $ward = Ward::factory()->create(['province_id' => $this->hanoi->id]);
        $area = PriorityArea::factory()->create();
        SubjectCombination::factory()->create(['code' => 'A00']);

        $response = $this->actingAs($this->admin)->post('/high-schools', schoolPayload([
            'province_id' => $this->hanoi->id, 'ward_id' => $ward->id, 'priority_area_id' => $area->id, 'strong_combinations' => ['A00'],
            'grade12_students' => 450, 'grade12_classes' => 10, 'mou_signed_at' => '2026-03-15', 'email' => 'bgh@thpt.edu.vn', 'owner_id' => $this->admin->id,
        ]));

        $school = HighSchool::withoutGlobalScopes()->where('code', 'TH001')->firstOrFail();
        $response->assertRedirect("/high-schools/{$school->id}");
        expect($school->organization_id)->toBe($this->org->id)->and($school->tier)->toBe(SchoolTier::A)
            ->and($school->partnership_status)->toBe(PartnershipStatus::Contacting)->and($school->strong_combinations)->toBe(['A00'])
            ->and($school->grade12_students)->toBe(450)->and($school->mou_signed_at->toDateString())->toBe('2026-03-15')
            ->and($school->owner_id)->toBe($this->admin->id)->and($school->created_by)->toBe($this->admin->id);
    });

    it('keeps the code unique per organization, and a missing code is fine', function () {
        ownSchool(['code' => 'TH001']);
        HighSchool::factory()->for($this->other)->create(['code' => 'TH777']);

        $this->actingAs($this->admin)->post('/high-schools', schoolPayload())->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['code' => 'th777']))->assertSessionDoesntHaveErrors();
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['code' => '', 'name' => 'Không mã 1']))->assertSessionDoesntHaveErrors();
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['code' => '', 'name' => 'Không mã 2']))->assertSessionDoesntHaveErrors();
    });

    it('validates enums, the ward against the province, and the owner against the organization', function () {
        $wardElsewhere = Ward::factory()->create(['province_id' => $this->hue->id]);
        $foreignUser = User::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['tier' => 'Z']))->assertSessionHasErrors('tier');
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['type' => 'x']))->assertSessionHasErrors('type');
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['province_id' => $this->hanoi->id, 'ward_id' => $wardElsewhere->id]))->assertSessionHasErrors('ward_id');
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['owner_id' => $foreignUser->id]))->assertSessionHasErrors('owner_id');
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['strong_combinations' => ['NOPE']]))->assertSessionHasErrors('strong_combinations.0');
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['name' => '']))->assertSessionHasErrors('name');
    });

    it('gives a new school without an owner to the owner of its province territory', function () {
        $head = User::factory()->for($this->org)->create();
        $territory = Territory::factory()->for($this->org)->create(['user_id' => $head->id]);
        TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'province', 'assignable_id' => $this->hanoi->id]);

        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['province_id' => $this->hanoi->id]));
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['code' => 'X2', 'province_id' => $this->hue->id]));
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload(['code' => 'X3', 'province_id' => $this->hanoi->id, 'owner_id' => $this->admin->id]));

        expect(HighSchool::withoutGlobalScopes()->where('code', 'TH001')->value('owner_id'))->toBe($head->id)
            ->and(HighSchool::withoutGlobalScopes()->where('code', 'X2')->value('owner_id'))->toBeNull()
            ->and(HighSchool::withoutGlobalScopes()->where('code', 'X3')->value('owner_id'))->toBe($this->admin->id);
    });

    it('updates a school', function () {
        $school = ownSchool(['code' => 'TH001']);

        $this->actingAs($this->admin)->put("/high-schools/{$school->id}", schoolPayload(['name' => 'Tên mới', 'tier' => 'B']))->assertSessionHasNoErrors();

        expect($school->fresh())->name->toBe('Tên mới')->tier->toBe(SchoolTier::B);
    });

    it('deletes a school with its contacts and territory place, freeing the code', function () {
        $school = ownSchool(['code' => 'TH001']);
        SchoolContact::factory()->create(['organization_id' => $this->org->id, 'high_school_id' => $school->id]);
        $territory = Territory::factory()->for($this->org)->create();
        TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'high_school', 'assignable_id' => $school->id]);

        $this->actingAs($this->admin)->delete("/high-schools/{$school->id}")->assertRedirect('/high-schools');

        expect(HighSchool::withoutGlobalScopes()->withTrashed()->find($school->id))->toBeNull()
            ->and(SchoolContact::withoutGlobalScopes()->where('high_school_id', $school->id)->count())->toBe(0)
            ->and(DB::table('territory_assignments')->where('assignable_type', 'high_school')->count())->toBe(0);
        $this->actingAs($this->admin)->post('/high-schools', schoolPayload())->assertSessionDoesntHaveErrors();
    });
});

describe('contacts', function () {
    beforeEach(function () {
        $this->school = ownSchool();
    });

    function contactPayload(array $overrides = []): array
    {
        return ['full_name' => 'Nguyễn Văn A', 'role' => 'career_counselor', 'phone' => '0912345678', 'email' => 'a@thpt.vn', 'relationship_level' => 4, 'is_primary' => false, ...$overrides];
    }

    it('adds, updates and removes contacts', function () {
        $this->actingAs($this->admin)->post("/high-schools/{$this->school->id}/contacts", contactPayload())->assertSessionHasNoErrors();
        $contact = SchoolContact::query()->firstOrFail();
        expect($contact->organization_id)->toBe($this->org->id)->and($contact->high_school_id)->toBe($this->school->id)->and($contact->relationship_level)->toBe(4);

        $this->actingAs($this->admin)->put("/high-schools/{$this->school->id}/contacts/{$contact->id}", contactPayload(['full_name' => 'Trần B', 'role' => 'principal']));
        expect($contact->fresh())->full_name->toBe('Trần B');

        $this->actingAs($this->admin)->delete("/high-schools/{$this->school->id}/contacts/{$contact->id}");
        expect(SchoolContact::query()->count())->toBe(0);
    });

    it('keeps one primary contact per school', function () {
        $this->actingAs($this->admin)->post("/high-schools/{$this->school->id}/contacts", contactPayload(['full_name' => 'Một', 'is_primary' => true]));
        $this->actingAs($this->admin)->post("/high-schools/{$this->school->id}/contacts", contactPayload(['full_name' => 'Hai', 'is_primary' => true]));

        expect(SchoolContact::query()->where('is_primary', true)->pluck('full_name')->all())->toBe(['Hai']);

        $other = ownSchool();
        $this->actingAs($this->admin)->post("/high-schools/{$other->id}/contacts", contactPayload(['full_name' => 'Ba', 'is_primary' => true]));
        expect(SchoolContact::query()->where('is_primary', true)->count())->toBe(2);
    });

    it('lists the primary contact first on the detail page', function () {
        SchoolContact::factory()->create(['organization_id' => $this->org->id, 'high_school_id' => $this->school->id, 'full_name' => 'Thường']);
        SchoolContact::factory()->create(['organization_id' => $this->org->id, 'high_school_id' => $this->school->id, 'full_name' => 'Chính', 'is_primary' => true]);

        $this->actingAs($this->admin)->get("/high-schools/{$this->school->id}")
            ->assertInertia(fn (Assert $page) => $page->component('HighSchools/Show')->has('contacts', 2)->where('contacts.0.full_name', 'Chính')->where('can.contacts.create', true));
    });

    it('validates the contact', function () {
        $this->actingAs($this->admin)->post("/high-schools/{$this->school->id}/contacts", contactPayload(['full_name' => '']))->assertSessionHasErrors('full_name');
        $this->actingAs($this->admin)->post("/high-schools/{$this->school->id}/contacts", contactPayload(['relationship_level' => 9]))->assertSessionHasErrors('relationship_level');
        $this->actingAs($this->admin)->post("/high-schools/{$this->school->id}/contacts", contactPayload(['role' => 'boss']))->assertSessionHasErrors('role');
    });

    it('is limited by school_contact permissions and by the school data scope', function () {
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('high_school.view', 'school_contact.view');
        $this->actingAs($viewer)->post("/high-schools/{$this->school->id}/contacts", contactPayload())->assertForbidden();

        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');
        $counselor->givePermissionTo('school_contact.create');
        $this->actingAs($counselor)->post("/high-schools/{$this->school->id}/contacts", contactPayload())->assertNotFound(); // school outside their scope
        expect(SchoolContact::query()->count())->toBe(0);
    });

    it('does not touch the contact of another school', function () {
        $contact = SchoolContact::factory()->create(['organization_id' => $this->org->id, 'high_school_id' => ownSchool()->id]);

        $this->actingAs($this->admin)->delete("/high-schools/{$this->school->id}/contacts/{$contact->id}")->assertNotFound();
        expect(SchoolContact::query()->count())->toBe(1);
    });
});

describe('Excel import', function () {
    function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('truong.csv', "\xEF\xBB\xBF".$content);
    }

    it('creates schools from Vietnamese headings, resolving province, type, tier and status', function () {
        $file = csv("Mã trường,Tên trường,Loại hình,Tỉnh,Hạng,Trạng thái hợp tác,Số học sinh khối 12,Email\n".
            "th01,THPT Chu Văn An,Công lập,Hà Nội,a,Đối tác,420,a@x.vn\n".
            "th02,THPT Chuyên Huế,chuyên,46,B,mou_signed,300,\n");

        $this->actingAs($this->admin)->post('/high-schools/import', ['file' => $file])->assertSessionHasNoErrors();

        $one = HighSchool::query()->where('code', 'TH01')->firstOrFail();
        $two = HighSchool::query()->where('code', 'TH02')->firstOrFail();
        expect($one->province_id)->toBe($this->hanoi->id)->and($one->type->value)->toBe('public')->and($one->tier)->toBe(SchoolTier::A)
            ->and($one->partnership_status)->toBe(PartnershipStatus::Partner)->and($one->grade12_students)->toBe(420)->and($one->organization_id)->toBe($this->org->id)
            ->and($two->province_id)->toBe($this->hue->id)->and($two->type->value)->toBe('specialized')->and($two->partnership_status)->toBe(PartnershipStatus::MouSigned);
    });

    it('updates a school with the same code using only the filled cells', function () {
        $school = ownSchool(['code' => 'TH01', 'name' => 'Tên cũ', 'phone' => '0900000000', 'tier' => 'C']);

        $this->actingAs($this->admin)->post('/high-schools/import', ['file' => csv("code,name,tier,phone\nTH01,Tên mới,B,\n")]);

        expect($school->fresh())->name->toBe('Tên mới')->tier->toBe(SchoolTier::B)->phone->toBe('0900000000')
            ->and(HighSchool::query()->count())->toBe(1);
    });

    it('matches a school without a code by name and province', function () {
        ownSchool(['code' => null, 'name' => 'THPT Không Mã', 'province_id' => $this->hanoi->id]);

        $this->actingAs($this->admin)->post('/high-schools/import', ['file' => csv("name,province,tier\nTHPT Không Mã,Hà Nội,A\nTHPT Không Mã,Huế,B\n")]);

        expect(HighSchool::query()->count())->toBe(2)->and(HighSchool::query()->where('name', 'THPT Không Mã')->where('province_id', $this->hanoi->id)->value('tier'))->toBe(SchoolTier::A);
    });

    it('skips bad rows but still imports the good ones', function () {
        $this->actingAs($this->admin)->post('/high-schools/import', ['file' => csv(
            "code,name,province,tier,email\nOK1,Trường tốt,Hà Nội,A,\nBAD1,,Hà Nội,A,\nBAD2,Trường lỗi,Atlantis,A,\nBAD3,Hạng sai,Hà Nội,Z,\nBAD4,Email sai,Hà Nội,A,khong-phai-email\n"
        )])->assertSessionHasNoErrors();

        expect(HighSchool::query()->pluck('code')->all())->toBe(['OK1']);
    });

    it('a dry run reports without writing', function () {
        $this->actingAs($this->admin)->post('/high-schools/import', ['file' => csv("code,name\nD1,Một\nD2,Hai\n"), 'dry_run' => true]);

        expect(HighSchool::query()->count())->toBe(0);

        $this->actingAs($this->admin)->post('/high-schools/import', ['file' => csv("code,name\nD1,Một\nD2,Hai\n")]);
        expect(HighSchool::query()->count())->toBe(2);
    });

    it('needs the import permission and a valid file', function () {
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('high_school.view');

        $this->actingAs($viewer)->post('/high-schools/import', ['file' => csv("code,name\nX,Y\n")])->assertForbidden();
        $this->actingAs($this->admin)->post('/high-schools/import', [])->assertSessionHasErrors('file');
        $this->actingAs($this->admin)->post('/high-schools/import', ['file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
        expect(HighSchool::query()->count())->toBe(0);
    });
});
