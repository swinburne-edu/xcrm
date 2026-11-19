<?php

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Platform\Enums\CustomFieldObject;
use App\Domain\Platform\Enums\CustomFieldType;
use App\Domain\Platform\Models\CustomFieldDefinition;
use App\Domain\Platform\Services\CustomFields;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');
    $this->viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->viewer->givePermissionTo(['custom_field.view']);
    $this->actingAs($this->admin);

    $this->define = fn (string $key, CustomFieldType $type, array $extra = [], CustomFieldObject $object = CustomFieldObject::Applicants) => CustomFieldDefinition::factory()->create([
        'organization_id' => $this->org->id, 'object' => $object, 'key' => $key, 'label' => ucfirst($key), 'type' => $type,
    ] + $extra);

    $this->makeCase = function (array $extra = []) {
        $cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
        $pipeline = Pipeline::factory()->for($this->org)->create();
        $stage = PipelineStage::factory()->for($pipeline)->create();

        return AdmissionCase::factory()->create(['organization_id' => $this->org->id, 'cycle_id' => $cycle->id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id] + $extra);
    };
});

/* ---------- definitions screen ---------- */

it('lists the definitions grouped by object for someone with custom_field.view', function () {
    ($this->define)('hoc_bong', CustomFieldType::Select, ['options' => ['A', 'B']]);
    ($this->define)('ghi_chu_truong', CustomFieldType::Text, [], CustomFieldObject::HighSchools);

    $this->get('/settings/custom-fields')->assertOk()->assertInertia(fn (Assert $page) => $page->component('CustomFields/Index')
        ->has('fields', 2)->where('fields.0.object_label', 'Thí sinh')->where('fields.0.type_label', 'Chọn một')->where('fields.0.options', ['A', 'B'])
        ->has('options.objects', 6)->has('options.types', 10)->where('can.manage', true));

    $this->actingAs($this->viewer)->get('/settings/custom-fields')->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
});

it('needs custom_field.view and custom_field.manage', function () {
    $this->actingAs($this->counselor)->get('/settings/custom-fields')->assertForbidden();
    $this->actingAs($this->viewer)->post('/settings/custom-fields', ['object' => 'applicants', 'key' => 'abc', 'label' => 'Abc', 'type' => 'text'])->assertForbidden();
    expect(CustomFieldDefinition::query()->count())->toBe(0);
});

it('creates a field and appends it at the end of its object', function () {
    $this->post('/settings/custom-fields', ['object' => 'applicants', 'key' => 'hoc_bong', 'label' => ' Học bổng ', 'type' => 'select', 'options' => ['Toàn phần', ' Một phần ', '', 'Toàn phần'], 'is_required' => true])
        ->assertSessionHasNoErrors();
    $this->post('/settings/custom-fields', ['object' => 'applicants', 'key' => 'ngay_hen', 'label' => 'Ngày hẹn', 'type' => 'date'])->assertSessionHasNoErrors();

    $first = CustomFieldDefinition::query()->where('key', 'hoc_bong')->firstOrFail();
    $second = CustomFieldDefinition::query()->where('key', 'ngay_hen')->firstOrFail();
    expect($first->label)->toBe('Học bổng')->and($first->options)->toBe(['Toàn phần', 'Một phần'])->and($first->is_required)->toBeTrue()->and($first->is_filterable)->toBeTrue()
        ->and($first->organization_id)->toBe($this->org->id)->and($first->created_by)->toBe($this->admin->id)
        ->and($second->options)->toBeNull()->and($second->sort_order)->toBe($first->sort_order + 1);
});

it('validates the key, the type and the options of a select', function () {
    ($this->define)('da_co', CustomFieldType::Text);

    $this->post('/settings/custom-fields', ['object' => 'applicants', 'key' => 'Có Dấu', 'label' => 'x', 'type' => 'text'])->assertSessionHasErrors('key');
    $this->post('/settings/custom-fields', ['object' => 'applicants', 'key' => '1abc', 'label' => 'x', 'type' => 'text'])->assertSessionHasErrors('key');
    $this->post('/settings/custom-fields', ['object' => 'applicants', 'key' => 'da_co', 'label' => 'x', 'type' => 'text'])->assertSessionHasErrors('key');
    $this->post('/settings/custom-fields', ['object' => 'khac', 'key' => 'abc', 'label' => 'x', 'type' => 'text'])->assertSessionHasErrors('object');
    $this->post('/settings/custom-fields', ['object' => 'applicants', 'key' => 'abc', 'label' => 'x', 'type' => 'so'])->assertSessionHasErrors('type');
    $this->post('/settings/custom-fields', ['object' => 'applicants', 'key' => 'abc', 'label' => '', 'type' => 'text'])->assertSessionHasErrors('label');
    $this->post('/settings/custom-fields', ['object' => 'applicants', 'key' => 'abc', 'label' => 'x', 'type' => 'select', 'options' => ['', ' ']])->assertSessionHasErrors('options');

    // the same key is fine on another object
    $this->post('/settings/custom-fields', ['object' => 'high_schools', 'key' => 'da_co', 'label' => 'x', 'type' => 'text'])->assertSessionHasNoErrors();
});

it('edits label, options and flags but never the key or the type', function () {
    $field = ($this->define)('muc', CustomFieldType::Select, ['options' => ['A']]);

    $this->put("/settings/custom-fields/{$field->id}", ['label' => 'Mức mới', 'key' => 'doi_key', 'type' => 'number', 'object' => 'high_schools', 'options' => ['A', 'B'], 'is_filterable' => false, 'sort_order' => 7])
        ->assertSessionHasNoErrors();

    $field->refresh();
    expect($field->label)->toBe('Mức mới')->and($field->key)->toBe('muc')->and($field->type)->toBe(CustomFieldType::Select)->and($field->object)->toBe(CustomFieldObject::Applicants)
        ->and($field->options)->toBe(['A', 'B'])->and($field->is_filterable)->toBeFalse()->and($field->sort_order)->toBe(7);
});

it('deletes a definition but keeps the stored values', function () {
    $field = ($this->define)('ghi_nho', CustomFieldType::Text);
    $applicant = Applicant::factory()->create(['organization_id' => $this->org->id, 'custom_data' => ['ghi_nho' => 'abc']]);

    $this->delete("/settings/custom-fields/{$field->id}")->assertSessionHasNoErrors();

    expect(CustomFieldDefinition::query()->count())->toBe(0)->and($applicant->fresh()->custom_data)->toBe(['ghi_nho' => 'abc'])
        ->and(app(CustomFields::class)->fields(CustomFieldObject::Applicants, $applicant->fresh()))->toBe([]);
});

it('never touches another organization', function () {
    $other = Organization::factory()->create();
    $foreign = CustomFieldDefinition::factory()->create(['organization_id' => $other->id]);

    $this->put("/settings/custom-fields/{$foreign->id}", ['label' => 'Hack'])->assertNotFound();
    $this->delete("/settings/custom-fields/{$foreign->id}")->assertNotFound();
    expect(app(CustomFields::class)->definitions(CustomFieldObject::Applicants))->toHaveCount(0);
});

/* ---------- inline edit of values ---------- */

it('saves values by type, drops blanks and keeps keys it was not sent', function () {
    ($this->define)('hoc_bong', CustomFieldType::Select, ['options' => ['A', 'B']]);
    ($this->define)('so_con', CustomFieldType::Number);
    ($this->define)('can_goi', CustomFieldType::Boolean);
    ($this->define)('mon_thich', CustomFieldType::Multiselect, ['options' => ['Toán', 'Lý', 'Hoá']]);
    ($this->define)('ngay_hen', CustomFieldType::Date);
    ($this->define)('ghi_chu', CustomFieldType::Text);
    $applicant = Applicant::factory()->create(['organization_id' => $this->org->id, 'custom_data' => ['ghi_chu' => 'giữ', 'cu' => 'x']]);

    $this->put("/custom-data/applicants/{$applicant->id}", ['custom' => [
        'hoc_bong' => 'B', 'so_con' => '3', 'can_goi' => true, 'mon_thich' => ['Toán', 'Lý', 'Toán'], 'ngay_hen' => '2026-12-20', 'khong_co' => 'bo qua',
    ]])->assertSessionHasNoErrors();

    expect($applicant->fresh()->custom_data)->toBe(['ghi_chu' => 'giữ', 'cu' => 'x', 'hoc_bong' => 'B', 'so_con' => 3, 'can_goi' => true, 'mon_thich' => ['Toán', 'Lý'], 'ngay_hen' => '2026-12-20']);

    $this->put("/custom-data/applicants/{$applicant->id}", ['custom' => ['hoc_bong' => '', 'so_con' => null, 'mon_thich' => []]])->assertSessionHasNoErrors();

    expect($applicant->fresh()->custom_data)->toBe(['ghi_chu' => 'giữ', 'cu' => 'x', 'can_goi' => true, 'ngay_hen' => '2026-12-20']);
});

it('rejects values that do not fit the field', function () {
    ($this->define)('hoc_bong', CustomFieldType::Select, ['options' => ['A', 'B']]);
    ($this->define)('so_con', CustomFieldType::Number);
    ($this->define)('ngay_hen', CustomFieldType::Date);
    ($this->define)('trang_web', CustomFieldType::Url);
    ($this->define)('thu', CustomFieldType::Email);
    ($this->define)('dt', CustomFieldType::Phone);
    ($this->define)('mon', CustomFieldType::Multiselect, ['options' => ['Toán']]);
    $applicant = Applicant::factory()->create(['organization_id' => $this->org->id]);

    $this->put("/custom-data/applicants/{$applicant->id}", ['custom' => [
        'hoc_bong' => 'C', 'so_con' => 'abc', 'ngay_hen' => '20/12/2026', 'trang_web' => 'khong phai url', 'thu' => 'x', 'dt' => '123', 'mon' => ['Văn'],
    ]])->assertSessionHasErrors(['custom.hoc_bong', 'custom.so_con', 'custom.ngay_hen', 'custom.trang_web', 'custom.thu', 'custom.dt', 'custom.mon.0']);

    expect($applicant->fresh()->custom_data)->toBeNull();
});

it('enforces required fields on inline edit', function () {
    ($this->define)('bat_buoc', CustomFieldType::Text, ['is_required' => true]);
    $applicant = Applicant::factory()->create(['organization_id' => $this->org->id]);

    $this->put("/custom-data/applicants/{$applicant->id}", ['custom' => ['bat_buoc' => '']])->assertSessionHasErrors('custom.bat_buoc');
    $this->put("/custom-data/applicants/{$applicant->id}", ['custom' => ['bat_buoc' => 'ok']])->assertSessionHasNoErrors();
});

it('lets the record policy decide who may edit values', function () {
    ($this->define)('ghi_chu', CustomFieldType::Text);
    $mine = Applicant::factory()->create(['organization_id' => $this->org->id, 'referred_by_user_id' => $this->counselor->id]);
    $theirs = Applicant::factory()->create(['organization_id' => $this->org->id]);

    $this->actingAs($this->viewer)->put("/custom-data/applicants/{$mine->id}", ['custom' => ['ghi_chu' => 'x']])->assertForbidden();
    $this->actingAs($this->counselor)->put("/custom-data/applicants/{$theirs->id}", ['custom' => ['ghi_chu' => 'x']])->assertNotFound();
    expect($mine->fresh()->custom_data)->toBeNull()->and($theirs->fresh()->custom_data)->toBeNull();
});

it('returns 404 for an unknown object and for a record of another organization', function () {
    $other = Organization::factory()->create();
    $foreign = Applicant::factory()->create(['organization_id' => $other->id]);

    $this->put('/custom-data/khong_co/1', ['custom' => []])->assertNotFound();
    $this->put("/custom-data/applicants/{$foreign->id}", ['custom' => []])->assertNotFound();
});

it('edits the custom fields of a high school and of a case', function () {
    ($this->define)('tuyen_sinh_thang', CustomFieldType::Boolean, [], CustomFieldObject::HighSchools);
    ($this->define)('ly_do_khac', CustomFieldType::Textarea, [], CustomFieldObject::AdmissionCases);
    $school = HighSchool::factory()->create(['organization_id' => $this->org->id]);
    $case = ($this->makeCase)();

    $this->put("/custom-data/high_schools/{$school->id}", ['custom' => ['tuyen_sinh_thang' => true]])->assertSessionHasNoErrors();
    $this->put("/custom-data/admission_cases/{$case->id}", ['custom' => ['ly_do_khac' => "dòng 1\ndòng 2"]])->assertSessionHasNoErrors();

    expect($school->fresh()->custom_data)->toBe(['tuyen_sinh_thang' => true])->and($case->fresh()->custom_data)->toBe(['ly_do_khac' => "dòng 1\ndòng 2"]);
});

/* ---------- dynamic forms ---------- */

it('adds custom fields to the applicant form: validated and saved with the record', function () {
    ($this->define)('hoc_bong', CustomFieldType::Select, ['options' => ['A', 'B']]);
    ($this->define)('bat_buoc', CustomFieldType::Text, ['is_required' => true]);

    $this->post('/applicants', ['full_name' => 'Nguyễn Văn A', 'phone' => '0912345678', 'custom' => ['hoc_bong' => 'Z', 'bat_buoc' => '']])
        ->assertSessionHasErrors(['custom.hoc_bong', 'custom.bat_buoc']);

    $this->post('/applicants', ['full_name' => 'Nguyễn Văn A', 'phone' => '0912345678', 'custom' => ['hoc_bong' => 'A', 'bat_buoc' => 'có']])->assertSessionHasNoErrors();

    $applicant = Applicant::query()->where('full_name', 'Nguyễn Văn A')->firstOrFail();
    expect($applicant->custom_data)->toBe(['hoc_bong' => 'A', 'bat_buoc' => 'có']);

    $this->put("/applicants/{$applicant->uuid}", ['full_name' => 'Nguyễn Văn A', 'phone' => '0912345678', 'custom' => ['hoc_bong' => 'B', 'bat_buoc' => 'có']])->assertSessionHasNoErrors();
    expect($applicant->fresh()->custom_data['hoc_bong'])->toBe('B');

    // a form that does not send "custom" at all still saves and leaves the values alone (required counts only when the block is sent)
    $this->put("/applicants/{$applicant->uuid}", ['full_name' => 'Nguyễn Văn B', 'phone' => '0912345678'])->assertSessionHasNoErrors();
    expect($applicant->fresh()->custom_data)->toBe(['hoc_bong' => 'B', 'bat_buoc' => 'có'])->and($applicant->fresh()->full_name)->toBe('Nguyễn Văn B');
});

it('adds custom fields to the high school form', function () {
    ($this->define)('so_gv', CustomFieldType::Number, [], CustomFieldObject::HighSchools);

    $this->post('/high-schools', ['name' => 'THPT Mẫu', 'custom' => ['so_gv' => 'nhiều']])->assertSessionHasErrors('custom.so_gv');
    $this->post('/high-schools', ['name' => 'THPT Mẫu', 'custom' => ['so_gv' => '85']])->assertSessionHasNoErrors();

    expect(HighSchool::query()->where('name', 'THPT Mẫu')->firstOrFail()->custom_data)->toBe(['so_gv' => 85]);
});

it('hands the form fields, the filters and the detail panel to the screens', function () {
    ($this->define)('hoc_bong', CustomFieldType::Select, ['options' => ['A', 'B']]);
    ($this->define)('khong_loc', CustomFieldType::Text, ['is_filterable' => false]);
    $applicant = Applicant::factory()->create(['organization_id' => $this->org->id, 'custom_data' => ['hoc_bong' => 'B']]);

    $this->get('/applicants')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('options.customFields', 2)->has('options.customFilters', 1)
        ->where('options.customFilters.0', ['key' => 'cf_hoc_bong', 'label' => 'Hoc_bong', 'type' => 'select', 'options' => [['value' => 'A', 'label' => 'A'], ['value' => 'B', 'label' => 'B']]]));

    $this->get("/applicants/{$applicant->uuid}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('custom_fields.object', 'applicants')->where('custom_fields.id', $applicant->id)->has('custom_fields.fields', 2)
        ->where('custom_fields.fields.0.value', 'B')->where('custom_fields.fields.1.value', null)->where('applicant.custom_data.hoc_bong', 'B'));
});

/* ---------- list filters ---------- */

it('filters lists by every field type', function () {
    ($this->define)('hoc_bong', CustomFieldType::Select, ['options' => ['A', 'B']]);
    ($this->define)('so_con', CustomFieldType::Number);
    ($this->define)('can_goi', CustomFieldType::Boolean);
    ($this->define)('mon', CustomFieldType::Multiselect, ['options' => ['Toán', 'Lý']]);
    ($this->define)('ngay_hen', CustomFieldType::Date);
    ($this->define)('ghi_chu', CustomFieldType::Text);

    $make = fn (string $name, array $data) => Applicant::factory()->create(['organization_id' => $this->org->id, 'full_name' => $name, 'custom_data' => $data]);
    $make('Một', ['hoc_bong' => 'A', 'so_con' => 3, 'can_goi' => true, 'mon' => ['Toán'], 'ngay_hen' => '2026-12-10', 'ghi_chu' => 'quan tâm học bổng']);
    $make('Hai', ['hoc_bong' => 'B', 'so_con' => 5, 'can_goi' => false, 'mon' => ['Toán', 'Lý'], 'ngay_hen' => '2026-12-25', 'ghi_chu' => 'cần gọi lại 100%']);
    $make('Ba', []);

    $names = fn (array $filter) => collect($this->get('/applicants?'.http_build_query(['filter' => $filter]))->assertOk()->viewData('page')['props']['applicants']['data'])->pluck('full_name')->sort()->values()->all();

    expect($names(['cf_hoc_bong' => 'A']))->toBe(['Một'])
        ->and($names(['cf_so_con' => '5']))->toBe(['Hai'])
        ->and($names(['cf_can_goi' => '1']))->toBe(['Một'])
        ->and($names(['cf_can_goi' => '0']))->toBe(['Hai'])
        ->and($names(['cf_mon' => 'Lý']))->toBe(['Hai'])
        ->and($names(['cf_mon' => 'Toán']))->toBe(['Hai', 'Một'])
        ->and($names(['cf_ngay_hen' => ['from' => '2026-12-15']]))->toBe(['Hai'])
        ->and($names(['cf_ngay_hen' => ['from' => '2026-12-01', 'to' => '2026-12-15']]))->toBe(['Một'])
        ->and($names(['cf_ghi_chu' => 'học bổng']))->toBe(['Một'])
        ->and($names(['cf_ghi_chu' => '100%']))->toBe(['Hai'])
        ->and($names(['cf_ghi_chu' => '%']))->toBe(['Hai'])
        ->and($names(['cf_khong_co' => 'x']))->toBe(['Ba', 'Hai', 'Một']);
});

it('does not filter by a field that is not filterable', function () {
    ($this->define)('khong_loc', CustomFieldType::Text, ['is_filterable' => false]);
    Applicant::factory()->create(['organization_id' => $this->org->id, 'full_name' => 'Một', 'custom_data' => ['khong_loc' => 'x']]);
    Applicant::factory()->create(['organization_id' => $this->org->id, 'full_name' => 'Hai']);

    $rows = $this->get('/applicants?filter[cf_khong_loc]=x')->assertOk()->viewData('page')['props']['applicants']['data'];

    expect($rows)->toHaveCount(2);
});

it('filters high schools and cases by custom fields too', function () {
    ($this->define)('doi_tac', CustomFieldType::Select, ['options' => ['Vàng', 'Bạc']], CustomFieldObject::HighSchools);
    HighSchool::factory()->create(['organization_id' => $this->org->id, 'name' => 'THPT Vàng', 'custom_data' => ['doi_tac' => 'Vàng']]);
    HighSchool::factory()->create(['organization_id' => $this->org->id, 'name' => 'THPT Bạc', 'custom_data' => ['doi_tac' => 'Bạc']]);

    $rows = $this->get('/high-schools?filter[cf_doi_tac]='.urlencode('Vàng'))->assertOk()->viewData('page')['props']['schools']['data'];
    expect(collect($rows)->pluck('name')->all())->toBe(['THPT Vàng']);

    ($this->define)('nhom', CustomFieldType::Text, [], CustomFieldObject::AdmissionCases);
    $this->get('/cases/table?filter[owner]=all&filter[cf_nhom]=abc')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('options.customFilters', 1)->where('cases.query.filters.cf_nhom', 'abc'));
});

it('shows the custom fields of a case in the drawer data', function () {
    ($this->define)('nhom', CustomFieldType::Text, [], CustomFieldObject::AdmissionCases);
    $case = ($this->makeCase)(['custom_data' => ['nhom' => 'Nhóm 1']]);

    $this->getJson("/cases/{$case->uuid}")->assertOk()
        ->assertJsonPath('case.custom_fields.object', 'admission_cases')->assertJsonPath('case.custom_fields.id', $case->id)
        ->assertJsonPath('case.custom_fields.fields.0.value', 'Nhóm 1')->assertJsonPath('case.can.update', true);
});
