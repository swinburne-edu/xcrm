<?php

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantCertificate;
use App\Domain\Applicant\Models\ApplicantScore;
use App\Domain\Applicant\Models\Guardian;
use App\Domain\Applicant\Services\ApplicantDeduplicator;
use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Catalog\Models\Ward;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Models\TerritoryAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->hanoi = Province::factory()->create(['code' => '01', 'name' => 'Hà Nội']);
    $this->hue = Province::factory()->create(['code' => '46', 'name' => 'Huế']);
});

function applicantPayload(array $overrides = []): array
{
    return ['full_name' => 'Nguyễn Văn An', 'phone' => '0912345678', 'email' => 'An@Example.com', 'gender' => 'male', 'graduation_year' => 2027, 'current_grade' => 12, ...$overrides];
}

function ownApplicant(array $attrs = []): Applicant
{
    return Applicant::factory()->for(test()->org)->create($attrs);
}

/** A user who may view / edit applicants but not see sensitive data. */
function limitedUser(array $extra = []): User
{
    $user = User::factory()->for(test()->org)->create(['data_scope' => DataScopeLevel::All]);
    $user->givePermissionTo(['applicant.view', 'applicant.create', 'applicant.update', ...$extra]);

    return $user;
}

describe('list, scope and masking', function () {
    it('lists the applicants of the own organization, newest first, with filters and search', function () {
        $school = HighSchool::factory()->for($this->org)->create();
        $a = ownApplicant(['full_name' => 'Trần Thị Bình', 'phone' => '0987654321', 'email' => 'binh@x.vn', 'province_id' => $this->hanoi->id, 'high_school_id' => $school->id, 'graduation_year' => 2026]);
        ownApplicant(['full_name' => 'Lê Văn Cường', 'province_id' => $this->hue->id]);
        Applicant::factory()->for($this->other)->create();

        $this->actingAs($this->admin)->get('/applicants')
            ->assertInertia(fn (Assert $page) => $page->component('Applicants/Index')->has('applicants.data', 2));

        foreach (["filter[province_id]={$this->hanoi->id}", "filter[high_school_id]={$school->id}", 'filter[graduation_year]=2026', 'filter[q]=Bình', 'filter[q]=0987654', 'filter[q]=84987654321', 'filter[q]=binh@x'] as $q) {
            $this->actingAs($this->admin)->get("/applicants?{$q}")
                ->assertInertia(fn (Assert $page) => $page->has('applicants.data', 1)->where('applicants.data.0.uuid', $a->uuid));
        }
    });

    it('masks phone and citizen id for users without applicant.view_sensitive, and audits full views', function () {
        $applicant = ownApplicant(['phone' => '0912345678', 'citizen_id' => '012345678901']);
        $limited = limitedUser();

        $this->actingAs($limited)->get('/applicants')->assertInertia(fn (Assert $page) => $page->where('applicants.data.0.phone', '09******78'));
        $this->actingAs($limited)->get("/applicants/{$applicant->uuid}")
            ->assertInertia(fn (Assert $page) => $page->where('applicant.phone', '09******78')->where('applicant.citizen_id', '*********901')->where('applicant.sensitive_masked', true));
        expect(DB::table('activity_log')->where('description', 'Xem số CCCD')->count())->toBe(0);

        $this->actingAs($this->admin)->get("/applicants/{$applicant->uuid}")
            ->assertInertia(fn (Assert $page) => $page->where('applicant.phone', '0912345678')->where('applicant.citizen_id', '012345678901')->where('applicant.sensitive_masked', false));
        expect(DB::table('activity_log')->where('description', 'Xem số CCCD')->count())->toBe(1);
    });

    it('shows a counselor only the applicants they created or referred (data scope: own)', function () {
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');
        $created = Applicant::factory()->for($this->org)->create(['created_by' => $counselor->id]);
        $referred = ownApplicant(['referred_by_user_id' => $counselor->id]);
        $notMine = ownApplicant();

        $this->actingAs($counselor)->get('/applicants')->assertInertia(fn (Assert $page) => $page->has('applicants.data', 2));
        $this->actingAs($counselor)->get("/applicants/{$notMine->uuid}")->assertNotFound();
        $this->actingAs($counselor)->get("/applicants/{$created->uuid}")->assertOk();
        $this->actingAs($counselor)->get("/applicants/{$referred->uuid}")->assertOk();
    });

    it('applies the territory scope through provinces and schools', function () {
        $user = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Territory]);
        $user->givePermissionTo('applicant.view');
        $school = HighSchool::factory()->for($this->org)->create(['province_id' => $this->hue->id]);
        ownApplicant(['province_id' => $this->hanoi->id]);
        ownApplicant(['province_id' => $this->hue->id, 'high_school_id' => $school->id]);
        ownApplicant(['province_id' => $this->hue->id]);

        $territory = Territory::factory()->for($this->org)->create(['user_id' => $user->id]);
        TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'province', 'assignable_id' => $this->hanoi->id]);
        TerritoryAssignment::create(['territory_id' => $territory->id, 'assignable_type' => 'high_school', 'assignable_id' => $school->id]);

        $this->actingAs($user)->get('/applicants')->assertInertia(fn (Assert $page) => $page->has('applicants.data', 2));
    });

    it('is limited by applicant permissions, uses uuids and hides other organizations', function () {
        $applicant = ownApplicant();
        $foreign = Applicant::factory()->for($this->other)->create();
        $nobody = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('applicant.view');

        $this->actingAs($nobody)->get('/applicants')->assertForbidden();
        $this->actingAs($viewer)->get('/applicants')->assertInertia(fn (Assert $page) => $page->where('can.create', false));
        $this->actingAs($viewer)->post('/applicants', applicantPayload())->assertForbidden();
        $this->actingAs($viewer)->put("/applicants/{$applicant->uuid}", applicantPayload())->assertForbidden();
        $this->actingAs($viewer)->delete("/applicants/{$applicant->uuid}")->assertForbidden();
        $this->actingAs($this->admin)->get("/applicants/{$foreign->uuid}")->assertNotFound();
        $this->actingAs($this->admin)->get("/applicants/{$applicant->id}")->assertNotFound();
    });

    it('appears in the menu and in the applicants lookup', function () {
        ownApplicant(['full_name' => 'Phạm Minh Đức']);
        Applicant::factory()->for($this->other)->create(['full_name' => 'Phạm Minh Khác']);

        $this->actingAs($this->admin)->get('/applicants')
            ->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($nav) => str_contains(json_encode($nav, JSON_UNESCAPED_SLASHES), '/applicants')));
        $this->actingAs($this->admin)->getJson('/lookups/applicants?q=Phạm')->assertJsonCount(1, 'data');
    });
});

describe('create and update', function () {
    it('creates an applicant with a generated code and uuid and normalized derived columns', function () {
        $response = $this->actingAs($this->admin)->post('/applicants', applicantPayload(['citizen_id' => '012345678901', 'consent_marketing' => true]));

        $applicant = Applicant::query()->firstOrFail();
        $response->assertRedirect("/applicants/{$applicant->uuid}");
        expect($applicant->code)->toBe('TS000001')->and($applicant->uuid)->toHaveLength(36)
            ->and($applicant->phone_normalized)->toBe('84912345678')->and($applicant->email)->toBe('an@example.com')
            ->and($applicant->citizen_id)->toBe('012345678901')->and($applicant->citizen_id_hash)->toBe(hash('sha256', '012345678901'))
            ->and($applicant->consent_marketing)->toBeTrue()->and($applicant->consent_at)->not->toBeNull()
            ->and($applicant->organization_id)->toBe($this->org->id)->and($applicant->created_by)->toBe($this->admin->id)
            ->and(DB::table('applicants')->value('citizen_id'))->not->toContain('012345678901');
    });

    it('numbers codes per organization and never reuses a deleted one', function () {
        Applicant::factory()->for($this->other)->create();
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => '0911111111', 'email' => 'a@x.vn']));
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => '0922222222', 'email' => 'b@x.vn']));
        Applicant::query()->where('code', 'TS000002')->firstOrFail()->delete();
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => '0933333333', 'email' => 'c@x.vn']));

        expect(Applicant::query()->orderBy('id')->pluck('code')->all())->toBe(['TS000001', 'TS000003'])
            ->and(Applicant::withoutGlobalScopes()->where('organization_id', $this->other->id)->value('code'))->toBe('TS000001');
    });

    it('validates the input', function () {
        $foreignSchool = HighSchool::factory()->for($this->other)->create();
        $wardElsewhere = Ward::factory()->create(['province_id' => $this->hue->id]);

        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['full_name' => '']))->assertSessionHasErrors('full_name');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => '123']))->assertSessionHasErrors('phone');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['email' => 'khong-hop-le']))->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['citizen_id' => '12345']))->assertSessionHasErrors('citizen_id');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['dob' => '2999-01-01']))->assertSessionHasErrors('dob');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['current_grade' => 9]))->assertSessionHasErrors('current_grade');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['gender' => 'x']))->assertSessionHasErrors('gender');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['high_school_id' => $foreignSchool->id]))->assertSessionHasErrors('high_school_id');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['province_id' => $this->hanoi->id, 'ward_id' => $wardElsewhere->id]))->assertSessionHasErrors('ward_id');
        expect(Applicant::query()->count())->toBe(0);
    });

    it("defaults the priority area to the school's and keeps an explicit one", function () {
        $area = PriorityArea::factory()->create();
        $explicit = PriorityArea::factory()->create();
        $school = HighSchool::factory()->for($this->org)->create(['priority_area_id' => $area->id]);

        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['high_school_id' => $school->id]));
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => '0900000001', 'email' => 'x@x.vn', 'high_school_id' => $school->id, 'priority_area_id' => $explicit->id]));

        expect(Applicant::query()->orderBy('id')->pluck('priority_area_id')->all())->toBe([$area->id, $explicit->id]);
    });

    it('stamps consent_at once and clears it when consent is withdrawn', function () {
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['consent_marketing' => true]));
        $applicant = Applicant::query()->firstOrFail();
        $stamp = $applicant->consent_at;

        $this->travel(2)->days();
        $this->actingAs($this->admin)->put("/applicants/{$applicant->uuid}", applicantPayload(['consent_marketing' => true, 'full_name' => 'Đổi tên']));
        expect($applicant->fresh()->consent_at->equalTo($stamp))->toBeTrue();

        $this->actingAs($this->admin)->put("/applicants/{$applicant->uuid}", applicantPayload(['consent_marketing' => false]));
        expect($applicant->fresh())->consent_marketing->toBeFalse()->consent_at->toBeNull();
    });

    it('keeps a masked phone and citizen id when a limited user leaves them blank', function () {
        $applicant = ownApplicant(['phone' => '0912345678', 'citizen_id' => '012345678901']);
        $limited = limitedUser();

        $this->actingAs($limited)->put("/applicants/{$applicant->uuid}", applicantPayload(['full_name' => 'Tên mới', 'phone' => null, 'citizen_id' => null, 'email' => 'moi@x.vn']))->assertSessionHasNoErrors();
        expect($applicant->fresh())->full_name->toBe('Tên mới')->phone->toBe('0912345678')->citizen_id->toBe('012345678901');

        $this->actingAs($limited)->put("/applicants/{$applicant->uuid}", applicantPayload(['phone' => '0988888888', 'email' => 'moi@x.vn']));
        expect($applicant->fresh())->phone->toBe('0988888888')->phone_normalized->toBe('84988888888');

        $this->actingAs($this->admin)->put("/applicants/{$applicant->uuid}", applicantPayload(['phone' => null, 'email' => 'moi@x.vn']));
        expect($applicant->fresh()->phone)->toBeNull();
    });

    it('soft-deletes and logs it (rule R9)', function () {
        $applicant = ownApplicant();

        $this->actingAs($this->admin)->delete("/applicants/{$applicant->uuid}")->assertRedirect('/applicants');

        expect(Applicant::find($applicant->id))->toBeNull()->and(Applicant::withTrashed()->find($applicant->id))->not->toBeNull()
            ->and(DB::table('activity_log')->where('description', 'Xoá thí sinh')->count())->toBe(1);
    });
});

describe('duplicate detection', function () {
    it('rejects the same phone in any format, the same e-mail in any case, and the same citizen id', function () {
        ownApplicant(['full_name' => 'Đã Có', 'phone' => '0912345678', 'email' => 'da.co@x.vn', 'citizen_id' => '012345678901']);

        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => '+84 912 345 678', 'email' => 'moi@x.vn']))->assertSessionHasErrors('phone');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => '0900000009', 'email' => 'DA.CO@X.VN']))->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => '0900000009', 'email' => 'moi@x.vn', 'citizen_id' => '012345678901']))->assertSessionHasErrors('citizen_id');
        expect(Applicant::query()->count())->toBe(1);

        $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => '0900000009', 'email' => 'moi@x.vn']))->assertSessionDoesntHaveErrors();
    });

    it('names the existing applicant only when the user may see it', function () {
        $existing = ownApplicant(['full_name' => 'Người Đã Có', 'phone' => '0912345678']);
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');

        $this->actingAs($this->admin)->post('/applicants', applicantPayload())->assertSessionHasErrors(['phone' => "Số điện thoại đã thuộc về thí sinh {$existing->code} – Người Đã Có."]);
        $response = $this->actingAs($counselor)->post('/applicants', applicantPayload());
        $response->assertSessionHasErrors('phone');
        expect(session('errors')->first('phone'))->toContain('ngoài phạm vi')->not->toContain('Người Đã Có')->not->toContain($existing->code);
    });

    it('does not count the applicant itself, other organizations, merged or deleted applicants', function () {
        $self = ownApplicant(['phone' => '0912345678']);
        Applicant::factory()->for($this->other)->create(['phone' => '0900000001']);
        $merged = ownApplicant(['phone' => '0900000002']);
        $merged->forceFill(['merged_into_id' => $self->id])->save();
        ownApplicant(['phone' => '0900000003'])->delete();

        $this->actingAs($this->admin)->put("/applicants/{$self->uuid}", applicantPayload(['phone' => '0912345678']))->assertSessionDoesntHaveErrors();
        foreach (['0900000001', '0900000002', '0900000003'] as $i => $phone) {
            $this->actingAs($this->admin)->post('/applicants', applicantPayload(['phone' => $phone, 'email' => "u{$i}@x.vn"]))->assertSessionDoesntHaveErrors();
        }
    });

    it('finds duplicates through the service, phone first', function () {
        $byPhone = ownApplicant(['phone' => '0912345678']);
        $byEmail = ownApplicant(['email' => 'x@y.vn']);

        $service = app(ApplicantDeduplicator::class);
        expect($service->find(['phone' => '84912345678', 'email' => 'x@y.vn'])->is($byPhone))->toBeTrue()
            ->and($service->find(['email' => 'X@Y.vn'])->is($byEmail))->toBeTrue()
            ->and($service->find(['phone' => '0912345678'], $byPhone->id))->toBeNull()
            ->and($service->find([]))->toBeNull()
            ->and(array_column($service->matches(['phone' => '0912345678', 'email' => 'x@y.vn']), 'field'))->toBe(['phone', 'email']);
    });

    it('warns in realtime through check-duplicate, without leaking hidden applicants', function () {
        $visible = ownApplicant(['full_name' => 'Nhìn Thấy', 'phone' => '0912345678']);
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');
        $mine = Applicant::factory()->for($this->org)->create(['created_by' => $counselor->id, 'phone' => '0933333333']);

        $this->actingAs($this->admin)->getJson('/applicants/check-duplicate?phone=0912345678')
            ->assertJsonPath('matches.0.field', 'phone')->assertJsonPath('matches.0.visible', true)->assertJsonPath('matches.0.uuid', $visible->uuid)->assertJsonPath('matches.0.full_name', 'Nhìn Thấy');
        $this->actingAs($counselor)->getJson('/applicants/check-duplicate?phone=0912345678')
            ->assertJsonPath('matches.0.visible', false)->assertJsonPath('matches.0.uuid', null)->assertJsonPath('matches.0.full_name', null);
        $this->actingAs($counselor)->getJson('/applicants/check-duplicate?phone=0933333333')->assertJsonPath('matches.0.uuid', $mine->uuid);
        $this->actingAs($this->admin)->getJson("/applicants/check-duplicate?phone=0912345678&except={$visible->uuid}")->assertJsonCount(0, 'matches');
        $this->actingAs($this->admin)->getJson('/applicants/check-duplicate')->assertJsonCount(0, 'matches');
    });
});

describe('guardians', function () {
    beforeEach(function () {
        $this->applicant = ownApplicant();
        $this->guardianUrl = "/applicants/{$this->applicant->uuid}/guardians";
    });

    function guardianPayload(array $overrides = []): array
    {
        return ['full_name' => 'Nguyễn Văn Bố', 'relationship' => 'father', 'phone' => '0911222333', 'is_decision_maker' => true, ...$overrides];
    }

    it('adds, updates and unlinks a guardian', function () {
        $this->actingAs($this->admin)->post($this->guardianUrl, guardianPayload())->assertSessionHasNoErrors();
        $guardian = Guardian::query()->firstOrFail();
        $pivot = $this->applicant->guardians()->first()->pivot;
        expect($guardian->phone_normalized)->toBe('84911222333')->and($pivot->relationship)->toBe('father')->and((bool) $pivot->is_decision_maker)->toBeTrue();

        $this->actingAs($this->admin)->put("{$this->guardianUrl}/{$guardian->id}", guardianPayload(['full_name' => 'Đổi tên', 'relationship' => 'guardian']));
        expect($guardian->fresh()->full_name)->toBe('Đổi tên')->and($this->applicant->guardians()->first()->pivot->relationship)->toBe('guardian');

        $this->actingAs($this->admin)->delete("{$this->guardianUrl}/{$guardian->id}");
        expect($this->applicant->guardians()->count())->toBe(0)->and(Guardian::query()->count())->toBe(0);
    });

    it('links an existing guardian with the same phone to a sibling instead of duplicating', function () {
        $sibling = ownApplicant();
        $this->actingAs($this->admin)->post($this->guardianUrl, guardianPayload());
        $this->actingAs($this->admin)->post("/applicants/{$sibling->uuid}/guardians", guardianPayload(['relationship' => 'mother']));

        expect(Guardian::query()->count())->toBe(1)->and(Guardian::query()->first()->applicants()->count())->toBe(2);

        // unlinking from one applicant keeps the guardian for the other
        $this->actingAs($this->admin)->delete("{$this->guardianUrl}/".Guardian::query()->first()->id);
        expect(Guardian::query()->count())->toBe(1);
    });

    it('keeps one primary contact per applicant', function () {
        $this->actingAs($this->admin)->post($this->guardianUrl, guardianPayload(['full_name' => 'Một', 'phone' => '0911000001', 'is_primary_contact' => true]));
        $this->actingAs($this->admin)->post($this->guardianUrl, guardianPayload(['full_name' => 'Hai', 'phone' => '0911000002', 'is_primary_contact' => true]));

        $primary = $this->applicant->guardians()->wherePivot('is_primary_contact', true)->pluck('full_name')->all();
        expect($primary)->toBe(['Hai']);
    });

    it('validates and is protected by applicant.update and the data scope', function () {
        $this->actingAs($this->admin)->post($this->guardianUrl, guardianPayload(['full_name' => '']))->assertSessionHasErrors('full_name');
        $this->actingAs($this->admin)->post($this->guardianUrl, guardianPayload(['relationship' => 'cousin']))->assertSessionHasErrors('relationship');
        $this->actingAs($this->admin)->post($this->guardianUrl, guardianPayload(['phone' => '12']))->assertSessionHasErrors('phone');

        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('applicant.view');
        $this->actingAs($viewer)->post($this->guardianUrl, guardianPayload())->assertForbidden();

        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');
        $this->actingAs($counselor)->post($this->guardianUrl, guardianPayload())->assertNotFound();
        expect(Guardian::query()->count())->toBe(0);
    });

    it('masks guardian phones for users without view_sensitive', function () {
        $this->actingAs($this->admin)->post($this->guardianUrl, guardianPayload());

        $this->actingAs(limitedUser())->get("/applicants/{$this->applicant->uuid}")->assertInertia(fn (Assert $page) => $page->where('guardians.0.phone', '09******33'));
        $this->actingAs($this->admin)->get("/applicants/{$this->applicant->uuid}")->assertInertia(fn (Assert $page) => $page->where('guardians.0.phone', '0911222333')->where('guardians.0.relationship_label', 'Bố'));
    });
});

describe('scores', function () {
    beforeEach(function () {
        $this->applicant = ownApplicant();
        $this->math = Subject::factory()->math()->create();
        $this->lit = Subject::factory()->literature()->create();
        $this->url = "/applicants/{$this->applicant->uuid}/scores";
    });

    function cell(array $overrides): array
    {
        return ['score_type' => 'transcript', 'max_score' => 10, ...$overrides];
    }

    it('saves transcript cells, THPT exam scores and named exam totals', function () {
        $this->actingAs($this->admin)->put($this->url, ['scores' => [
            cell(['subject_id' => $this->math->id, 'term' => '11-1', 'score' => 8.5]),
            cell(['subject_id' => $this->math->id, 'term' => '11-2', 'score' => 9]),
            cell(['subject_id' => $this->lit->id, 'term' => '11-1', 'score' => 7.25]),
            cell(['score_type' => 'thpt_exam', 'subject_id' => $this->math->id, 'year' => 2027, 'score' => 9.4]),
            cell(['score_type' => 'competency_exam', 'exam_name' => 'HSA', 'max_score' => 150, 'year' => 2027, 'score' => 112.5]),
        ]])->assertSessionHasNoErrors();

        expect(ApplicantScore::query()->count())->toBe(5)
            ->and((float) ApplicantScore::query()->where('subject_id', $this->lit->id)->value('score'))->toBe(7.25)
            ->and(ApplicantScore::query()->where('score_type', 'competency_exam')->first())->exam_name->toBe('HSA')->subject_id->toBeNull();

        $this->actingAs($this->admin)->get("/applicants/{$this->applicant->uuid}")
            ->assertInertia(fn (Assert $page) => $page->has('scores', 5)->has('subjects', 2)->where('subjects.0.is_core', true));
    });

    it('updates a cell, deletes it when emptied, and never duplicates a cell', function () {
        $row = cell(['subject_id' => $this->math->id, 'term' => '12-1', 'score' => 7]);
        $this->actingAs($this->admin)->put($this->url, ['scores' => [$row]]);
        $this->actingAs($this->admin)->put($this->url, ['scores' => [[...$row, 'score' => 8]]]);

        expect(ApplicantScore::query()->count())->toBe(1)->and((float) ApplicantScore::query()->value('score'))->toBe(8.0);

        $this->actingAs($this->admin)->put($this->url, ['scores' => [[...$row, 'score' => '']]]);
        expect(ApplicantScore::query()->count())->toBe(0);
    });

    it('clears the verified flag when a score changes but not when it is unchanged', function () {
        $row = cell(['subject_id' => $this->math->id, 'term' => '12-1', 'score' => 7]);
        $this->actingAs($this->admin)->put($this->url, ['scores' => [$row]]);
        ApplicantScore::query()->update(['verified' => true, 'verified_by' => $this->admin->id]);

        $this->actingAs($this->admin)->put($this->url, ['scores' => [$row]]);
        expect(ApplicantScore::query()->first()->verified)->toBeTrue();

        $this->actingAs($this->admin)->put($this->url, ['scores' => [[...$row, 'score' => 7.5]]]);
        expect(ApplicantScore::query()->first())->verified->toBeFalse()->verified_by->toBeNull();
    });

    it('validates the cells', function () {
        $put = fn (array $cell) => $this->actingAs($this->admin)->put($this->url, ['scores' => [$cell]]);

        $put(cell(['subject_id' => $this->math->id, 'term' => '11-1', 'score' => 11]))->assertSessionHasErrors('scores.0.score');
        $put(cell(['subject_id' => $this->math->id, 'term' => '11-1', 'score' => -1]))->assertSessionHasErrors('scores.0.score');
        $put(cell(['subject_id' => $this->math->id, 'term' => '99-9', 'score' => 5]))->assertSessionHasErrors('scores.0.term');
        $put(cell(['term' => '11-1', 'score' => 5]))->assertSessionHasErrors('scores.0.subject_id');
        $put(cell(['subject_id' => $this->math->id, 'score' => 5]))->assertSessionHasErrors('scores.0.term');
        $put(cell(['score_type' => 'thpt_exam', 'subject_id' => $this->math->id, 'score' => 5]))->assertSessionHasErrors('scores.0.year');
        $put(cell(['score_type' => 'competency_exam', 'score' => 100, 'max_score' => 150]))->assertSessionHasErrors('scores.0.exam_name');
        $put(cell(['score_type' => 'bogus', 'score' => 5]))->assertSessionHasErrors('scores.0.score_type');
        $this->actingAs($this->admin)->put($this->url, ['scores' => []])->assertSessionHasErrors('scores');
        expect(ApplicantScore::query()->count())->toBe(0);
    });

    it('needs applicant.update and the data scope', function () {
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('applicant.view');
        $counselor = User::factory()->for($this->org)->create();
        $counselor->assignRole('counselor');
        $payload = ['scores' => [cell(['subject_id' => $this->math->id, 'term' => '11-1', 'score' => 8])]];

        $this->actingAs($viewer)->put($this->url, $payload)->assertForbidden();
        $this->actingAs($counselor)->put($this->url, $payload)->assertNotFound();
        expect(ApplicantScore::query()->count())->toBe(0);
    });
});

describe('certificates', function () {
    beforeEach(function () {
        $this->applicant = ownApplicant();
        $this->url = "/applicants/{$this->applicant->uuid}/certificates";
    });

    it('adds, updates and removes a certificate', function () {
        $this->actingAs($this->admin)->post($this->url, ['type' => 'IELTS', 'score' => '6.5', 'issued_at' => '2026-05-01', 'expires_at' => '2028-05-01'])->assertSessionHasNoErrors();
        $certificate = ApplicantCertificate::query()->firstOrFail();
        expect($certificate)->score->toBe('6.5')->and($certificate->issued_at->toDateString())->toBe('2026-05-01');

        $certificate->update(['verified' => true]);
        $this->actingAs($this->admin)->put("{$this->url}/{$certificate->id}", ['type' => 'IELTS', 'score' => '7.0']);
        expect($certificate->fresh())->score->toBe('7.0')->verified->toBeFalse();

        $this->actingAs($this->admin)->get("/applicants/{$this->applicant->uuid}")
            ->assertInertia(fn (Assert $page) => $page->where('certificates.0.type_label', 'IELTS')->where('certificates.0.score', '7.0'));

        $this->actingAs($this->admin)->delete("{$this->url}/{$certificate->id}");
        expect(ApplicantCertificate::query()->count())->toBe(0);
    });

    it("validates the certificate and does not touch another applicant's", function () {
        $this->actingAs($this->admin)->post($this->url, ['type' => 'XYZ'])->assertSessionHasErrors('type');
        $this->actingAs($this->admin)->post($this->url, ['type' => 'SAT', 'issued_at' => '2026-05-01', 'expires_at' => '2025-01-01'])->assertSessionHasErrors('expires_at');

        $other = ApplicantCertificate::query()->create(['applicant_id' => ownApplicant()->id, 'type' => 'TOEIC', 'score' => '800']);
        $this->actingAs($this->admin)->delete("{$this->url}/{$other->id}")->assertNotFound();
        expect(ApplicantCertificate::query()->count())->toBe(1);
    });

    it('needs applicant.update', function () {
        $viewer = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
        $viewer->givePermissionTo('applicant.view');

        $this->actingAs($viewer)->post($this->url, ['type' => 'IELTS'])->assertForbidden();
    });
});
