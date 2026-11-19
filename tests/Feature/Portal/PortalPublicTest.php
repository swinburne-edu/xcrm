<?php

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Models\Organization;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Task 3.4, public side: the score and major suggestion page and its lead form.
 * Module under test: ScoreEstimator and EstimatorController. No earlier test file.
 */
beforeEach(function () {
    $this->seed([RoleSeeder::class, CatalogSeeder::class]);
    $this->org = Organization::factory()->create(['name' => 'Đại học Demo']);
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    config(['portal.organization_id' => $this->org->id]);

    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create(['name' => 'Tuyển sinh 2027']);
    $method = CycleMethod::factory()->create(['cycle_id' => $this->cycle->id, 'score_formula' => ['source' => 'thpt_exam', 'scale_to' => 30, 'min_total' => 18]]);
    $this->major = Major::factory()->for($this->org)->create(['name' => 'Công nghệ thông tin']);
    DB::table('major_cycle_methods')->insert(['major_id' => $this->major->id, 'cycle_method_id' => $method->id]);
    DB::table('major_combinations')->insert(['major_id' => $this->major->id, 'combination_id' => SubjectCombination::query()->where('code', 'A00')->value('id'), 'cycle_id' => $this->cycle->id]);
});

it('shows the public page without login and suggests majors from the scores', function () {
    $this->get('/tuyen-sinh')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Portal/Estimator')->where('school', 'Đại học Demo')->where('cycle', 'Tuyển sinh 2027')->has('subjects'));

    $this->postJson('/tuyen-sinh/tinh-diem', ['scores' => ['TOAN' => 8, 'LY' => 7, 'HOA' => 9], 'bonus' => 0.5])->assertOk()
        ->assertJsonPath('suggestions.0.major_name', 'Công nghệ thông tin')
        ->assertJsonPath('suggestions.0.best.combination', 'A00')
        ->assertJsonPath('suggestions.0.best.score', 24.5)
        ->assertJsonPath('suggestions.0.best.meets_minimum', true);

    $this->postJson('/tuyen-sinh/tinh-diem', ['scores' => ['TOAN' => 4, 'LY' => 4, 'HOA' => 4]])->assertJsonPath('suggestions.0.best.meets_minimum', false);
    $this->postJson('/tuyen-sinh/tinh-diem', ['scores' => ['TOAN' => 8, 'LY' => 7]])->assertJsonPath('suggestions', []);
    $this->postJson('/tuyen-sinh/tinh-diem', ['scores' => ['TOAN' => 11]])->assertUnprocessable();
});

it('takes a lead from the public page only with consent, and ignores bots', function () {
    $this->postJson('/tuyen-sinh/dang-ky', ['full_name' => 'Lê Minh', 'phone' => '0912345678'])->assertUnprocessable()->assertJsonValidationErrors('consent');
    $this->postJson('/tuyen-sinh/dang-ky', ['full_name' => 'Bot', 'phone' => '0912345670', 'hp' => 'x'])->assertOk();
    expect(Applicant::query()->count())->toBe(0);

    $this->postJson('/tuyen-sinh/dang-ky', ['full_name' => 'Lê Minh', 'phone' => '0912345678', 'consent' => true])->assertCreated();
    expect(Applicant::query()->where('phone_normalized', '84912345678')->count())->toBe(1);
    expect(Http::recorded())->toHaveCount(0);
});

it('answers politely when no cycle is open', function () {
    $this->cycle->update(['is_current' => false, 'status' => 'closed']);

    $this->postJson('/tuyen-sinh/tinh-diem', ['scores' => ['TOAN' => 8]])->assertOk()->assertJsonPath('suggestions', []);
});
