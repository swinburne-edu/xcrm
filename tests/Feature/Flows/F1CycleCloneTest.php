<?php

use App\Domain\Catalog\Models\AdmissionMethod;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * F1 step 1: "Sao chép từ mùa trước" copies rounds (dates moved by the year difference), cycle methods,
 * major quotas and the major ↔ combination / method links into a new cycle in preparation.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->source = AdmissionCycle::factory()->for($this->org)->current()->create([
        'year' => 2026, 'name' => 'Tuyển sinh 2026', 'start_date' => '2025-10-01', 'end_date' => '2026-09-30',
        'target_enrolled' => 2000, 'regulation_ref' => 'TT 06/2026',
    ]);
    AdmissionRound::factory()->for($this->source, 'cycle')->create([
        'name' => 'Đợt 1', 'sort_order' => 10, 'apply_start' => '2026-07-01 01:00:00', 'apply_end' => '2026-07-20 10:30:00',
        'result_date' => '2026-08-01', 'confirm_deadline' => '2026-08-10 16:59:00',
    ]);
    AdmissionRound::factory()->for($this->source, 'cycle')->create(['name' => 'Đợt bổ sung', 'sort_order' => 20]);

    $this->methods = collect(range(1, 5))->map(fn ($i) => CycleMethod::factory()->for($this->source, 'cycle')->create([
        'method_id' => AdmissionMethod::factory()->create()->id,
        'display_name' => "PT {$i}",
        'score_formula' => ['source' => 'thpt_exam', 'use_combination' => true, 'min_total' => 15],
        'required_documents' => ['citizen_id'],
    ]));

    $this->majorA = Major::factory()->for($this->org)->create();
    $this->majorB = Major::factory()->for($this->org)->create();
    $this->combo = SubjectCombination::factory()->create();

    MajorQuota::factory()->create(['organization_id' => $this->org->id, 'major_id' => $this->majorA->id, 'cycle_id' => $this->source->id, 'quota_total' => 200, 'quota_direct' => 20, 'previous_cutoffs' => [['year' => 2025, 'method' => '100', 'score' => 24.5]]]);
    MajorQuota::factory()->create(['organization_id' => $this->org->id, 'major_id' => $this->majorB->id, 'cycle_id' => $this->source->id, 'quota_total' => 80]);
    DB::table('major_combinations')->insert([
        ['major_id' => $this->majorA->id, 'combination_id' => $this->combo->id, 'cycle_id' => $this->source->id],
        ['major_id' => $this->majorB->id, 'combination_id' => $this->combo->id, 'cycle_id' => $this->source->id],
    ]);
    DB::table('major_cycle_methods')->insert([
        ['major_id' => $this->majorA->id, 'cycle_method_id' => $this->methods[0]->id],
        ['major_id' => $this->majorA->id, 'cycle_method_id' => $this->methods[1]->id],
        ['major_id' => $this->majorB->id, 'cycle_method_id' => $this->methods[0]->id],
    ]);
});

function cloneIt(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->admin)->post('/settings/cycles/'.test()->source->id.'/clone', ['name' => 'Tuyển sinh 2027', 'year' => 2027, ...$overrides]);
}

it('creates a cycle in preparation with every record copied', function () {
    $response = cloneIt();

    $new = AdmissionCycle::query()->where('year', 2027)->firstOrFail();
    $response->assertRedirect("/settings/cycles/{$new->id}");

    expect($new->name)->toBe('Tuyển sinh 2027')
        ->and($new->status)->toBe(CycleStatus::Preparing)
        ->and($new->is_current)->toBeFalse()
        ->and($new->level)->toBe($this->source->level)
        ->and($new->target_enrolled)->toBe(2000)
        ->and($new->regulation_ref)->toBe('TT 06/2026')
        ->and($new->start_date->toDateString())->toBe('2026-10-01')
        ->and($new->end_date->toDateString())->toBe('2027-09-30')
        ->and($new->rounds()->count())->toBe(2)
        ->and($new->methods()->count())->toBe(5)
        ->and(MajorQuota::query()->where('cycle_id', $new->id)->count())->toBe(2)
        ->and(DB::table('major_combinations')->where('cycle_id', $new->id)->count())->toBe(2)
        ->and(DB::table('major_cycle_methods')->whereIn('cycle_method_id', $new->methods()->pluck('id'))->count())->toBe(3);
});

it('moves round dates by the difference in years and keeps their order', function () {
    cloneIt();
    $new = AdmissionCycle::query()->where('year', 2027)->firstOrFail();
    $round = $new->rounds()->first();

    expect($round->name)->toBe('Đợt 1')
        ->and($round->apply_start->utc()->format('Y-m-d H:i'))->toBe('2027-07-01 01:00')
        ->and($round->apply_end->utc()->format('Y-m-d H:i'))->toBe('2027-07-20 10:30')
        ->and($round->result_date->toDateString())->toBe('2027-08-01')
        ->and($round->confirm_deadline->utc()->format('Y-m-d H:i'))->toBe('2027-08-10 16:59')
        ->and($new->rounds()->pluck('name')->all())->toBe(['Đợt 1', 'Đợt bổ sung']);
});

it('copies methods with their formula and keeps the major links on the new rows', function () {
    cloneIt();
    $new = AdmissionCycle::query()->where('year', 2027)->firstOrFail();
    $copy = $new->methods()->where('display_name', 'PT 1')->firstOrFail();

    expect($copy->score_formula)->toBe(['source' => 'thpt_exam', 'use_combination' => true, 'min_total' => 15])
        ->and($copy->required_documents)->toBe(['citizen_id'])
        ->and($copy->method_id)->toBe($this->methods[0]->method_id)
        ->and($copy->id)->not->toBe($this->methods[0]->id)
        ->and(DB::table('major_cycle_methods')->where('cycle_method_id', $copy->id)->pluck('major_id')->sort()->values()->all())
        ->toBe(collect([$this->majorA->id, $this->majorB->id])->sort()->values()->all());
});

it('keeps the old quota as a suggestion, with previous cutoffs', function () {
    cloneIt();
    $new = AdmissionCycle::query()->where('year', 2027)->firstOrFail();
    $quota = MajorQuota::query()->where('cycle_id', $new->id)->where('major_id', $this->majorA->id)->firstOrFail();

    expect($quota->quota_total)->toBe(200)->and($quota->quota_direct)->toBe(20)
        ->and($quota->previous_cutoffs)->toBe([['year' => 2025, 'method' => '100', 'score' => 24.5]])
        ->and($quota->organization_id)->toBe($this->org->id);
});

it('leaves the source cycle untouched and still current', function () {
    cloneIt();

    expect($this->source->fresh()->is_current)->toBeTrue()
        ->and($this->source->rounds()->count())->toBe(2)
        ->and($this->source->methods()->count())->toBe(5)
        ->and(MajorQuota::query()->where('cycle_id', $this->source->id)->count())->toBe(2)
        ->and(DB::table('major_combinations')->where('cycle_id', $this->source->id)->count())->toBe(2);
});

it('can clone a closed cycle', function () {
    $this->source->forceFill(['status' => CycleStatus::Closed, 'is_current' => false])->save();

    cloneIt()->assertSessionHasNoErrors();

    expect(AdmissionCycle::query()->where('year', 2027)->exists())->toBeTrue();
});

it('rejects a year already used by the same level, and bad input', function () {
    AdmissionCycle::factory()->for($this->org)->create(['year' => 2027]);

    cloneIt()->assertSessionHasErrors('year');
    cloneIt(['year' => 2028, 'name' => ''])->assertSessionHasErrors('name');
    expect(AdmissionCycle::query()->count())->toBe(2);
});

it('allows the same year for another level', function () {
    AdmissionCycle::factory()->for($this->org)->create(['year' => 2027, 'level' => 'college']);

    cloneIt()->assertSessionHasNoErrors();
});

it('R1 still applies to the copy: a 6th non-direct method is refused', function () {
    cloneIt();
    $new = AdmissionCycle::query()->where('year', 2027)->firstOrFail();
    $sixth = AdmissionMethod::factory()->create();

    $this->actingAs($this->admin)->post("/settings/cycles/{$new->id}/methods", [
        'method_id' => $sixth->id, 'is_direct' => false, 'score_formula' => ['source' => 'thpt_exam'],
    ])->assertSessionHasErrors('is_direct');

    expect($new->methods()->count())->toBe(5);
});

it('lets the new cycle become current afterwards', function () {
    cloneIt();
    $new = AdmissionCycle::query()->where('year', 2027)->firstOrFail();

    $this->actingAs($this->admin)->post("/settings/cycles/{$new->id}/current")->assertSessionHasNoErrors();

    expect($new->fresh())->is_current->toBeTrue()->status->toBe(CycleStatus::Active)
        ->and($this->source->fresh()->is_current)->toBeFalse();
});

it('needs cycle.create and a cycle of the own organization', function () {
    $counselor = User::factory()->for($this->org)->create();
    $counselor->assignRole('counselor');
    $foreign = AdmissionCycle::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($counselor)->post("/settings/cycles/{$this->source->id}/clone", ['name' => 'X', 'year' => 2027])->assertForbidden();
    $this->actingAs($this->admin)->post("/settings/cycles/{$foreign->id}/clone", ['name' => 'X', 'year' => 2027])->assertForbidden();
    expect(AdmissionCycle::query()->count())->toBe(1);
});
