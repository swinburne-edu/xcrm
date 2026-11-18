<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Activity\Models\Appointment;
use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Application;
use App\Domain\Admission\Models\CaseTouch;
use App\Domain\Admission\Models\PipelineStage;
use App\Domain\Applicant\Actions\MergeApplicants;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantCertificate;
use App\Domain\Applicant\Models\ApplicantDocument;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Domain\Applicant\Models\Guardian;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Models\AdmissionRound;
use App\Domain\Cycle\Models\CycleMethod;
use App\Domain\Major\Models\Major;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);

    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->school = HighSchool::factory()->for($this->org)->create();
    $this->person = fn (array $data = []) => Applicant::factory()->for($this->org)->create($data);
    $this->openCase = fn (Applicant $a, ?AdmissionCycle $cycle = null) => app(CreateAdmissionCase::class)->handle($a, $cycle ?? $this->cycle, ['owner_id' => $this->admin->id], $this->admin);
    $this->stage = fn (string $code) => PipelineStage::query()->whereHas('pipeline', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', $this->org->id))->where('code', $code)->firstOrFail();
});

describe('MergeApplicants', function () {
    it('moves guardians, certificates, scores, timeline and tasks to the survivor and soft-deletes the duplicate', function () {
        $keep = ($this->person)(['full_name' => 'Nguyễn An']);
        $dup = ($this->person)(['full_name' => 'Nguyen An']);
        $subject = Subject::factory()->create();

        $shared = Guardian::factory()->for($this->org)->create();
        $own = Guardian::factory()->for($this->org)->create();
        $keep->guardians()->attach($shared->id, ['relationship' => 'father']);
        $dup->guardians()->attach($shared->id, ['relationship' => 'father']);
        $dup->guardians()->attach($own->id, ['relationship' => 'mother', 'is_primary_contact' => true]);

        ApplicantScore::query()->create(['applicant_id' => $keep->id, 'score_type' => 'transcript', 'subject_id' => $subject->id, 'term' => 'g12_s1', 'score' => 8]);
        ApplicantScore::query()->create(['applicant_id' => $dup->id, 'score_type' => 'transcript', 'subject_id' => $subject->id, 'term' => 'g12_s1', 'score' => 5]);
        ApplicantScore::query()->create(['applicant_id' => $dup->id, 'score_type' => 'transcript', 'subject_id' => $subject->id, 'term' => 'g12_s2', 'score' => 9]);
        ApplicantCertificate::query()->create(['applicant_id' => $dup->id, 'type' => 'IELTS', 'score' => '6.5']);
        Activity::factory()->create(['organization_id' => $this->org->id, 'subject_type' => 'applicant', 'subject_id' => $dup->id, 'type' => 'note', 'body' => 'Ghi chú cũ']);
        Task::factory()->create(['organization_id' => $this->org->id, 'subject_type' => 'applicant', 'subject_id' => $dup->id, 'assignee_id' => $this->admin->id]);

        app(MergeApplicants::class)->handle($keep, $dup, [], $this->admin);

        expect($keep->guardians()->count())->toBe(2)->and($dup->guardians()->count())->toBe(0)
            ->and(ApplicantScore::query()->where('applicant_id', $keep->id)->count())->toBe(2)
            ->and((float) ApplicantScore::query()->where('applicant_id', $keep->id)->where('term', 'g12_s1')->value('score'))->toBe(8.0)
            ->and(ApplicantCertificate::query()->where('applicant_id', $keep->id)->count())->toBe(1)
            ->and(Activity::query()->where('subject_type', 'applicant')->where('subject_id', $keep->id)->where('body', 'Ghi chú cũ')->exists())->toBeTrue()
            ->and(Task::query()->withoutGlobalScopes()->where('subject_type', 'applicant')->where('subject_id', $keep->id)->count())->toBe(1);

        $dup = Applicant::query()->withTrashed()->find($dup->id);

        expect($dup->merged_into_id)->toBe($keep->id)->and($dup->trashed())->toBeTrue()
            ->and(Activity::query()->where('subject_type', 'applicant')->where('subject_id', $keep->id)->where('title', 'like', 'Gộp thí sinh trùng%')->exists())->toBeTrue();
    });

    it('takes the chosen field values and fills blanks from the duplicate', function () {
        $keep = ($this->person)(['full_name' => 'Trần Bình', 'email' => 'keep@example.com', 'phone' => '0911111111', 'zalo_id' => null, 'address' => null]);
        $dup = ($this->person)(['full_name' => 'Trần Văn Bình', 'email' => 'dup@example.com', 'phone' => '0922222222', 'zalo_id' => 'zalo-dup', 'address' => '12 Lê Lợi']);

        app(MergeApplicants::class)->handle($keep, $dup, ['full_name' => 'merge', 'phone' => 'merge'], $this->admin);

        $keep->refresh();

        expect($keep->full_name)->toBe('Trần Văn Bình')->and($keep->phone_normalized)->toBe('84922222222')
            ->and($keep->email)->toBe('keep@example.com')->and($keep->zalo_id)->toBe('zalo-dup')->and($keep->address)->toBe('12 Lê Lợi');
    });

    it('hands over a case in a cycle where only the duplicate has one', function () {
        $keep = ($this->person)();
        $dup = ($this->person)();
        $case = ($this->openCase)($dup);

        app(MergeApplicants::class)->handle($keep, $dup, [], $this->admin);

        expect($case->refresh()->applicant_id)->toBe($keep->id);
    });

    it('joins two cases of the same cycle: the further stage wins, everything moves to the survivor\'s case', function () {
        $keep = ($this->person)();
        $dup = ($this->person)();
        $keepCase = ($this->openCase)($keep);
        $dupCase = ($this->openCase)($dup);
        AdmissionCase::query()->whereKey($dupCase->id)->update(['stage_id' => ($this->stage)('counseling')->id, 'first_contacted_at' => now()->subDays(3)]);
        Activity::factory()->create(['organization_id' => $this->org->id, 'subject_type' => 'admission_case', 'subject_id' => $dupCase->id, 'type' => 'note', 'body' => 'Đã tư vấn']);
        Task::factory()->create(['organization_id' => $this->org->id, 'subject_type' => 'admission_case', 'subject_id' => $dupCase->id, 'assignee_id' => $this->admin->id, 'due_at' => now()->addDay()]);
        Appointment::factory()->create(['organization_id' => $this->org->id, 'case_id' => $dupCase->id]);

        app(MergeApplicants::class)->handle($keep, $dup, [], $this->admin);

        $keepCase->refresh()->load('stage');

        expect($keepCase->stage->code)->toBe('counseling')->and($keepCase->first_contacted_at)->not->toBeNull()
            ->and($keepCase->next_action_at)->not->toBeNull()
            ->and(AdmissionCase::query()->withoutGlobalScopes()->where('applicant_id', $keep->id)->where('cycle_id', $this->cycle->id)->count())->toBe(1)
            ->and(AdmissionCase::query()->withoutGlobalScopes()->onlyTrashed()->whereKey($dupCase->id)->exists())->toBeTrue()
            ->and(Activity::query()->where('subject_type', 'admission_case')->where('subject_id', $keepCase->id)->where('body', 'Đã tư vấn')->exists())->toBeTrue()
            ->and(Task::query()->withoutGlobalScopes()->where('subject_id', $keepCase->id)->count())->toBe(1)
            ->and(Appointment::query()->withoutGlobalScopes()->where('case_id', $keepCase->id)->count())->toBe(1)
            ->and(CaseTouch::query()->where('case_id', $keepCase->id)->count())->toBe(2);
    });

    it('keeps the survivor\'s case when it is the one further along', function () {
        $keep = ($this->person)();
        $dup = ($this->person)();
        $keepCase = ($this->openCase)($keep);
        ($this->openCase)($dup);
        AdmissionCase::query()->whereKey($keepCase->id)->update(['stage_id' => ($this->stage)('counseling')->id]);

        app(MergeApplicants::class)->handle($keep, $dup, [], $this->admin);

        expect($keepCase->refresh()->stage->code)->toBe('counseling');
    });

    it('moves nguyện vọng and documents with the case, renumbering an order that clashes in the round (R3)', function () {
        $keep = ($this->person)();
        $dup = ($this->person)();
        $keepCase = ($this->openCase)($keep);
        $dupCase = ($this->openCase)($dup);
        $round = AdmissionRound::factory()->create(['cycle_id' => $this->cycle->id]);
        $method = CycleMethod::factory()->create(['cycle_id' => $this->cycle->id]);
        $make = fn (AdmissionCase $case, int $order) => Application::factory()->create([
            'organization_id' => $this->org->id, 'case_id' => $case->id, 'major_id' => Major::factory()->for($this->org)->create()->id,
            'cycle_method_id' => $method->id, 'round_id' => $round->id, 'preference_order' => $order,
        ]);
        $make($keepCase, 1);
        $theirs = $make($dupCase, 1);
        $free = $make($dupCase, 4);
        $document = ApplicantDocument::factory()->create(['organization_id' => $this->org->id, 'applicant_id' => $dup->id, 'case_id' => $dupCase->id]);

        app(MergeApplicants::class)->handle($keep, $dup, [], $this->admin);

        expect(Application::query()->where('case_id', $keepCase->id)->orderBy('preference_order')->pluck('preference_order')->all())->toBe([1, 2, 4])
            ->and($theirs->refresh()->case_id)->toBe($keepCase->id)->and($theirs->preference_order)->toBe(2)
            ->and($free->refresh()->preference_order)->toBe(4)
            ->and($document->refresh()->applicant_id)->toBe($keep->id)->and($document->case_id)->toBe($keepCase->id);
    });

    it('refuses to join cases that together exceed 15 nguyện vọng in a round', function () {
        $keep = ($this->person)();
        $dup = ($this->person)();
        $keepCase = ($this->openCase)($keep);
        $dupCase = ($this->openCase)($dup);
        $round = AdmissionRound::factory()->create(['cycle_id' => $this->cycle->id]);
        $method = CycleMethod::factory()->create(['cycle_id' => $this->cycle->id]);

        foreach (range(1, 8) as $order) {
            foreach ([$keepCase, $dupCase] as $case) {
                $major = Major::factory()->for($this->org)->create();
                Application::factory()->create(['organization_id' => $this->org->id, 'case_id' => $case->id, 'major_id' => $major->id, 'cycle_method_id' => $method->id, 'round_id' => $round->id, 'preference_order' => $order]);
            }
        }

        expect(fn () => app(MergeApplicants::class)->handle($keep, $dup, [], $this->admin))->toThrow(ValidationException::class);
        expect(Applicant::query()->whereKey($dup->id)->exists())->toBeTrue()->and(Application::query()->where('case_id', $dupCase->id)->count())->toBe(8);
    });

    it('refuses to merge with itself, across organizations or an already merged record', function () {
        $a = ($this->person)();
        $b = ($this->person)();
        $foreign = Applicant::factory()->for($this->other)->create();

        expect(fn () => app(MergeApplicants::class)->handle($a, $a, [], $this->admin))->toThrow(ValidationException::class)
            ->and(fn () => app(MergeApplicants::class)->handle($a, $foreign, [], $this->admin))->toThrow(ValidationException::class);

        app(MergeApplicants::class)->handle($a, $b, [], $this->admin);

        expect(fn () => app(MergeApplicants::class)->handle($a, $b->refresh(), [], $this->admin))->toThrow(ValidationException::class);
    });
});

describe('duplicates screen', function () {
    it('lists same name + same school and shared guardian phones, ignoring case and accents', function () {
        ($this->person)(['full_name' => 'Lê Hoàng', 'high_school_id' => $this->school->id]);
        ($this->person)(['full_name' => 'LE  HOANG', 'high_school_id' => $this->school->id]);
        ($this->person)(['full_name' => 'Lê Hoàng', 'high_school_id' => HighSchool::factory()->for($this->org)->create()->id]);

        $x = ($this->person)();
        $y = ($this->person)();
        $x->guardians()->attach(Guardian::factory()->for($this->org)->create(['phone' => '0988777666'])->id);
        $y->guardians()->attach(Guardian::factory()->for($this->org)->create(['phone' => '+84 988 777 666'])->id);

        $this->actingAs($this->admin)->get('/applicants/duplicates')->assertInertia(fn (Assert $page) => $page->component('Applicants/Duplicates')
            ->has('groups', 2)->where('groups.0.reason', 'same_school')->has('groups.0.applicants', 2)->where('groups.1.reason', 'guardian')->has('groups.1.applicants', 2));
    });

    it('does not list merged applicants or other organizations', function () {
        $a = ($this->person)(['full_name' => 'Phạm Dũng', 'high_school_id' => $this->school->id]);
        $b = ($this->person)(['full_name' => 'Phạm Dũng', 'high_school_id' => $this->school->id]);
        Applicant::factory()->for($this->other)->create(['full_name' => 'Phạm Dũng', 'high_school_id' => $this->school->id]);

        app(MergeApplicants::class)->handle($a, $b, [], $this->admin);

        $this->actingAs($this->admin)->get('/applicants/duplicates')->assertInertia(fn (Assert $page) => $page->has('groups', 0));
    });
});

describe('merge screen and endpoint', function () {
    it('compares two records field by field', function () {
        $a = ($this->person)(['full_name' => 'Võ Em', 'email' => 'a@example.com']);
        $b = ($this->person)(['full_name' => 'Võ Em', 'email' => 'b@example.com']);

        $this->actingAs($this->admin)->get("/applicants/merge/{$a->uuid}/{$b->uuid}")->assertInertia(fn (Assert $page) => $page->component('Applicants/Merge')
            ->where('keep.uuid', $a->uuid)->where('merge.uuid', $b->uuid)
            ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'email')['differs'] === true && collect($fields)->firstWhere('key', 'full_name')['differs'] === false));
    });

    it('merges through POST and goes to the survivor', function () {
        $a = ($this->person)(['full_name' => 'Đỗ Phúc', 'email' => 'a@example.com']);
        $b = ($this->person)(['full_name' => 'Đỗ Phúc B', 'email' => 'b@example.com']);

        $this->actingAs($this->admin)->post('/applicants/merge', ['keep' => $a->uuid, 'merge' => $b->uuid, 'choices' => ['email' => 'merge']])
            ->assertRedirect("/applicants/{$a->uuid}");

        expect($a->refresh()->email)->toBe('b@example.com')->and(Applicant::query()->whereKey($b->id)->exists())->toBeFalse();
    });

    it('validates the request and needs applicant.merge', function () {
        $a = ($this->person)();

        $this->actingAs($this->admin)->postJson('/applicants/merge', ['keep' => $a->uuid, 'merge' => $a->uuid])->assertUnprocessable()->assertJsonValidationErrors('keep');
        $this->postJson('/applicants/merge', ['keep' => $a->uuid, 'merge' => (string) Str::uuid(), 'choices' => ['email' => 'zzz']])->assertUnprocessable();

        $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        $counselor->assignRole('counselor');
        $b = ($this->person)();

        $this->actingAs($counselor)->get('/applicants/duplicates')->assertForbidden();
        $this->post('/applicants/merge', ['keep' => $a->uuid, 'merge' => $b->uuid])->assertForbidden();
    });

    it('cannot merge records of another organization', function () {
        $a = ($this->person)();
        $foreign = Applicant::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->post('/applicants/merge', ['keep' => $a->uuid, 'merge' => $foreign->uuid])->assertNotFound();
        $this->get("/applicants/merge/{$a->uuid}/{$foreign->uuid}")->assertNotFound();
    });
});
