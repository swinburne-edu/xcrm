<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Enums\StageType;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    app(SeedOrganizationCatalogs::class)->handle($this->other);

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->default = Pipeline::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->where('is_default', true)->firstOrFail();
});

function stagePayload(array $overrides = []): array
{
    return ['code' => 'interview', 'name' => 'Phỏng vấn', 'type' => 'open', 'color' => 'blue', 'probability' => 30, 'sla_hours' => 48, ...$overrides];
}

describe('screen and permissions', function () {
    it('lists the pipelines of the own organization with their stages and case counts', function () {
        $cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
        app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $cycle);
        app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $cycle);

        $this->actingAs($this->admin)->get('/settings/pipelines')
            ->assertInertia(fn (Assert $page) => $page->component('Pipelines/Index')->has('pipelines', 1)
                ->where('pipelines.0.name', 'Đại học chính quy')->where('pipelines.0.is_default', true)->where('pipelines.0.cases_count', 2)
                ->has('pipelines.0.stages', 10)->where('pipelines.0.stages.0.code', 'new')->where('pipelines.0.stages.0.cases_count', 2)
                ->where('pipelines.0.stages.9.type', 'lost')->where('can.manage', true));
    });

    it('is limited by pipeline.view and pipeline.manage and shows in the menu', function () {
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('pipeline.view');
        $nobody = User::factory()->for($this->org)->create();

        $this->actingAs($nobody)->get('/settings/pipelines')->assertForbidden();
        $this->actingAs($viewer)->get('/settings/pipelines')->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
        $this->actingAs($viewer)->post('/settings/pipelines', ['name' => 'X', 'level' => 'college'])->assertForbidden();
        $this->actingAs($viewer)->post("/settings/pipelines/{$this->default->id}/stages", stagePayload())->assertForbidden();
        $this->actingAs($viewer)->delete("/settings/pipelines/{$this->default->id}")->assertForbidden();

        $this->actingAs($this->admin)->get('/settings/pipelines')
            ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($nav) => str_contains(json_encode($nav, JSON_UNESCAPED_SLASHES), '/settings/pipelines')));
    });

    it("does not reach another organization's pipeline", function () {
        $foreign = Pipeline::query()->withoutGlobalScopes()->where('organization_id', $this->other->id)->firstOrFail();

        $this->actingAs($this->admin)->put("/settings/pipelines/{$foreign->id}", ['name' => 'X', 'level' => 'university'])->assertForbidden();
        $this->actingAs($this->admin)->post("/settings/pipelines/{$foreign->id}/stages", stagePayload())->assertForbidden();
        $this->actingAs($this->admin)->delete("/settings/pipelines/{$foreign->id}")->assertNotFound();
        expect($foreign->stages()->count())->toBe(10);
    });
});

describe('pipelines', function () {
    it('creates a pipeline, which is the default when it is the first of its level', function () {
        $this->actingAs($this->admin)->post('/settings/pipelines', ['name' => 'Cao đẳng', 'level' => 'college'])->assertSessionHasNoErrors();

        $college = Pipeline::query()->where('level', 'college')->firstOrFail();
        expect($college)->is_default->toBeTrue()->organization_id->toBe($this->org->id);

        $this->actingAs($this->admin)->post('/settings/pipelines', ['name' => 'Cao đẳng 2', 'level' => 'college'])->assertSessionHasNoErrors();
        expect(Pipeline::query()->where('level', 'college')->where('is_default', true)->count())->toBe(1)
            ->and(Pipeline::query()->where('name', 'Cao đẳng 2')->value('is_default'))->toBeFalse();
    });

    it('makes another pipeline the only default of its level, and keeps one default', function () {
        $second = Pipeline::factory()->for($this->org)->create(['level' => 'university']);

        $this->actingAs($this->admin)->put("/settings/pipelines/{$second->id}", ['name' => 'Mới', 'level' => 'university', 'is_default' => true]);
        expect($second->fresh()->is_default)->toBeTrue()->and($this->default->fresh()->is_default)->toBeFalse();

        $this->actingAs($this->admin)->put("/settings/pipelines/{$second->id}", ['name' => 'Mới', 'level' => 'university', 'is_default' => false]);
        expect($second->fresh()->is_default)->toBeTrue();
    });

    it('validates the pipeline', function () {
        $this->actingAs($this->admin)->post('/settings/pipelines', ['name' => '', 'level' => 'university'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post('/settings/pipelines', ['name' => 'X', 'level' => 'phd'])->assertSessionHasErrors('level');
    });

    it('deletes a spare pipeline but not the default one or one that held cases', function () {
        $spare = Pipeline::factory()->for($this->org)->create();
        $used = Pipeline::factory()->for($this->org)->create(['level' => 'college', 'is_default' => true]);
        $stage = PipelineStage::factory()->for($used)->create();
        $cycle = AdmissionCycle::factory()->for($this->org)->create(['level' => 'college']);
        app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $cycle);
        $notDefault = Pipeline::factory()->for($this->org)->create(['level' => 'college']);

        $this->actingAs($this->admin)->delete("/settings/pipelines/{$this->default->id}")->assertSessionHasErrors('pipeline');
        $this->actingAs($this->admin)->delete("/settings/pipelines/{$used->id}")->assertSessionHasErrors('pipeline');
        $this->actingAs($this->admin)->delete("/settings/pipelines/{$spare->id}")->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/settings/pipelines/{$notDefault->id}")->assertSessionHasNoErrors();

        expect(Pipeline::query()->find($spare->id))->toBeNull()->and(Pipeline::query()->find($used->id))->not->toBeNull()
            ->and($stage->fresh())->not->toBeNull();
    });
});

describe('stages', function () {
    it('adds a stage at the end, with its colour, probability, SLA and required fields', function () {
        $this->actingAs($this->admin)->post("/settings/pipelines/{$this->default->id}/stages", stagePayload(['required_fields' => ['primary_major_id', 'temperature']]))->assertSessionHasNoErrors();

        $stage = $this->default->stages()->where('code', 'interview')->firstOrFail();
        expect($stage)->name->toBe('Phỏng vấn')->type->toBe(StageType::Open)->color->toBe('blue')->probability->toBe(30)->sla_hours->toBe(48)
            ->required_fields->toBe(['primary_major_id', 'temperature'])
            ->and($stage->sort_order)->toBeGreaterThan($this->default->stages()->where('code', 'lost')->value('sort_order'));
    });

    it('updates a stage and respects an explicit order', function () {
        $stage = $this->default->stages()->where('code', 'counseling')->firstOrFail();

        $this->actingAs($this->admin)->put("/settings/pipelines/{$this->default->id}/stages/{$stage->id}", stagePayload(['code' => 'counseling', 'name' => 'Tư vấn sâu', 'sort_order' => 5, 'type' => 'open']))->assertSessionHasNoErrors();

        expect($stage->fresh())->name->toBe('Tư vấn sâu')->sort_order->toBe(5)
            ->and($this->default->stages()->first()->code)->toBe('counseling');
    });

    it('validates the stage', function () {
        $url = "/settings/pipelines/{$this->default->id}/stages";

        $this->actingAs($this->admin)->post($url, stagePayload(['code' => 'new']))->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post($url, stagePayload(['code' => 'Có Dấu']))->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->post($url, stagePayload(['type' => 'x']))->assertSessionHasErrors('type');
        $this->actingAs($this->admin)->post($url, stagePayload(['color' => 'purple']))->assertSessionHasErrors('color');
        $this->actingAs($this->admin)->post($url, stagePayload(['probability' => 150]))->assertSessionHasErrors('probability');
        $this->actingAs($this->admin)->post($url, stagePayload(['sla_hours' => 0]))->assertSessionHasErrors('sla_hours');
        $this->actingAs($this->admin)->post($url, stagePayload(['required_fields' => ['password']]))->assertSessionHasErrors('required_fields.0');

        // the same code is fine in another pipeline
        $other = Pipeline::factory()->for($this->org)->create(['level' => 'college']);
        $this->actingAs($this->admin)->post("/settings/pipelines/{$other->id}/stages", stagePayload(['code' => 'new']))->assertSessionHasNoErrors();
    });

    it('keeps at least one open, one won and one lost stage', function () {
        $won = $this->default->stages()->where('code', 'enrolled')->firstOrFail();
        $lost = $this->default->stages()->where('code', 'lost')->firstOrFail();
        $base = "/settings/pipelines/{$this->default->id}/stages";

        $this->actingAs($this->admin)->put("{$base}/{$won->id}", stagePayload(['code' => 'enrolled', 'type' => 'open']))->assertSessionHasErrors('type');
        $this->actingAs($this->admin)->delete("{$base}/{$won->id}")->assertSessionHasErrors('stage');
        $this->actingAs($this->admin)->delete("{$base}/{$lost->id}")->assertSessionHasErrors('stage');
        expect($won->fresh()->type)->toBe(StageType::Won);

        $this->actingAs($this->admin)->post($base, stagePayload(['code' => 'won2', 'type' => 'won']));
        $this->actingAs($this->admin)->put("{$base}/{$won->id}", stagePayload(['code' => 'enrolled', 'type' => 'open']))->assertSessionHasNoErrors();
    });

    it('deletes an unused stage but not one that cases have been in', function () {
        $cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
        app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $cycle);
        $used = $this->default->stages()->where('code', 'new')->firstOrFail();
        $unused = $this->default->stages()->where('code', 'eligible')->firstOrFail();
        $base = "/settings/pipelines/{$this->default->id}/stages";

        $this->actingAs($this->admin)->delete("{$base}/{$used->id}")->assertSessionHasErrors('stage');
        $this->actingAs($this->admin)->delete("{$base}/{$unused->id}")->assertSessionHasNoErrors();

        expect($used->fresh())->not->toBeNull()->and($unused->fresh())->toBeNull();
    });

    it('does not touch a stage of another pipeline', function () {
        $other = Pipeline::factory()->for($this->org)->create(['level' => 'college']);
        $stage = PipelineStage::factory()->for($other)->create();

        $this->actingAs($this->admin)->delete("/settings/pipelines/{$this->default->id}/stages/{$stage->id}")->assertNotFound();
        $this->actingAs($this->admin)->put("/settings/pipelines/{$this->default->id}/stages/{$stage->id}", stagePayload())->assertNotFound();
        expect($stage->fresh())->not->toBeNull();
    });
});
