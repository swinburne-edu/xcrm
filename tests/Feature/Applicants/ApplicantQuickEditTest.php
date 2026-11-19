<?php

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Ward;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);

    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->applicant = Applicant::factory()->for($this->org)->create(['full_name' => 'Nguyễn Văn An', 'phone' => '0912345678', 'email' => 'an@example.com']);
    $this->patch = fn (string $field, mixed $value, ?Applicant $a = null) => $this->patch('/applicants/'.($a ?? $this->applicant)->uuid.'/field', ['field' => $field, 'value' => $value]);
});

it('edits one field at a time and leaves the rest alone', function () {
    $this->actingAs($this->admin);

    ($this->patch)('full_name', '  Trần Thị Bình ')->assertSessionHasNoErrors();
    ($this->patch)('email', 'Binh@Example.com')->assertSessionHasNoErrors();
    ($this->patch)('graduation_year', '2027')->assertSessionHasNoErrors();

    $a = $this->applicant->fresh();
    expect($a->full_name)->toBe('Trần Thị Bình')->and($a->email)->toBe('binh@example.com')->and($a->graduation_year)->toBe(2027)->and($a->phone)->toBe('0912345678');
});

it('normalizes a new phone number', function () {
    $this->actingAs($this->admin);

    ($this->patch)('phone', '+84 988 111 222')->assertSessionHasNoErrors();

    expect($this->applicant->fresh()->phone_normalized)->toBe('84988111222');
});

it('clears an optional field with an empty value but never the name', function () {
    $this->actingAs($this->admin);

    ($this->patch)('email', '')->assertSessionHasNoErrors();
    ($this->patch)('full_name', '')->assertSessionHasErrors('value');

    expect($this->applicant->fresh()->email)->toBeNull()->and($this->applicant->fresh()->full_name)->toBe('Nguyễn Văn An');
});

it('rejects a phone or email that belongs to another applicant and names who', function () {
    $other = Applicant::factory()->for($this->org)->create(['full_name' => 'Lê Văn Cường', 'phone' => '0988000111', 'email' => 'cuong@example.com']);
    $this->actingAs($this->admin);

    ($this->patch)('phone', '0988 000 111')->assertSessionHasErrors('value');
    ($this->patch)('email', 'CUONG@example.com')->assertSessionHasErrors('value');

    expect(session('errors')->first('value'))->toContain($other->code)->and($this->applicant->fresh()->phone)->toBe('0912345678');
});

it('rejects bad values', function () {
    $this->actingAs($this->admin);

    ($this->patch)('phone', '123')->assertSessionHasErrors('value');
    ($this->patch)('email', 'khong-phai-email')->assertSessionHasErrors('value');
    ($this->patch)('graduation_year', '1990')->assertSessionHasErrors('value');
    ($this->patch)('citizen_id', '012345678901')->assertSessionHasErrors('field');
    ($this->patch)('high_school_id', '999999')->assertSessionHasErrors('value');
});

it('clears the ward when the province changes and fills the priority area from the school', function () {
    $area = PriorityArea::query()->first();
    $hanoi = Province::factory()->create();
    $this->applicant->update(['province_id' => Province::factory()->create()->id, 'ward_id' => Ward::factory()->create()->id, 'priority_area_id' => null]);
    $school = HighSchool::factory()->create(['organization_id' => $this->org->id, 'priority_area_id' => $area?->id]);
    $this->actingAs($this->admin);

    ($this->patch)('province_id', (string) $hanoi->id)->assertSessionHasNoErrors();
    ($this->patch)('high_school_id', (string) $school->id)->assertSessionHasNoErrors();

    $a = $this->applicant->fresh();
    expect($a->province_id)->toBe($hanoi->id)->and($a->ward_id)->toBeNull()->and($a->high_school_id)->toBe($school->id)->and($a->priority_area_id)->toBe($area?->id);
});

it('keeps a user without full phone access from changing the phone here', function () {
    $user = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $user->givePermissionTo(['applicant.view', 'applicant.update']);
    $this->actingAs($user);

    ($this->patch)('phone', '0977000111')->assertSessionHasErrors('value');
    ($this->patch)('email', 'moi@example.com')->assertSessionHasNoErrors();

    expect($this->applicant->fresh()->phone)->toBe('0912345678')->and($this->applicant->fresh()->email)->toBe('moi@example.com');
});

it('needs update permission and data scope', function () {
    $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $viewer->givePermissionTo('applicant.view');
    $foreign = Applicant::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($viewer);
    ($this->patch)('email', 'x@example.com')->assertForbidden();

    $this->actingAs($this->counselor);
    ($this->patch)('email', 'x@example.com')->assertForbidden(); // not the owner of this applicant
    ($this->patch)('email', 'x@example.com', $foreign)->assertForbidden(); // other organization: same answer as a plain update
    expect($this->applicant->fresh()->email)->toBe('an@example.com');
});

it('requires login', function () {
    $this->patch('/applicants/'.$this->applicant->uuid.'/field', ['field' => 'email', 'value' => 'x@example.com'])->assertRedirect(route('login'));
});

it('lists the stage, owner and last activity of the newest case with the edit flags', function () {
    $cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $case = AdmissionCase::factory()->create(['organization_id' => $this->org->id, 'applicant_id' => $this->applicant->id, 'cycle_id' => $cycle->id, 'owner_id' => $this->admin->id, 'last_activity_at' => now()]);

    $this->actingAs($this->admin)->get('/applicants')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('applicants.data.0.owner_name', $this->admin->name)->where('applicants.data.0.stage_name', $case->stage->name)
        ->where('applicants.data.0.can_edit', true)->where('applicants.data.0.sensitive_masked', false)->where('applicants.data.0.province_id', $this->applicant->province_id)
        ->whereNot('applicants.data.0.last_activity_at', null));
});

it('returns the whole record for the edit form opened from the list', function () {
    $this->applicant->update(['dob' => '2008-05-20', 'address' => '12 Phố Huế', 'zalo_id' => 'zalo-1', 'consent_marketing' => true]);

    $this->actingAs($this->admin)->getJson('/applicants/'.$this->applicant->uuid.'/edit')->assertOk()
        ->assertJsonPath('applicant.full_name', 'Nguyễn Văn An')->assertJsonPath('applicant.dob', '2008-05-20')->assertJsonPath('applicant.address', '12 Phố Huế')
        ->assertJsonPath('applicant.zalo_id', 'zalo-1')->assertJsonPath('applicant.consent_marketing', true)->assertJsonPath('applicant.phone', '0912345678')
        ->assertJsonStructure(['applicant' => ['uuid', 'gender', 'province_id', 'ward_id', 'high_school_id', 'custom_data', 'citizen_id']]);
});

it('does not give the edit record to someone who cannot update it', function () {
    $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $viewer->givePermissionTo('applicant.view');

    $this->actingAs($viewer)->getJson('/applicants/'.$this->applicant->uuid.'/edit')->assertForbidden();
    $this->actingAs($this->counselor)->getJson('/applicants/'.$this->applicant->uuid.'/edit')->assertNotFound();
});
