<?php

use App\Domain\Catalog\Models\AdmissionMethod;
use App\Domain\Cycle\Enums\CycleStatus;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Cycle\Services\ScoreFormulaValidator;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->methods = collect(range(1, 7))->map(fn ($i) => AdmissionMethod::factory()->create(['code' => "PT{$i}", 'name' => "Phương thức {$i}"]));
});

function cyclePayload(array $overrides = []): array
{
    return ['name' => 'Tuyển sinh 2101', 'year' => 2101, 'level' => 'university', 'status' => 'preparing', 'is_current' => false, ...$overrides];
}

function methodPayload(AdmissionMethod $method, array $overrides = []): array
{
    return ['method_id' => $method->id, 'is_direct' => false, 'score_formula' => ['source' => 'thpt_exam', 'use_combination' => true], 'required_documents' => ['citizen_id'], ...$overrides];
}

/** A cycle of the admin's organization ('preparing' is the factory default). */
function ownCycle(string $state = 'preparing', array $attrs = []): AdmissionCycle
{
    $factory = AdmissionCycle::factory()->for(test()->org);

    return ($state === 'preparing' ? $factory : $factory->{$state}())->create($attrs);
}

describe('list and permissions', function () {
    it('lists cycles of the own organization only', function () {
        $mine = ownCycle();
        AdmissionCycle::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->get('/settings/cycles')
            ->assertInertia(fn (Assert $page) => $page->component('Cycles/Index')
                ->has('cycles.data', 1)->where('cycles.data.0.id', $mine->id));
    });

    it('is limited to cycle.view, and writes to cycle.create/update', function () {
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');
        $cycle = ownCycle();

        $this->actingAs($counselor)->post('/settings/cycles', cyclePayload())->assertForbidden();
        $this->actingAs($counselor)->put("/settings/cycles/{$cycle->id}", cyclePayload())->assertForbidden();

        $viewer = User::factory()->for($this->org)->create();
        $viewer->givePermissionTo('cycle.view');
        $this->actingAs($viewer)->get("/settings/cycles/{$cycle->id}")
            ->assertInertia(fn (Assert $page) => $page->where('can.update', false));
        $this->actingAs($viewer)->post("/settings/cycles/{$cycle->id}/rounds", ['name' => 'Đợt 1'])->assertForbidden();
    });

    it('does not show a cycle of another organization', function () {
        $foreign = AdmissionCycle::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->get("/settings/cycles/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->admin)->post("/settings/cycles/{$foreign->id}/rounds", ['name' => 'X'])->assertNotFound();
    });

    it('shows the menu item for users with cycle.view', function () {
        $this->actingAs($this->admin)->get('/settings/cycles')
            ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($nav) => str_contains(json_encode($nav, JSON_UNESCAPED_SLASHES), '/settings/cycles')));
    });
});

describe('create and update', function () {
    it('creates a cycle and redirects to its page', function () {
        $response = $this->actingAs($this->admin)->post('/settings/cycles', cyclePayload());

        $cycle = AdmissionCycle::withoutGlobalScopes()->where('year', 2101)->firstOrFail();
        $response->assertRedirect("/settings/cycles/{$cycle->id}");
        expect($cycle->organization_id)->toBe($this->org->id)->and($cycle->status)->toBe(CycleStatus::Preparing);
    });

    it('rejects a duplicate year for the same level in the same organization', function () {
        ownCycle(attrs: ['year' => 2101]);

        $this->actingAs($this->admin)->post('/settings/cycles', cyclePayload())->assertSessionHasErrors('year');
        // other level is fine, and so is another organization's year
        $this->actingAs($this->admin)->post('/settings/cycles', cyclePayload(['level' => 'college']))->assertSessionDoesntHaveErrors();
        AdmissionCycle::factory()->for($this->other)->create(['year' => 2102]);
        $this->actingAs($this->admin)->post('/settings/cycles', cyclePayload(['year' => 2102, 'name' => 'B']))->assertSessionDoesntHaveErrors();
    });

    it('requires the end date to be on or after the start date', function () {
        $this->actingAs($this->admin)->post('/settings/cycles', cyclePayload(['start_date' => '2101-06-01', 'end_date' => '2101-05-01']))
            ->assertSessionHasErrors('end_date');
    });

    it('updates a cycle', function () {
        $cycle = ownCycle(attrs: ['year' => 2101]);

        $this->actingAs($this->admin)->put("/settings/cycles/{$cycle->id}", cyclePayload(['name' => 'Mới', 'target_enrolled' => 1500]))
            ->assertSessionHasNoErrors();

        expect($cycle->fresh())->name->toBe('Mới')->target_enrolled->toBe(1500);
    });
});

describe('R10: one current cycle per level', function () {
    it('makes the new current cycle the only one and activates it', function () {
        $old = ownCycle('current');
        $new = ownCycle();

        $this->actingAs($this->admin)->post("/settings/cycles/{$new->id}/current")->assertSessionHasNoErrors();

        expect($new->fresh())->is_current->toBeTrue()->status->toBe(CycleStatus::Active)
            ->and($old->fresh()->is_current)->toBeFalse();
    });

    it('keeps the current cycles of other levels and organizations', function () {
        $college = ownCycle('current', ['level' => 'college']);
        $foreign = AdmissionCycle::factory()->for($this->other)->current()->create();
        $new = ownCycle();

        $this->actingAs($this->admin)->post("/settings/cycles/{$new->id}/current");

        expect($college->fresh()->is_current)->toBeTrue()->and($foreign->fresh()->is_current)->toBeTrue();
    });

    it('can be requested when creating a cycle', function () {
        $old = ownCycle('current');

        $this->actingAs($this->admin)->post('/settings/cycles', cyclePayload(['is_current' => true]));

        expect($old->fresh()->is_current)->toBeFalse()
            ->and(AdmissionCycle::withoutGlobalScopes()->where('organization_id', $this->org->id)->where('is_current', true)->count())->toBe(1);
    });

    it('does not allow a closed cycle to be current', function () {
        $closed = ownCycle('closed');

        $this->actingAs($this->admin)->post("/settings/cycles/{$closed->id}/current")->assertForbidden();
        expect($closed->fresh()->is_current)->toBeFalse();
    });
});

describe('R8: a closed cycle is read-only', function () {
    it('rejects every change but reopening', function () {
        $closed = ownCycle('closed', ['year' => 2101]);
        $round = AdmissionRound::factory()->for($closed, 'cycle')->create();
        $method = CycleMethod::factory()->for($closed, 'cycle')->create(['method_id' => $this->methods[0]->id]);

        $this->actingAs($this->admin)->put("/settings/cycles/{$closed->id}", cyclePayload())->assertForbidden();
        $this->actingAs($this->admin)->post("/settings/cycles/{$closed->id}/rounds", ['name' => 'Đợt mới'])->assertForbidden();
        $this->actingAs($this->admin)->put("/settings/cycles/{$closed->id}/rounds/{$round->id}", ['name' => 'Sửa'])->assertForbidden();
        $this->actingAs($this->admin)->delete("/settings/cycles/{$closed->id}/rounds/{$round->id}")->assertForbidden();
        $this->actingAs($this->admin)->post("/settings/cycles/{$closed->id}/methods", methodPayload($this->methods[1]))->assertForbidden();
        $this->actingAs($this->admin)->delete("/settings/cycles/{$closed->id}/methods/{$method->id}")->assertForbidden();

        expect($closed->rounds()->count())->toBe(1)->and($closed->methods()->count())->toBe(1);
    });

    it('can be reopened', function () {
        $closed = ownCycle('closed');

        $this->actingAs($this->admin)->get("/settings/cycles/{$closed->id}")
            ->assertInertia(fn (Assert $page) => $page->where('can.reopen', true)->where('can.update', false));

        $this->actingAs($this->admin)->post("/settings/cycles/{$closed->id}/reopen")->assertSessionHasNoErrors();

        expect($closed->fresh()->status)->toBe(CycleStatus::Active);
    });

    it('only reopens closed cycles', function () {
        $active = ownCycle('active');

        $this->actingAs($this->admin)->post("/settings/cycles/{$active->id}/reopen")->assertForbidden();
    });
});

describe('delete', function () {
    it('deletes a preparing cycle that is not current', function () {
        $cycle = ownCycle();

        $this->actingAs($this->admin)->delete("/settings/cycles/{$cycle->id}")->assertRedirect('/settings/cycles');

        expect(AdmissionCycle::withoutGlobalScopes()->find($cycle->id))->toBeNull();
    });

    it('refuses active or current cycles', function () {
        $active = ownCycle('current');

        $this->actingAs($this->admin)->delete("/settings/cycles/{$active->id}");

        expect($active->fresh())->not->toBeNull();
    });
});

describe('rounds', function () {
    it('stores times entered in Vietnam time as UTC and shows them back unchanged', function () {
        $cycle = ownCycle();

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/rounds", [
            'name' => 'Đợt 1', 'type' => 'moet_system',
            'apply_start' => '2101-07-01T08:00', 'apply_end' => '2101-07-20T17:30',
            'result_date' => '2101-08-01', 'confirm_deadline' => '2101-08-10T23:59',
        ])->assertSessionHasNoErrors();

        $round = $cycle->rounds()->firstOrFail();
        expect($round->apply_start->utc()->format('Y-m-d H:i'))->toBe('2101-07-01 01:00')
            ->and($round->apply_end->utc()->format('Y-m-d H:i'))->toBe('2101-07-20 10:30');

        $this->actingAs($this->admin)->get("/settings/cycles/{$cycle->id}")
            ->assertInertia(fn (Assert $page) => $page->where('rounds.0.apply_start', '2101-07-01T08:00')
                ->where('rounds.0.confirm_deadline', '2101-08-10T23:59')
                ->where('rounds.0.result_date', '2101-08-01'));
    });

    it('requires the close time to be after the open time and the confirmation after the close', function () {
        $cycle = ownCycle();

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/rounds", [
            'name' => 'Đợt 1', 'apply_start' => '2101-07-20T08:00', 'apply_end' => '2101-07-01T08:00',
        ])->assertSessionHasErrors();

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/rounds", [
            'name' => 'Đợt 1', 'apply_start' => '2101-07-01T08:00', 'apply_end' => '2101-07-20T08:00', 'confirm_deadline' => '2101-07-10T08:00',
        ])->assertSessionHasErrors();
    });

    it('keeps rounds in order, updates and deletes them', function () {
        $cycle = ownCycle();

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/rounds", ['name' => 'Đợt 1']);
        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/rounds", ['name' => 'Đợt 2']);
        expect($cycle->rounds()->pluck('name')->all())->toBe(['Đợt 1', 'Đợt 2']);

        $first = $cycle->rounds()->first();
        $this->actingAs($this->admin)->put("/settings/cycles/{$cycle->id}/rounds/{$first->id}", ['name' => 'Đợt sớm']);
        expect($first->fresh()->name)->toBe('Đợt sớm');

        $this->actingAs($this->admin)->delete("/settings/cycles/{$cycle->id}/rounds/{$first->id}");
        expect($cycle->rounds()->count())->toBe(1);
    });

    it('does not touch the round of another cycle', function () {
        $cycle = ownCycle();
        $otherCycle = ownCycle();
        $round = AdmissionRound::factory()->for($otherCycle, 'cycle')->create();

        $this->actingAs($this->admin)->delete("/settings/cycles/{$cycle->id}/rounds/{$round->id}")->assertNotFound();
        expect(AdmissionRound::find($round->id))->not->toBeNull();
    });
});

describe('methods', function () {
    it('adds a method with a structured formula', function () {
        $cycle = ownCycle();

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[0], [
            'score_formula' => ['source' => 'transcript', 'terms' => ['11', '12-1'], 'aggregate' => 'avg', 'scale_to' => 30, 'priority' => ['apply' => true, 'taper_from' => 22.5]],
        ]))->assertSessionHasNoErrors();

        expect($cycle->methods()->first()->score_formula)->toMatchArray(['source' => 'transcript', 'aggregate' => 'avg', 'scale_to' => 30]);
    });

    it('accepts the formula as JSON text (advanced mode)', function () {
        $cycle = ownCycle();

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[0], [
            'score_formula' => '{"source":"thpt_exam","use_combination":true,"min_total":15}',
            'conditions' => '{"min_gpa": 6.5}',
        ]))->assertSessionHasNoErrors();

        $method = $cycle->methods()->first();
        expect($method->score_formula['min_total'])->toBe(15)->and($method->conditions)->toBe(['min_gpa' => 6.5]);
    });

    it('rejects invalid formulas', function (mixed $formula) {
        $cycle = ownCycle();

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[0], ['score_formula' => $formula]))
            ->assertSessionHasErrors('score_formula');
        expect($cycle->methods()->count())->toBe(0);
    })->with([
        'not json' => ['{source:'],
        'unknown key' => [['source' => 'thpt_exam', 'bogus' => 1]],
        'unknown source' => [['source' => 'magic']],
        'no source' => [['aggregate' => 'avg']],
        'bad aggregate' => [['source' => 'transcript', 'aggregate' => 'median']],
        'negative scale' => [['source' => 'thpt_exam', 'scale_to' => -3]],
        'list instead of object' => [['thpt_exam']],
    ]);

    it('needs a formula unless the method is direct admission', function () {
        $cycle = ownCycle();

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[0], ['score_formula' => null]))
            ->assertSessionHasErrors('score_formula');

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[0], ['score_formula' => null, 'is_direct' => true]))
            ->assertSessionHasNoErrors();
    });

    it('R1: allows at most 5 non-direct methods per cycle, direct ones excluded', function () {
        $cycle = ownCycle();

        foreach (range(0, 4) as $i) {
            $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[$i]))->assertSessionHasNoErrors();
        }

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[5]))->assertSessionHasErrors('is_direct');
        expect($cycle->methods()->count())->toBe(5);

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[5], ['is_direct' => true, 'score_formula' => null]))
            ->assertSessionHasNoErrors();
        expect($cycle->methods()->count())->toBe(6);
    });

    it('R1: cannot turn a 6th method from direct to non-direct', function () {
        $cycle = ownCycle();
        foreach (range(0, 4) as $i) {
            CycleMethod::factory()->for($cycle, 'cycle')->create(['method_id' => $this->methods[$i]->id]);
        }
        $direct = CycleMethod::factory()->direct()->for($cycle, 'cycle')->create(['method_id' => $this->methods[5]->id]);

        $this->actingAs($this->admin)->put("/settings/cycles/{$cycle->id}/methods/{$direct->id}", methodPayload($this->methods[5]))
            ->assertSessionHasErrors('is_direct');
        expect($direct->fresh()->is_direct)->toBeTrue();
    });

    it('can edit one of the five without tripping the limit', function () {
        $cycle = ownCycle();
        foreach (range(0, 4) as $i) {
            CycleMethod::factory()->for($cycle, 'cycle')->create(['method_id' => $this->methods[$i]->id]);
        }
        $first = $cycle->methods()->first();

        $this->actingAs($this->admin)->put("/settings/cycles/{$cycle->id}/methods/{$first->id}", methodPayload($this->methods[0], ['display_name' => 'Đổi tên']))
            ->assertSessionHasNoErrors();
        expect($first->fresh()->display_name)->toBe('Đổi tên');
    });

    it('uses a method once per cycle', function () {
        $cycle = ownCycle();
        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[0]));

        $this->actingAs($this->admin)->post("/settings/cycles/{$cycle->id}/methods", methodPayload($this->methods[0]))->assertSessionHasErrors('method_id');
    });

    it('removes a method', function () {
        $cycle = ownCycle();
        $method = CycleMethod::factory()->for($cycle, 'cycle')->create(['method_id' => $this->methods[0]->id]);

        $this->actingAs($this->admin)->delete("/settings/cycles/{$cycle->id}/methods/{$method->id}");
        expect($cycle->methods()->count())->toBe(0);
    });

    it('exposes a readable summary of the formula', function () {
        $cycle = ownCycle();
        CycleMethod::factory()->for($cycle, 'cycle')->create([
            'method_id' => $this->methods[0]->id,
            'score_formula' => ['source' => 'transcript', 'terms' => ['12'], 'aggregate' => 'avg', 'scale_to' => 30],
        ]);

        $this->actingAs($this->admin)->get("/settings/cycles/{$cycle->id}")
            ->assertInertia(fn (Assert $page) => $page->where('methods.0.formula_summary', 'Học bạ THPT · kỳ 12 · trung bình · thang 30'));
    });
});

describe('ScoreFormulaValidator', function () {
    it('accepts a full formula including certificate conversion', function () {
        $formula = [
            'source' => 'thpt_exam', 'use_combination' => true, 'aggregate' => 'sum', 'subject_scale' => 10, 'scale_to' => 30,
            'priority' => ['apply' => true, 'taper_from' => 22.5], 'encourage_bonus_max' => 3, 'min_total' => 15,
            'certificate_conversion' => ['subject' => 'ANH', 'table' => ['IELTS' => ['5.0' => 8.0, '6.5' => 10.0]]],
        ];

        expect(app(ScoreFormulaValidator::class)->errors($formula))->toBe([])
            ->and(app(ScoreFormulaValidator::class)->passes(null))->toBeTrue();
    });

    it('rejects a broken certificate table', function () {
        expect(app(ScoreFormulaValidator::class)->errors(['source' => 'thpt_exam', 'certificate_conversion' => ['subject' => 'anh', 'table' => []]]))->not->toBe([]);
    });
});

describe('header cycle selector', function () {
    it('defaults to the current cycle and shares the cycles of the organization', function () {
        ownCycle(attrs: ['year' => 2101]);
        $current = ownCycle('current', ['year' => 2102]);
        AdmissionCycle::factory()->for($this->other)->current()->create();

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('currentCycle.id', $current->id)->has('cycles', 2));
    });

    it('falls back to the most recent cycle when none is current', function () {
        ownCycle(attrs: ['year' => 2101]);
        $latest = ownCycle(attrs: ['year' => 2103]);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('currentCycle.id', $latest->id));
    });

    it('is null when the organization has no cycle', function () {
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('currentCycle', null)->where('cycles', []));
    });

    it('remembers the selection in the session', function () {
        ownCycle('current', ['year' => 2102]);
        $chosen = ownCycle('closed', ['year' => 2100]);

        $this->actingAs($this->admin)->post('/cycle/select', ['cycle_id' => $chosen->id])->assertRedirect();

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('currentCycle.id', $chosen->id));
    });

    it('does not accept a cycle of another organization', function () {
        $foreign = AdmissionCycle::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->post('/cycle/select', ['cycle_id' => $foreign->id])->assertSessionHasErrors('cycle_id');
    });
});
