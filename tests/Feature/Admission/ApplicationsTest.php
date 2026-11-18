<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Models\MajorQuota;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed([RoleSeeder::class, CatalogSeeder::class]);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create(['status' => 'active']);

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');
    $this->clerk = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->clerk->assignRole('admission_clerk');

    $this->a00 = SubjectCombination::query()->where('code', 'A00')->firstOrFail();
    $this->method = CycleMethod::factory()->create(['cycle_id' => $this->cycle->id, 'score_formula' => ['source' => 'thpt_exam', 'scale_to' => 30, 'min_total' => 18]]);
    $this->major = Major::factory()->for($this->org)->create(['name' => 'Công nghệ thông tin']);
    DB::table('major_cycle_methods')->insert(['major_id' => $this->major->id, 'cycle_method_id' => $this->method->id]);
    DB::table('major_combinations')->insert(['major_id' => $this->major->id, 'combination_id' => $this->a00->id, 'cycle_id' => $this->cycle->id]);
    $this->round = AdmissionRound::factory()->create(['cycle_id' => $this->cycle->id, 'name' => 'Đợt 1']);

    /** A case owned by $owner; $scores = Toán, Lý, Hoá of the THPT exam (null = no scores). */
    $this->caseFor = function (User $owner, ?array $scores = [8, 8, 8], ?AdmissionCycle $cycle = null): AdmissionCase {
        $applicant = Applicant::factory()->for($this->org)->create();
        $case = app(CreateAdmissionCase::class)->handle($applicant, $cycle ?? $this->cycle, ['owner_id' => $owner->id], $this->admin);

        foreach (($scores === null ? [] : ['TOAN', 'LY', 'HOA']) as $i => $code) {
            ApplicantScore::query()->create(['applicant_id' => $applicant->id, 'score_type' => 'thpt_exam', 'subject_id' => Subject::query()->where('code', $code)->value('id'), 'score' => $scores[$i], 'year' => 2026]);
        }

        return $case;
    };

    $this->payload = fn (array $extra = []) => $extra + [
        'major_id' => $this->major->id,
        'cycle_method_id' => $this->method->id,
        'combination_id' => $this->a00->id,
        'round_id' => $this->round->id,
    ];

    $this->add = fn (User $by, AdmissionCase $case, array $extra = []) => $this->actingAs($by)->post("/cases/{$case->uuid}/applications", ($this->payload)($extra));
});

describe('adding a nguyện vọng', function () {
    it('creates a scored draft, numbers it and sets the case major', function () {
        $case = ($this->caseFor)($this->counselor, [8, 7, 9]);

        ($this->add)($this->counselor, $case)->assertSessionHasNoErrors();

        $application = Application::query()->firstOrFail();
        expect($application->status->value)->toBe('draft')->and($application->preference_order)->toBe(1)
            ->and((float) $application->raw_score)->toBe(24.0)->and((float) $application->final_score)->toBe(24.0)
            ->and($application->score_breakdown['combination'])->toBe('A00')->and($case->refresh()->primary_major_id)->toBe($this->major->id);
    });

    it('takes the next free order in the round and keeps the one asked for', function () {
        $case = ($this->caseFor)($this->counselor);
        $other = Major::factory()->for($this->org)->create();
        DB::table('major_cycle_methods')->insert(['major_id' => $other->id, 'cycle_method_id' => $this->method->id]);
        DB::table('major_combinations')->insert(['major_id' => $other->id, 'combination_id' => $this->a00->id, 'cycle_id' => $this->cycle->id]);

        ($this->add)($this->counselor, $case);
        ($this->add)($this->counselor, $case, ['major_id' => $other->id]);
        ($this->add)($this->counselor, $case, ['major_id' => $other->id, 'cycle_method_id' => $this->method->id, 'preference_order' => 5, 'combination_id' => null])->assertSessionHasErrors('combination_id');

        expect(Application::query()->orderBy('id')->pluck('preference_order')->all())->toBe([1, 2]);
    });

    it('R4: the combination must belong to the major in this cycle', function () {
        $case = ($this->caseFor)($this->counselor);
        $d01 = SubjectCombination::query()->where('code', 'D01')->firstOrFail();

        ($this->add)($this->counselor, $case, ['combination_id' => $d01->id])->assertSessionHasErrors('combination_id');
        expect(Application::query()->count())->toBe(0);
    });

    it('R4: the method must belong to the major and to the cycle of the case', function () {
        $case = ($this->caseFor)($this->counselor);
        $unlinked = CycleMethod::factory()->create(['cycle_id' => $this->cycle->id, 'score_formula' => ['source' => 'thpt_exam']]);
        $foreign = CycleMethod::factory()->create(['score_formula' => ['source' => 'thpt_exam']]); // another cycle

        ($this->add)($this->counselor, $case, ['cycle_method_id' => $unlinked->id])->assertSessionHasErrors('cycle_method_id');
        ($this->add)($this->counselor, $case, ['cycle_method_id' => $foreign->id])->assertSessionHasErrors('cycle_method_id');
        expect(Application::query()->count())->toBe(0);
    });

    it('the round must belong to the cycle of the case', function () {
        $case = ($this->caseFor)($this->counselor);
        $foreign = AdmissionRound::factory()->create();

        ($this->add)($this->counselor, $case, ['round_id' => $foreign->id])->assertSessionHasErrors('round_id');
    });

    it('needs a combination for a method scored by subjects', function () {
        $case = ($this->caseFor)($this->counselor);

        ($this->add)($this->counselor, $case, ['combination_id' => null])->assertSessionHasErrors('combination_id');
    });

    it('R3: refuses an order already used in the round, an order out of 1–15 and a 16th nguyện vọng', function () {
        $case = ($this->caseFor)($this->counselor);
        ($this->add)($this->counselor, $case, ['preference_order' => 3]);

        $other = Major::factory()->for($this->org)->create();
        DB::table('major_cycle_methods')->insert(['major_id' => $other->id, 'cycle_method_id' => $this->method->id]);
        DB::table('major_combinations')->insert(['major_id' => $other->id, 'combination_id' => $this->a00->id, 'cycle_id' => $this->cycle->id]);

        ($this->add)($this->counselor, $case, ['major_id' => $other->id, 'preference_order' => 3])->assertSessionHasErrors('preference_order');
        ($this->add)($this->counselor, $case, ['major_id' => $other->id, 'preference_order' => 16])->assertSessionHasErrors('preference_order');

        foreach (range(1, 14) as $i) {
            Application::factory()->create(['organization_id' => $this->org->id, 'case_id' => $case->id, 'major_id' => $this->major->id, 'cycle_method_id' => $this->method->id, 'round_id' => AdmissionRound::factory()->create(['cycle_id' => $this->cycle->id])->id, 'preference_order' => $i]);
        }

        expect(Application::query()->where('case_id', $case->id)->count())->toBe(15);
        ($this->add)($this->counselor, $case, ['major_id' => $other->id])->assertSessionHasErrors('preference_order');
        expect(Application::query()->where('case_id', $case->id)->count())->toBe(15);
    });

    it('refuses the same major, method and combination twice in a round', function () {
        $case = ($this->caseFor)($this->counselor);
        ($this->add)($this->counselor, $case);

        ($this->add)($this->counselor, $case)->assertSessionHasErrors('major_id');
        expect(Application::query()->count())->toBe(1);
    });

    it('keeps the score incomplete, with the missing subjects, when scores are not entered yet', function () {
        $case = ($this->caseFor)($this->counselor, null);

        ($this->add)($this->counselor, $case)->assertSessionHasNoErrors();

        $application = Application::query()->firstOrFail();
        expect($application->final_score)->toBeNull()->and($application->score_breakdown['missing'])->toBe(['TOAN', 'LY', 'HOA']);
    });

    it('only for cases the user may update, and not in a closed cycle (R8)', function () {
        $mine = ($this->caseFor)($this->counselor);
        $theirs = ($this->caseFor)($this->admin);

        ($this->add)($this->counselor, $theirs)->assertNotFound();

        $this->cycle->update(['status' => 'closed']);
        ($this->add)($this->counselor, $mine)->assertForbidden();
        expect(Application::query()->count())->toBe(0);
    });

    it('needs application.create', function () {
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->assignRole('faculty_viewer');
        $case = ($this->caseFor)($this->counselor);

        ($this->add)($viewer, $case)->assertForbidden();
    });
});

describe('editing and deleting', function () {
    it('edits a draft and re-scores it; refuses to edit once submitted', function () {
        $case = ($this->caseFor)($this->counselor, [6, 6, 6]);
        ($this->add)($this->counselor, $case);
        $application = Application::query()->firstOrFail();

        $this->actingAs($this->counselor)->put("/applications/{$application->id}", ($this->payload)(['preference_order' => 4]))->assertSessionHasNoErrors();
        expect($application->refresh()->preference_order)->toBe(4);

        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'submit'])->assertSessionHasNoErrors();
        $this->actingAs($this->counselor)->put("/applications/{$application->id}", ($this->payload)(['preference_order' => 2]))->assertSessionHasErrors('major_id');
        expect($application->refresh()->preference_order)->toBe(4);
    });

    it('R9: only a draft is deleted; a submitted one is withdrawn', function () {
        $case = ($this->caseFor)($this->counselor);
        ($this->add)($this->counselor, $case);
        $application = Application::query()->firstOrFail();
        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'submit']);

        $this->actingAs($this->counselor)->delete("/applications/{$application->id}")->assertForbidden();
        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'withdraw'])->assertSessionHasNoErrors();
        expect($application->refresh()->status->value)->toBe('withdrawn');

        ($this->add)($this->counselor, $case, ['preference_order' => 2]);
        $draft = Application::query()->where('preference_order', 2)->firstOrFail();
        $this->actingAs($this->counselor)->delete("/applications/{$draft->id}")->assertSessionHasNoErrors();
        expect(Application::query()->whereKey($draft->id)->exists())->toBeFalse();
    });
});

describe('life of a nguyện vọng', function () {
    it('cannot be submitted without a complete score', function () {
        $case = ($this->caseFor)($this->counselor, null);
        ($this->add)($this->counselor, $case);
        $application = Application::query()->firstOrFail();

        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'submit'])->assertSessionHasErrors('action');
        expect($application->refresh()->status->value)->toBe('draft');
    });

    it('submit → eligible → admitted → confirmed pulls the case forward and logs the timeline', function () {
        $case = ($this->caseFor)($this->counselor, [8, 8, 8]);
        ($this->add)($this->counselor, $case);
        $application = Application::query()->firstOrFail();
        $stage = fn () => $case->refresh()->stage->code;

        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'submit'])->assertSessionHasNoErrors();
        expect($application->refresh()->status->value)->toBe('submitted')->and($application->submitted_at)->not->toBeNull()->and($stage())->toBe('early_applied')
            ->and($case->refresh()->our_preference_rank)->toBe(1);

        $this->actingAs($this->clerk)->post("/applications/{$application->id}/transition", ['action' => 'eligible'])->assertSessionHasNoErrors();
        expect($stage())->toBe('eligible')->and($application->refresh()->decided_at)->not->toBeNull();

        $this->actingAs($this->clerk)->post("/applications/{$application->id}/transition", ['action' => 'admit'])->assertSessionHasNoErrors();
        expect($stage())->toBe('admitted');

        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'confirm'])->assertSessionHasNoErrors();
        expect($stage())->toBe('confirmed')->and($application->refresh()->confirmed_at)->not->toBeNull();

        expect(DB::table('activities')->where('subject_type', 'applicant')->where('subject_id', $case->applicant_id)->where('title', 'like', 'Nguyện vọng 1%')->count())->toBe(4);
    });

    it('never moves a case backwards or out of a won stage', function () {
        $case = ($this->caseFor)($this->counselor, [8, 8, 8]);
        $won = $case->pipeline->stages()->where('code', 'enrolled')->firstOrFail();
        AdmissionCase::query()->whereKey($case->id)->update(['stage_id' => $won->id, 'status' => 'won']);
        ($this->add)($this->counselor, $case);
        $application = Application::query()->firstOrFail();

        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'submit'])->assertSessionHasNoErrors();

        expect($case->refresh()->stage->code)->toBe('enrolled');
    });

    it('only the registrar decides; wrong order of steps is refused', function () {
        $case = ($this->caseFor)($this->counselor, [8, 8, 8]);
        ($this->add)($this->counselor, $case);
        $application = Application::query()->firstOrFail();

        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'eligible'])->assertForbidden();
        $this->actingAs($this->clerk)->post("/applications/{$application->id}/transition", ['action' => 'eligible'])->assertSessionHasErrors('action'); // still a draft

        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'submit']);
        $this->actingAs($this->counselor)->post("/applications/{$application->id}/transition", ['action' => 'confirm'])->assertSessionHasErrors('action'); // not admitted yet
        $this->actingAs($this->clerk)->post("/applications/{$application->id}/transition", ['action' => 'nonsense'])->assertSessionHasErrors('action');
    });

    it('is frozen in a closed cycle', function () {
        $case = ($this->caseFor)($this->counselor, [8, 8, 8]);
        ($this->add)($this->counselor, $case);
        $application = Application::query()->firstOrFail();
        $this->cycle->update(['status' => 'closed']);

        $this->actingAs($this->clerk)->post("/applications/{$application->id}/transition", ['action' => 'submit'])->assertForbidden();
        $this->actingAs($this->clerk)->post("/applications/{$application->id}/score", ['encourage_bonus' => 1])->assertForbidden();
        expect($application->refresh()->status->value)->toBe('draft');
    });

    it('lets the registrar enter and re-score the encouragement bonus, capped by the formula', function () {
        $this->method->update(['score_formula' => ['source' => 'thpt_exam', 'scale_to' => 30, 'encourage_bonus_max' => 3]]);
        $case = ($this->caseFor)($this->counselor, [7, 7, 7]);
        ($this->add)($this->counselor, $case);
        $application = Application::query()->firstOrFail();

        $this->actingAs($this->counselor)->post("/applications/{$application->id}/score", ['encourage_bonus' => 2])->assertForbidden();
        $this->actingAs($this->clerk)->post("/applications/{$application->id}/score", ['encourage_bonus' => 5])->assertSessionHasNoErrors();

        expect((float) $application->refresh()->encourage_bonus)->toBe(3.0)->and((float) $application->final_score)->toBe(24.0);
    });

    it('re-scores when the applicant scores change', function () {
        $case = ($this->caseFor)($this->counselor, [5, 5, 5]);
        ($this->add)($this->counselor, $case);
        $application = Application::query()->firstOrFail();
        ApplicantScore::query()->where('applicant_id', $case->applicant_id)->update(['score' => 9]);

        $this->actingAs($this->clerk)->post("/applications/{$application->id}/score", [])->assertSessionHasNoErrors();

        expect((float) $application->refresh()->final_score)->toBe(27.0);
    });
});

describe('screens', function () {
    it('lists the admission ranking of a major by score, against its quota, without drafts', function () {
        MajorQuota::query()->create(['organization_id' => $this->org->id, 'major_id' => $this->major->id, 'cycle_id' => $this->cycle->id, 'quota_total' => 50, 'quota_direct' => 5]);
        foreach ([[9, 9, 9], [6, 6, 6], [8, 8, 8]] as $scores) {
            $case = ($this->caseFor)($this->admin, $scores);
            ($this->add)($this->admin, $case);
            $this->actingAs($this->admin)->post('/applications/'.Application::query()->latest('id')->value('id').'/transition', ['action' => 'submit']);
        }
        ($this->add)($this->admin, ($this->caseFor)($this->admin, [10, 10, 10])); // a draft: not listed

        $this->actingAs($this->admin)->get("/applications?filter[major_id]={$this->major->id}")->assertInertia(fn (Assert $page) => $page->component('Applications/Index')
            ->where('applications.data', fn ($rows) => collect($rows)->pluck('final_score')->all() == [27, 24, 18] && collect($rows)->pluck('rank')->all() == [1, 2, 3])
            ->where('quota.total', 50)->where('quota.by_score', 45)->where('quota.admitted', 0)->where('can.review', true));
    });

    it('shows a counselor only the nguyện vọng of their own cases', function () {
        foreach ([$this->counselor, $this->admin] as $owner) {
            $case = ($this->caseFor)($owner, [8, 8, 8]);
            ($this->add)($owner, $case);
            $this->actingAs($owner)->post('/applications/'.Application::query()->latest('id')->value('id').'/transition', ['action' => 'submit']);
        }

        $this->actingAs($this->counselor)->get('/applications')->assertInertia(fn (Assert $page) => $page->has('applications.data', 1));
    });

    it('needs application.view for the list', function () {
        $user = User::factory()->for($this->org)->create();
        $this->actingAs($user)->get('/applications')->assertForbidden();
    });

    it('gives the applicant page its nguyện vọng, the add-form choices and the allowed actions', function () {
        $case = ($this->caseFor)($this->counselor, [8, 8, 8]);
        ($this->add)($this->counselor, $case);

        $this->actingAs($this->counselor)->get("/applicants/{$case->applicant->uuid}")->assertInertia(fn (Assert $page) => $page->component('Applicants/Show')
            ->where('application_panel.case_uuid', $case->uuid)->where('application_panel.can_create', true)
            ->has('application_panel.items', 1)->where('application_panel.items.0.major', 'Công nghệ thông tin')
            ->where('application_panel.items.0.actions.0.action', 'submit')->where('application_panel.items.0.can.update', true)
            ->where('application_panel.options.majors.0.combinations.0.code', 'A00')->has('application_panel.options.rounds', 1));
    });
});
