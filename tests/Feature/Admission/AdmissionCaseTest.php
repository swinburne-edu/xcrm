<?php

use App\Domain\Admission\Actions\AssignCase;
use App\Domain\Admission\Actions\ClaimCase;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Actions\MoveCaseStage;
use App\Domain\Admission\Actions\SeedDefaultPipeline;
use App\Domain\Admission\Enums\CaseStatus;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Catalog\Models\LostReason;
use App\Domain\Catalog\Models\Province;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Major\Models\Major;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Models\TerritoryAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    app(SeedOrganizationCatalogs::class)->handle($this->other);

    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->applicant = Applicant::factory()->for($this->org)->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->stage = fn (string $code) => PipelineStage::query()->whereHas('pipeline', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $this->org->id))->where('code', $code)->firstOrFail();
    $this->create = fn (?Applicant $applicant = null, array $data = [], ?AdmissionCycle $cycle = null) => app(CreateAdmissionCase::class)->handle($applicant ?? $this->applicant, $cycle ?? $this->cycle, $data, $this->admin);
});

function fails(callable $callback, string $key): void
{
    try {
        $callback();
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($key);

        return;
    }

    throw new RuntimeException('Expected a ValidationException on '.$key);
}

describe('default pipeline', function () {
    it('seeds the ten stages of docs/04-database.md for every organization, idempotently', function () {
        $pipeline = Pipeline::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->firstOrFail();
        $stages = $pipeline->stages;

        expect($pipeline)->name->toBe('Đại học chính quy')->is_default->toBeTrue()
            ->and($stages->pluck('code')->all())->toBe(['new', 'contacted', 'counseling', 'early_applied', 'eligible', 'moet_registered', 'admitted', 'confirmed', 'enrolled', 'lost'])
            ->and($stages->pluck('probability')->all())->toBe([5, 10, 20, 40, 55, 65, 80, 95, 100, 0])
            ->and($stages->where('type.value', 'won')->pluck('code')->all())->toBe(['enrolled'])
            ->and($stages->where('type.value', 'lost')->pluck('code')->all())->toBe(['lost']);

        app(SeedDefaultPipeline::class)->handle($this->org);
        app(SeedOrganizationCatalogs::class)->handle($this->org);

        expect(Pipeline::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->count())->toBe(1)
            ->and(Pipeline::query()->withoutGlobalScopes()->where('organization_id', $this->other->id)->count())->toBe(1);
    });
});

describe('CreateAdmissionCase', function () {
    it('opens a case in the first stage with history, first touch and pool ownership', function () {
        $case = ($this->create)();

        expect($case->uuid)->toHaveLength(36)->and($case->stage->code)->toBe('new')->and($case->status)->toBe(CaseStatus::Open)
            ->and($case->organization_id)->toBe($this->org->id)->and($case->owner_id)->toBeNull()->and($case->assigned_at)->toBeNull()
            ->and($case->last_activity_at)->not->toBeNull()->and($case->inPool())->toBeTrue()
            ->and($case->stageHistories)->toHaveCount(1)
            ->and($case->stageHistories->first())->from_stage_id->toBeNull()->to_stage_id->toBe($case->stage_id)->user_id->toBe($this->admin->id)
            ->and($case->caseTouches)->toHaveCount(1);
    });

    it('R5: one case per applicant per cycle, even a deleted one', function () {
        $first = ($this->create)();

        fails(fn () => ($this->create)(), 'applicant_id');

        $first->delete();
        fails(fn () => ($this->create)(), 'applicant_id');

        $next = AdmissionCycle::factory()->for($this->org)->create();
        expect(($this->create)(null, [], $next)->cycle_id)->toBe($next->id)
            ->and(AdmissionCase::withTrashed()->count())->toBe(2);
    });

    it('R5 also holds in the database', function () {
        ($this->create)();

        expect(fn () => AdmissionCase::factory()->create([
            'organization_id' => $this->org->id, 'applicant_id' => $this->applicant->id, 'cycle_id' => $this->cycle->id,
            'pipeline_id' => ($this->stage)('new')->pipeline_id, 'stage_id' => ($this->stage)('new')->id,
        ]))->toThrow(QueryException::class);
    });

    it('R8: refuses a closed cycle', function () {
        fails(fn () => ($this->create)(null, [], AdmissionCycle::factory()->for($this->org)->closed()->create()), 'cycle_id');
    });

    it('takes source and campaign from the applicant unless given', function () {
        $source = LeadSource::query()->where('code', 'website')->firstOrFail();
        $campaign = Campaign::factory()->for($this->org)->create(['cycle_id' => $this->cycle->id]);
        $applicant = Applicant::factory()->for($this->org)->create(['first_source_id' => $source->id, 'first_campaign_id' => $campaign->id]);
        $explicit = LeadSource::query()->where('code', 'hotline')->firstOrFail();

        $case = ($this->create)($applicant);
        expect($case)->source_id->toBe($source->id)->campaign_id->toBe($campaign->id)
            ->and($case->caseTouches->first())->source_id->toBe($source->id)->campaign_id->toBe($campaign->id);

        $other = ($this->create)(Applicant::factory()->for($this->org)->create(), ['source_id' => $explicit->id, 'utm_source' => 'fb', 'utm_campaign' => 'tuyen-sinh']);
        expect($other)->source_id->toBe($explicit->id)->and($other->caseTouches->first()->utm)->toBe(['source' => 'fb', 'campaign' => 'tuyen-sinh']);
    });

    it('gives the case to the owner of the territory: the school first, then the province', function () {
        $hanoi = Province::factory()->create();
        $hue = Province::factory()->create();
        $school = HighSchool::factory()->for($this->org)->create(['province_id' => $hanoi->id]);
        $team = Team::factory()->for($this->org)->create();
        $provinceOwner = User::factory()->for($this->org)->create();
        $schoolOwner = User::factory()->for($this->org)->create();

        $provinceTerritory = Territory::factory()->for($this->org)->create(['user_id' => $provinceOwner->id, 'team_id' => $team->id]);
        TerritoryAssignment::create(['territory_id' => $provinceTerritory->id, 'assignable_type' => 'province', 'assignable_id' => $hanoi->id]);
        $schoolTerritory = Territory::factory()->for($this->org)->create(['user_id' => $schoolOwner->id]);
        TerritoryAssignment::create(['territory_id' => $schoolTerritory->id, 'assignable_type' => 'high_school', 'assignable_id' => $school->id]);

        $bySchool = ($this->create)(Applicant::factory()->for($this->org)->create(['high_school_id' => $school->id, 'province_id' => $hanoi->id]));
        $byProvince = ($this->create)(Applicant::factory()->for($this->org)->create(['province_id' => $hanoi->id]));
        $nowhere = ($this->create)(Applicant::factory()->for($this->org)->create(['province_id' => $hue->id]));

        expect($bySchool->owner_id)->toBe($schoolOwner->id)->and($bySchool->assigned_at)->not->toBeNull()
            ->and($byProvince)->owner_id->toBe($provinceOwner->id)->team_id->toBe($team->id)
            ->and($nowhere->owner_id)->toBeNull();
    });

    it('honours an explicit owner, and an explicit null sends the case to the pool', function () {
        $province = Province::factory()->create();
        $head = User::factory()->for($this->org)->create();
        $territory = Territory::factory()->for($this->org)->create(['user_id' => $head->id]);
        TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'province', 'assignable_id' => $province->id]);
        $chosen = User::factory()->for($this->org)->create();
        Team::factory()->for($this->org)->create()->members()->attach($chosen);

        $explicit = ($this->create)(Applicant::factory()->for($this->org)->create(['province_id' => $province->id]), ['owner_id' => $chosen->id]);
        $pool = ($this->create)(Applicant::factory()->for($this->org)->create(['province_id' => $province->id]), ['owner_id' => null]);

        expect($explicit)->owner_id->toBe($chosen->id)->team_id->not->toBeNull()->and($pool->owner_id)->toBeNull();
    });

    it("uses the organization's own default pipeline", function () {
        $mine = ($this->create)();
        $theirs = Pipeline::query()->withoutGlobalScopes()->where('organization_id', $this->other->id)->firstOrFail();

        expect($mine->pipeline_id)->not->toBe($theirs->id)->and($mine->pipeline->organization_id)->toBe($this->org->id);

        $custom = Pipeline::factory()->for($this->org)->create(['level' => 'college', 'is_default' => true]);
        PipelineStage::factory()->for($custom)->create(['code' => 'start']);
        $college = AdmissionCycle::factory()->for($this->org)->create(['level' => 'college']);
        expect(($this->create)(Applicant::factory()->for($this->org)->create(), [], $college)->pipeline_id)->toBe($custom->id);
    });

    it('needs a default pipeline with an open stage', function () {
        Pipeline::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->get()->each(function (Pipeline $p) {
            $p->stages()->delete();
        });

        fails(fn () => ($this->create)(), 'pipeline_id');

        Pipeline::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->delete();
        fails(fn () => ($this->create)(Applicant::factory()->for($this->org)->create()), 'pipeline_id');
    });
});

describe('MoveCaseStage', function () {
    beforeEach(function () {
        $this->case = ($this->create)();
        $this->move = fn (string $code, array $data = [], ?AdmissionCase $case = null) => app(MoveCaseStage::class)->handle($case ?? $this->case, ($this->stage)($code), $this->admin, $data);
    });

    it('moves the case, writes the history and keeps the status in step (R7)', function () {
        $this->travel(5)->hours();
        $case = ($this->move)('counseling');

        expect($case->stage->code)->toBe('counseling')->and($case->status)->toBe(CaseStatus::Open);

        $history = $case->stageHistories()->get()->last();
        expect($history)->from_stage_id->toBe(($this->stage)('new')->id)->to_stage_id->toBe(($this->stage)('counseling')->id)->user_id->toBe($this->admin->id)
            ->and((float) $history->hours_in_previous)->toBeGreaterThan(4.9)->toBeLessThan(5.1)
            ->and($case->stageHistories)->toHaveCount(2);
    });

    it('R7: the status follows the stage type, also when the stage is changed directly', function () {
        $won = ($this->move)('enrolled');
        expect($won->status)->toBe(CaseStatus::Won);

        $lost = ($this->move)('lost', ['lost_reason_id' => LostReason::query()->first()->id]);
        expect($lost->status)->toBe(CaseStatus::Lost);

        $case = AdmissionCase::query()->findOrFail($this->case->id);
        $case->stage_id = ($this->stage)('counseling')->id;
        $case->save();
        expect($case->fresh()->status)->toBe(CaseStatus::Open);

        $case->stage_id = ($this->stage)('enrolled')->id;
        $case->save();
        expect($case->fresh()->status)->toBe(CaseStatus::Won);
    });

    it('R6: a lost case needs a lost reason of the organization', function () {
        fails(fn () => ($this->move)('lost'), 'lost_reason_id');
        fails(fn () => ($this->move)('lost', ['lost_reason_id' => 999999]), 'lost_reason_id');
        fails(fn () => ($this->move)('lost', ['lost_reason_id' => LostReason::query()->withoutGlobalScopes()->where('organization_id', $this->other->id)->value('id')]), 'lost_reason_id');
        expect($this->case->fresh()->stage->code)->toBe('new');

        $reason = LostReason::query()->first();
        $case = ($this->move)('lost', ['lost_reason_id' => $reason->id, 'lost_note' => 'Chọn trường khác']);

        expect($case)->lost_reason_id->toBe($reason->id)->lost_note->toBe('Chọn trường khác')->status->toBe(CaseStatus::Lost);
    });

    it('clears the lost reason when a lost case is reopened', function () {
        ($this->move)('lost', ['lost_reason_id' => LostReason::query()->first()->id, 'lost_note' => 'x']);
        $case = ($this->move)('contacted');

        expect($case)->status->toBe(CaseStatus::Open)->lost_reason_id->toBeNull()->lost_note->toBeNull();
    });

    it('stamps enrollment when a case is won and clears it when it leaves', function () {
        $major = Major::factory()->for($this->org)->create();
        $other = Major::factory()->for($this->org)->create();
        $this->case->update(['primary_major_id' => $major->id]);

        $case = ($this->move)('enrolled');
        expect($case)->enrolled_at->not->toBeNull()->enrolled_major_id->toBe($major->id);

        $case = ($this->move)('confirmed');
        expect($case)->enrolled_at->toBeNull()->enrolled_major_id->toBeNull();

        expect(($this->move)('enrolled', ['enrolled_major_id' => $other->id])->enrolled_major_id)->toBe($other->id);
    });

    it('requires the fields a stage asks for', function () {
        ($this->stage)('eligible')->update(['required_fields' => ['primary_major_id', 'temperature']]);

        fails(fn () => ($this->move)('eligible'), 'stage_id');

        $this->case->update(['primary_major_id' => Major::factory()->for($this->org)->create()->id]);
        fails(fn () => ($this->move)('eligible'), 'stage_id');

        $this->case->update(['temperature' => 'hot']);
        expect(($this->move)('eligible')->stage->code)->toBe('eligible');
    });

    it('rejects the same stage, a stage of another pipeline, and a closed cycle (R8)', function () {
        fails(fn () => ($this->move)('new'), 'stage_id');

        $foreign = PipelineStage::factory()->for(Pipeline::factory()->for($this->org)->create())->create();
        fails(fn () => app(MoveCaseStage::class)->handle($this->case, $foreign, $this->admin), 'stage_id');

        $this->cycle->forceFill(['status' => 'closed', 'is_current' => false])->save();
        fails(fn () => ($this->move)('counseling'), 'stage_id');
        expect($this->case->fresh()->stage->code)->toBe('new')->and($this->case->stageHistories()->count())->toBe(1);
    });
});

describe('AssignCase and ClaimCase', function () {
    beforeEach(function () {
        $this->case = ($this->create)();
        $this->owner = User::factory()->for($this->org)->create();
    });

    it('assigns a case to a user and team, and returns it to the pool', function () {
        $team = Team::factory()->for($this->org)->create();
        $team->members()->attach($this->owner);

        $case = app(AssignCase::class)->handle($this->case, $this->owner, null, $this->admin);
        expect($case)->owner_id->toBe($this->owner->id)->team_id->toBe($team->id)->assigned_at->not->toBeNull()
            ->and(DB::table('activity_log')->where('description', 'Đổi người phụ trách')->count())->toBe(1);

        $case = app(AssignCase::class)->handle($case, null, null, $this->admin);
        expect($case)->owner_id->toBeNull()->assigned_at->toBeNull()->team_id->toBe($team->id);
    });

    it('refuses an owner from another organization, an inactive owner, an unknown team and a closed cycle', function () {
        fails(fn () => app(AssignCase::class)->handle($this->case, User::factory()->for($this->other)->create()), 'owner_id');
        fails(fn () => app(AssignCase::class)->handle($this->case, User::factory()->for($this->org)->create(['is_active' => false])), 'owner_id');
        fails(fn () => app(AssignCase::class)->handle($this->case, $this->owner, 999999), 'team_id');

        $this->cycle->forceFill(['status' => 'closed', 'is_current' => false])->save();
        fails(fn () => app(AssignCase::class)->handle($this->case->fresh(), $this->owner), 'owner_id');
        expect($this->case->fresh()->owner_id)->toBeNull();
    });

    it('lets a user claim a pool case once', function () {
        $case = app(ClaimCase::class)->handle($this->case, $this->owner);

        expect($case)->owner_id->toBe($this->owner->id)->assigned_at->not->toBeNull()
            ->and(DB::table('activity_log')->where('description', 'Nhận hồ sơ từ kho chung')->count())->toBe(1);

        $second = User::factory()->for($this->org)->create();
        fails(fn () => app(ClaimCase::class)->handle($this->case, $second), 'case');
        expect($this->case->fresh()->owner_id)->toBe($this->owner->id);
    });

    it('cannot claim in a closed cycle', function () {
        $this->cycle->forceFill(['status' => 'closed', 'is_current' => false])->save();

        fails(fn () => app(ClaimCase::class)->handle($this->case->fresh(), $this->owner), 'case');
    });
});

describe('policy and data scope', function () {
    it('R8: a case of a closed cycle is read-only, assigning needs case.assign, claiming needs a pool case', function () {
        $case = ($this->create)();
        $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $counselor->givePermissionTo(['case.view', 'case.update']);

        expect($counselor->can('update', $case))->toBeTrue()->and($counselor->can('claim', $case))->toBeTrue()
            ->and($counselor->can('assign', $case))->toBeFalse()->and($this->admin->can('assign', $case))->toBeTrue();

        $case->update(['owner_id' => $counselor->id]);
        expect($counselor->can('claim', $case->fresh()))->toBeFalse();

        $this->cycle->forceFill(['status' => 'closed', 'is_current' => false])->save();
        $fresh = AdmissionCase::query()->withoutDataScope()->find($case->id);
        expect($counselor->can('update', $fresh))->toBeFalse()->and($this->admin->can('update', $fresh))->toBeFalse()->and($this->admin->can('assign', $fresh))->toBeFalse()
            ->and($counselor->can('view', $fresh))->toBeTrue();
    });

    it('shows a counselor their own cases and the pool of their team, not others', function () {
        $team = Team::factory()->for($this->org)->create();
        $otherTeam = Team::factory()->for($this->org)->create();
        $alice = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        $alice->assignRole('counselor');
        $team->members()->attach($alice);
        $bob = User::factory()->for($this->org)->create();

        $mine = ($this->create)(Applicant::factory()->for($this->org)->create(), ['owner_id' => $alice->id, 'team_id' => $team->id]);
        $teamPool = ($this->create)(Applicant::factory()->for($this->org)->create(), ['owner_id' => null, 'team_id' => $team->id]);
        ($this->create)(Applicant::factory()->for($this->org)->create(), ['owner_id' => $bob->id, 'team_id' => $team->id]);
        ($this->create)(Applicant::factory()->for($this->org)->create(), ['owner_id' => null, 'team_id' => $otherTeam->id]);

        $this->actingAs($alice);
        expect(AdmissionCase::query()->pluck('id')->sort()->values()->all())->toBe(collect([$mine->id, $teamPool->id])->sort()->values()->all());
    });

    it('applies the territory scope to cases through the applicant', function () {
        $hanoi = Province::factory()->create();
        $hue = Province::factory()->create();
        $lead = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Territory]);
        $territory = Territory::factory()->for($this->org)->create(['user_id' => $lead->id]);
        TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'province', 'assignable_id' => $hanoi->id]);

        $inside = ($this->create)(Applicant::factory()->for($this->org)->create(['province_id' => $hanoi->id]), ['owner_id' => $this->admin->id]);
        ($this->create)(Applicant::factory()->for($this->org)->create(['province_id' => $hue->id]), ['owner_id' => $this->admin->id]);

        $this->actingAs($lead);
        expect(AdmissionCase::query()->pluck('id')->all())->toBe([$inside->id]);
    });

    it('lets whoever owns a case see its applicant', function () {
        $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        $visible = Applicant::factory()->for($this->org)->create();
        Applicant::factory()->for($this->org)->create();
        ($this->create)($visible, ['owner_id' => $counselor->id]);

        $this->actingAs($counselor);
        expect(Applicant::query()->pluck('id')->all())->toBe([$visible->id]);
    });
});
