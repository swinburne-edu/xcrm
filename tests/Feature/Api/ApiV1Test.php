<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Task 3.5, REST API: token authentication, abilities, permissions and data scope, applicants and cases.
 * Module under test: app/Http/Controllers/Api/V1 and ApiAbility. No earlier test file.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');

    $this->token = fn (User $u, array $abilities = ['read', 'write']) => $u->createToken('t', $abilities)->plainTextToken;
    // The guard remembers the user of the previous request inside one test: forget it so each call uses its own token.
    $this->api = function (User $u, array $abilities = ['read', 'write']) {
        app('auth')->forgetGuards();

        return $this->withToken(($this->token)($u, $abilities))->withHeader('Accept', 'application/json');
    };
    $this->caseFor = fn (User $owner, array $attrs = []) => app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create($attrs), $this->cycle, ['owner_id' => $owner->id]);
});

it('rejects calls without a valid token and tokens without the needed ability', function () {
    $this->getJson('/api/v1/applicants')->assertUnauthorized();
    $this->withToken('nonsense')->getJson('/api/v1/applicants')->assertUnauthorized();

    ($this->api)($this->admin, ['read'])->postJson('/api/v1/applicants', ['full_name' => 'A', 'phone' => '0912345678'])->assertForbidden();
    ($this->api)($this->admin, ['write'])->getJson('/api/v1/applicants')->assertForbidden();
    ($this->api)($this->admin, ['read'])->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $this->admin->email);
});

it('lists applicants with pagination and filters, masks the phone without the sensitive permission and hides the citizen id', function () {
    Applicant::factory()->for($this->org)->count(12)->create(['graduation_year' => 2027, 'phone' => '0912345670']);
    Applicant::factory()->for($this->org)->create(['graduation_year' => 2030, 'phone' => '0912345671']);
    Applicant::factory()->for(Organization::factory()->create())->create(['graduation_year' => 2027]);

    $res = ($this->api)($this->admin)->getJson('/api/v1/applicants?filter[graduation_year]=2027&per_page=10')->assertOk();
    expect($res->json('data'))->toHaveCount(10)->and($res->json('meta.total'))->toBe(12)->and($res->json('meta.last_page'))->toBe(2);
    expect($res->json('data.0'))->not->toHaveKey('citizen_id')->and($res->json('data.0.phone'))->toBe('0912345670');

    $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $viewer->givePermissionTo('applicant.view');
    expect(($this->api)($viewer)->getJson('/api/v1/applicants')->json('data.0.phone'))->not->toBe('0912345670');
});

it('follows the data scope of the token owner', function () {
    $mine = ($this->caseFor)($this->counselor);
    $theirs = ($this->caseFor)($this->admin);

    $res = ($this->api)($this->counselor)->getJson('/api/v1/cases')->assertOk();
    expect(collect($res->json('data'))->pluck('uuid')->all())->toBe([$mine->uuid]);
    ($this->api)($this->counselor)->getJson("/api/v1/cases/{$theirs->uuid}")->assertNotFound();
    ($this->api)($this->admin)->getJson("/api/v1/cases/{$theirs->uuid}")->assertOk()->assertJsonPath('data.stage.code', 'new');
});

it('creates an applicant once and reports the duplicate, then updates fields', function () {
    $first = ($this->api)($this->admin)->postJson('/api/v1/applicants', ['full_name' => 'Lê Minh', 'phone' => '0912345678', 'graduation_year' => 2027])->assertCreated();
    expect($first->json('created'))->toBeTrue();
    $uuid = $first->json('data.uuid');

    ($this->api)($this->admin)->postJson('/api/v1/applicants', ['full_name' => 'Lê Minh', 'phone' => '0912 345 678'])->assertOk()->assertJsonPath('created', false)->assertJsonPath('data.uuid', $uuid);
    ($this->api)($this->admin)->postJson('/api/v1/applicants', ['full_name' => 'Không liên hệ'])->assertUnprocessable();

    ($this->api)($this->admin)->patchJson("/api/v1/applicants/{$uuid}", ['full_name' => 'Lê Minh Anh', 'graduation_year' => 2028])->assertOk()->assertJsonPath('data.full_name', 'Lê Minh Anh');
    ($this->api)($this->admin)->patchJson("/api/v1/applicants/{$uuid}", ['email' => 'not-an-email'])->assertUnprocessable();

    $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $viewer->givePermissionTo(['applicant.view', 'applicant.update']);
    ($this->api)($viewer)->patchJson("/api/v1/applicants/{$uuid}", ['phone' => '0987654321'])->assertForbidden();
});

it('moves a case to another stage within its pipeline', function () {
    $case = ($this->caseFor)($this->admin);
    $stage = PipelineStage::query()->where('pipeline_id', $case->pipeline_id)->where('code', 'contacted')->firstOrFail();

    ($this->api)($this->admin)->postJson("/api/v1/cases/{$case->uuid}/stage", ['stage_id' => $stage->id])->assertOk()->assertJsonPath('data.stage.code', 'contacted');
    ($this->api)($this->admin)->postJson("/api/v1/cases/{$case->uuid}/stage", ['stage_id' => 999999])->assertNotFound();
    ($this->api)($this->counselor)->postJson("/api/v1/cases/{$case->uuid}/stage", ['stage_id' => $stage->id])->assertNotFound();
});
