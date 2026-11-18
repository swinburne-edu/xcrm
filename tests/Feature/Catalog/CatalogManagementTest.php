<?php

use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Catalog\Models\Ward;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $seeder = app(CatalogSeeder::class);
    $seeder->dataDirectory = sys_get_temp_dir().'/xcrm-no-data';
    $seeder->run();

    $this->manager = User::factory()->create();
    $this->manager->givePermissionTo('catalog.manage');

    $this->viewer = User::factory()->create();
    $this->viewer->givePermissionTo('catalog.view');
});

function subjectIds(string ...$codes): array
{
    return array_map(fn ($code) => Subject::where('code', $code)->value('id'), $codes);
}

function comboPayload(string $code, array $weights): array
{
    $ids = subjectIds(...array_keys($weights));

    return [
        'code' => $code,
        'subjects' => array_map(fn ($id, $w) => ['subject_id' => $id, 'weight' => $w], $ids, array_values($weights)),
    ];
}

it('forbids the catalog screen without permission', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/catalogs')
        ->assertForbidden();
});

it('shows every catalog tab to a viewer, read-only', function (string $key) {
    $this->actingAs($this->viewer)
        ->get("/settings/catalogs/{$key}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')
            ->has('tabs', 12)
            ->where('catalog.key', $key)
            ->where('can.manage', false)
            ->has('items.data')
            ->has('breadcrumbs', 3));
})->with(['provinces', 'wards', 'ward-mappings', 'priority-areas', 'priority-groups', 'subjects', 'combinations', 'admission-methods', 'lead-sources', 'lost-reasons', 'call-results', 'tags']);

it('opens the first tab at /settings/catalogs and 404s unknown tabs', function () {
    $this->actingAs($this->viewer)->get('/settings/catalogs')
        ->assertInertia(fn (Assert $page) => $page->where('catalog.key', 'provinces'));

    $this->actingAs($this->viewer)->get('/settings/catalogs/secrets')->assertNotFound();
});

it('lists combinations with filters and sorting', function () {
    $this->actingAs($this->viewer)
        ->get('/settings/catalogs/combinations?filter[subject_id]='.Subject::where('code', 'SINH')->value('id').'&sort=-code')
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.meta.total', 2)
            ->where('items.data.0.code', 'B08')
            ->where('items.data.0.subjects_label', 'Toán, Tiếng Anh, Sinh học')
            ->where('items.data.1.code', 'B00'));
});

it('does not let a viewer change catalogs', function () {
    $this->actingAs($this->viewer)
        ->post('/settings/catalogs/provinces', ['code' => '01', 'name' => 'Hà Nội'])
        ->assertForbidden();

    expect(Province::count())->toBe(0);
});

it('creates, updates and validates provinces', function () {
    $this->actingAs($this->manager)
        ->post('/settings/catalogs/provinces', ['code' => ' 01 ', 'name' => 'Hà Nội', 'type' => 'thanh_pho', 'is_active' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $province = Province::where('code', '01')->first();
    expect($province->name)->toBe('Hà Nội');

    $this->actingAs($this->manager)
        ->post('/settings/catalogs/provinces', ['code' => '01', 'name' => 'Trùng mã'])
        ->assertSessionHasErrors(['code' => 'mã đã tồn tại.']);

    $this->actingAs($this->manager)
        ->put("/settings/catalogs/provinces/{$province->id}", ['code' => '01', 'name' => 'Thành phố Hà Nội', 'type' => 'thanh_pho', 'is_active' => false])
        ->assertSessionHasNoErrors();

    expect($province->fresh()->name)->toBe('Thành phố Hà Nội')
        ->and($province->fresh()->is_active)->toBeFalse();
});

it('creates a combination with weights and auto name (R2 ok)', function () {
    $this->actingAs($this->manager)
        ->post('/settings/catalogs/combinations', comboPayload('x01', ['TOAN' => 2, 'LY' => 1, 'TIN' => 1]))
        ->assertSessionHasNoErrors();

    $combo = SubjectCombination::with('subjects')->where('code', 'X01')->first();

    expect($combo->name)->toBe('Toán, Vật lí, Tin học')
        ->and($combo->subjects->firstWhere('code', 'TOAN')->pivot->weight)->toEqual('2.00');
});

it('rejects combinations that break R2', function (array $weights, string $message) {
    $this->actingAs($this->manager)
        ->post('/settings/catalogs/combinations', comboPayload('X02', $weights))
        ->assertSessionHasErrors(['subjects' => $message]);

    expect(SubjectCombination::where('code', 'X02')->exists())->toBeFalse();
})->with([
    'no math or literature' => [['LY' => 1, 'HOA' => 1, 'SINH' => 1], 'Tổ hợp bắt buộc có môn Toán hoặc Ngữ văn.'],
    'math too light' => [['TOAN' => 1, 'LY' => 2, 'HOA' => 2], 'Trọng số môn Toán/Ngữ văn phải chiếm ít nhất 1/3 tổng trọng số.'],
    'only two subjects' => [['TOAN' => 1, 'LY' => 1], 'Tổ hợp phải có đúng 3 môn.'],
]);

it('updates the subjects of a combination', function () {
    $combo = SubjectCombination::where('code', 'A00')->first();

    $this->actingAs($this->manager)
        ->put("/settings/catalogs/combinations/{$combo->id}", [...comboPayload('A00', ['TOAN' => 1, 'LY' => 1, 'TIN' => 1]), 'name' => 'Toán, Lí, Tin'])
        ->assertSessionHasNoErrors();

    expect($combo->fresh()->subjects->pluck('code')->sort()->values()->all())->toBe(['LY', 'TIN', 'TOAN'])
        ->and($combo->fresh()->name)->toBe('Toán, Lí, Tin');
});

it('refuses to delete a row still in use and deletes unused rows', function () {
    $province = Province::factory()->create(['code' => '40', 'name' => 'Nghệ An']);
    Ward::factory()->for($province)->create();
    $unused = Province::factory()->create(['code' => '01']);

    $this->actingAs($this->manager)
        ->delete("/settings/catalogs/provinces/{$province->id}")
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'error');

    expect(Province::find($province->id))->not->toBeNull();

    $this->actingAs($this->manager)
        ->delete("/settings/catalogs/provinces/{$unused->id}")
        ->assertInertiaFlash('toast.type', 'success');

    expect(Province::find($unused->id))->toBeNull();

    // A subject used by combinations is protected too; deleting a combination removes its pivot rows.
    $this->actingAs($this->manager)->delete('/settings/catalogs/subjects/'.Subject::where('code', 'TOAN')->value('id'));
    expect(Subject::where('code', 'TOAN')->exists())->toBeTrue();

    $a00 = SubjectCombination::where('code', 'A00')->first();
    $this->actingAs($this->manager)->delete("/settings/catalogs/combinations/{$a00->id}");
    expect(DB::table('combination_subjects')->where('combination_id', $a00->id)->count())->toBe(0);
});

it('looks up active provinces and the wards of one province', function () {
    $hanoi = Province::factory()->create(['code' => '01', 'name' => 'Hà Nội']);
    $nghean = Province::factory()->create(['code' => '40', 'name' => 'Nghệ An']);
    Province::factory()->create(['code' => '99', 'name' => 'Hà Ngưng', 'is_active' => false]);
    Ward::factory()->for($hanoi)->create(['name' => 'Ba Đình', 'type' => 'phuong']);
    Ward::factory()->for($nghean)->create(['name' => 'Vinh Phú', 'type' => 'phuong']);

    $this->actingAs($this->viewer)
        ->getJson('/lookups/provinces?q=Hà')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.label', 'Hà Nội');

    $this->actingAs($this->viewer)
        ->getJson("/lookups/wards?province_id={$nghean->id}")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.label', 'Phường Vinh Phú')
        ->assertJsonPath('data.0.description', 'Nghệ An');

    $this->actingAs($this->viewer)
        ->getJson('/lookups/subjects')
        ->assertJsonPath('data.0.description', fn ($d) => str_contains($d, 'cốt lõi'));
});

it('asks to choose all 3 subjects when a row is empty', function () {
    $this->actingAs($this->manager)
        ->post('/settings/catalogs/combinations', [
            'code' => 'X03',
            'subjects' => [
                ['subject_id' => Subject::where('code', 'TOAN')->value('id'), 'weight' => 1],
                ['subject_id' => null, 'weight' => 1],
                ['subject_id' => null, 'weight' => 1],
            ],
        ])
        ->assertSessionHasErrors(['subjects.1.subject_id' => 'Vui lòng chọn đủ 3 môn.'])
        ->assertSessionDoesntHaveErrors(['subjects']);
});
