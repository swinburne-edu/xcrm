<?php

use App\Domain\Activity\Models\Activity;
use App\Domain\Activity\Models\Task;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\CaseTouch;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Intake\Models\Form;
use App\Domain\Intake\Models\ImportJob;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->org = Organization::factory()->create();
    $this->other = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    app(SeedOrganizationCatalogs::class)->handle($this->other);

    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();
    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');

    $this->form = Form::query()->create([
        'organization_id' => $this->org->id,
        'name' => 'Đăng ký tư vấn 2027',
        'fields' => [['key' => 'full_name', 'required' => true], ['key' => 'phone', 'required' => true], ['key' => 'email', 'required' => false], ['key' => 'note', 'required' => false]],
        'success_message' => 'Cảm ơn bạn!',
    ]);
    $this->lead = fn (array $data = []) => $this->postJson('/api/public/leads', ['form_uuid' => $this->form->uuid] + $data + ['full_name' => 'Nguyễn Văn An', 'phone' => '0912 345 678']);
});

describe('public lead endpoint (flow F3)', function () {
    it('creates the applicant, the case at the first stage, the first touch and the "gọi lần đầu" task', function () {
        ($this->lead)(['email' => 'An@Example.com', 'note' => 'Quan tâm CNTT', 'utm_source' => 'facebook', 'utm_campaign' => 'ts2027'])
            ->assertCreated()->assertJsonPath('ok', true)->assertJsonPath('message', 'Cảm ơn bạn!');

        $applicant = Applicant::query()->withoutGlobalScopes()->firstOrFail();
        $case = AdmissionCase::query()->withoutGlobalScopes()->firstOrFail();

        expect($applicant->phone_normalized)->toBe('84912345678')->and($applicant->email)->toBe('an@example.com')
            ->and($applicant->organization_id)->toBe($this->org->id)
            ->and($case->cycle_id)->toBe($this->cycle->id)->and($case->stage->code)->toBe('new')
            ->and($case->source_id)->toBe(LeadSource::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->where('code', 'website')->value('id'))
            ->and($case->utm_source)->toBe('facebook')->and($case->utm_campaign)->toBe('ts2027');

        $touch = CaseTouch::query()->where('case_id', $case->id)->sole();
        $task = Task::query()->withoutGlobalScopes()->where('subject_id', $case->id)->sole();

        expect($touch->form_id)->toBe($this->form->id)->and($touch->payload['note'])->toBe('Quan tâm CNTT')
            ->and($task->title)->toBe('Gọi lần đầu')->and($task->status->value)->toBe('todo')
            ->and($task->due_at->between(now()->addHours(23), now()->addHours(25)))->toBeTrue()
            ->and($case->refresh()->next_action_at)->not->toBeNull()
            ->and(Activity::query()->withoutGlobalScopes()->where('subject_type', 'admission_case')->where('subject_id', $case->id)->where('type', 'system')->exists())->toBeTrue();
    });

    it('does not create a second applicant for the same phone: adds a touch and fills the blanks', function () {
        ($this->lead)()->assertCreated();
        ($this->lead)(['phone' => '+84912345678', 'email' => 'an@example.com', 'note' => 'Hỏi thêm học bổng'])->assertCreated();

        $applicant = Applicant::query()->withoutGlobalScopes()->sole();
        $case = AdmissionCase::query()->withoutGlobalScopes()->sole();

        expect($applicant->email)->toBe('an@example.com')
            ->and(CaseTouch::query()->where('case_id', $case->id)->count())->toBe(2)
            ->and(Task::query()->withoutGlobalScopes()->where('subject_id', $case->id)->count())->toBe(1)
            ->and(Activity::query()->withoutGlobalScopes()->where('subject_id', $case->id)->where('title', 'like', '%đăng ký lại%')->exists())->toBeTrue();
    });

    it('does not overwrite what the applicant already has', function () {
        ($this->lead)(['email' => 'first@example.com'])->assertCreated();
        ($this->lead)(['full_name' => 'Tên khác', 'email' => 'second@example.com'])->assertCreated();

        $applicant = Applicant::query()->withoutGlobalScopes()->sole();

        expect($applicant->full_name)->toBe('Nguyễn Văn An')->and($applicant->email)->toBe('first@example.com');
    });

    it('also matches a duplicate by email when the phone differs', function () {
        ($this->lead)(['email' => 'same@example.com'])->assertCreated();
        ($this->lead)(['phone' => '0987 654 321', 'email' => 'same@example.com'])->assertCreated();

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(1);
    });

    it('drops honeypot submissions silently', function () {
        ($this->lead)(['hp' => 'http://spam.example'])->assertOk()->assertJsonPath('ok', true);

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('rejects unknown and inactive forms', function () {
        $this->postJson('/api/public/leads', ['form_uuid' => (string) Str::uuid(), 'full_name' => 'A', 'phone' => '0912345678'])->assertNotFound();

        $this->form->update(['is_active' => false]);
        ($this->lead)()->assertNotFound();
        $this->postJson('/api/public/leads', ['full_name' => 'A'])->assertUnprocessable()->assertJsonValidationErrors('form_uuid');
    });

    it('validates the name, the phone and the fields the form requires', function () {
        ($this->lead)(['full_name' => ''])->assertUnprocessable()->assertJsonValidationErrors('full_name');
        ($this->lead)(['phone' => '123'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        ($this->lead)(['email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->form->update(['fields' => [['key' => 'full_name', 'required' => true], ['key' => 'phone', 'required' => true], ['key' => 'email', 'required' => true]]]);
        ($this->lead)()->assertUnprocessable()->assertJsonValidationErrors('email');

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('ignores fields the form does not show', function () {
        ($this->lead)(['graduation_year' => 2027])->assertCreated();

        expect(Applicant::query()->withoutGlobalScopes()->sole()->graduation_year)->toBeNull();
    });

    it('keeps the applicant but opens no case when the organization has no open cycle', function () {
        $this->cycle->update(['status' => 'closed']);

        ($this->lead)()->assertCreated();

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(1)->and(AdmissionCase::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('limits how fast one form can be flooded', function () {
        foreach (range(1, 10) as $i) {
            ($this->lead)(['phone' => '09123456'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)])->assertCreated();
        }

        ($this->lead)(['phone' => '0912345699'])->assertStatus(429);
    });

    it('lands the lead in the organization of the form, never another one', function () {
        ($this->lead)()->assertCreated();

        expect(Applicant::query()->withoutGlobalScopes()->where('organization_id', $this->other->id)->count())->toBe(0);
    });
});

describe('excel import', function () {
    $csv = fn (string $body) => UploadedFile::fake()->createWithContent('thi-sinh.csv', "\xEF\xBB\xBF".$body);
    $sample = "Họ và tên,SĐT,Email,Giới tính,Ngày sinh,Năm tốt nghiệp\nTrần Thị Bình,0911 111 111,binh@example.com,Nữ,15/03/2009,2027\nLê Văn Cường,0922222222,,Nam,,2027\nSai Số,12,,,,\n,0933333333,,,,\nTrần Thị Bình,0911111111,,,,\n";

    it('suggests the column mapping from the headings and previews new / duplicate / error rows', function () use ($csv, $sample) {
        Applicant::factory()->for($this->org)->create(['full_name' => 'Lê Văn Cường', 'phone' => '0922222222']);

        $this->actingAs($this->admin)->post('/applicants/import/preview', ['file' => $csv($sample)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('mapping.full_name', 'Họ và tên')->assertJsonPath('mapping.phone', 'SĐT')->assertJsonPath('mapping.graduation_year', 'Năm tốt nghiệp')
            ->assertJsonPath('preview.total', 5)->assertJsonPath('preview.new', 1)->assertJsonPath('preview.duplicate', 2)->assertJsonPath('preview.error', 2)
            ->assertJsonPath('preview.rows.2.status', 'error');

        expect(Applicant::query()->withoutGlobalScopes()->count())->toBe(1);
    });

    it('imports in the background: new applicants get a case and a task, duplicates a touch, bad rows are reported', function () use ($csv, $sample) {
        $mapping = ['full_name' => 'Họ và tên', 'phone' => 'SĐT', 'email' => 'Email', 'gender' => 'Giới tính', 'dob' => 'Ngày sinh', 'graduation_year' => 'Năm tốt nghiệp'];

        $this->actingAs($this->admin)->post('/applicants/import', ['file' => $csv($sample), 'mapping' => json_encode($mapping)], ['Accept' => 'application/json'])->assertStatus(202);

        $job = ImportJob::query()->sole();

        expect($job->status)->toBe('done')->and($job->total_rows)->toBe(5)->and($job->success_rows)->toBe(2)->and($job->duplicate_rows)->toBe(1)->and($job->error_rows)->toBe(2)
            ->and($job->errors)->toHaveCount(2)->and($job->errors[0]['row'])->toBe(4);

        $binh = Applicant::query()->withoutGlobalScopes()->where('full_name', 'Trần Thị Bình')->sole();

        expect($binh->gender->value)->toBe('female')->and($binh->dob->toDateString())->toBe('2009-03-15')->and($binh->graduation_year)->toBe(2027)
            ->and(AdmissionCase::query()->withoutGlobalScopes()->count())->toBe(2)
            ->and(Task::query()->withoutGlobalScopes()->where('title', 'Gọi lần đầu')->count())->toBe(2)
            ->and(CaseTouch::query()->count())->toBe(3)
            ->and(AdmissionCase::query()->withoutGlobalScopes()->where('applicant_id', $binh->id)->first()->source->code)->toBe('import');

        $this->getJson("/imports/{$job->id}")->assertOk()->assertJsonPath('import.status', 'done')->assertJsonPath('import.success', 2);
    });

    it('needs the name and a phone or email mapped', function () use ($csv, $sample) {
        $this->actingAs($this->admin)->post('/applicants/import', ['file' => $csv($sample), 'mapping' => json_encode(['full_name' => 'Họ và tên'])], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('mapping');

        expect(ImportJob::query()->count())->toBe(0);
    });

    it('rejects an empty sheet', function () use ($csv) {
        $this->actingAs($this->admin)->post('/applicants/import/preview', ['file' => $csv("Họ và tên,SĐT\n")], ['Accept' => 'application/json'])->assertUnprocessable();
    });

    it('is only for users who may import applicants and stays inside the organization', function () use ($csv, $sample) {
        $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        $counselor->assignRole('counselor');

        $this->actingAs($counselor)->post('/applicants/import/preview', ['file' => $csv($sample)], ['Accept' => 'application/json'])->assertForbidden();

        $job = ImportJob::query()->withoutGlobalScopes()->create(['organization_id' => $this->other->id, 'object' => 'applicants', 'file_path' => 'x']);
        $this->actingAs($this->admin)->getJson("/imports/{$job->id}")->assertNotFound();
    });
});

describe('forms screen', function () {
    it('lists forms with their embed code', function () {
        $this->actingAs($this->admin)->get('/forms')->assertInertia(fn (Assert $page) => $page->component('Forms/Index')->has('forms.data', 1)
            ->where('forms.data.0.uuid', $this->form->uuid)->where('forms.data.0.embed', fn ($html) => str_contains($html, $this->form->uuid) && str_contains($html, '/api/public/leads') && str_contains($html, 'name="hp"'))
            ->where('can.manage', true));
    });

    it('creates, changes and deletes a form; full name and phone are always asked', function () {
        $this->actingAs($this->admin);

        $this->post('/forms', ['name' => 'Ngày hội', 'fields' => [['key' => 'email', 'required' => true], ['key' => 'major']], 'is_active' => true])->assertRedirect();

        $form = Form::query()->where('name', 'Ngày hội')->firstOrFail();

        expect(collect($form->fields)->pluck('key')->all())->toBe(['full_name', 'phone', 'email', 'major'])->and($form->fields[2]['required'])->toBeTrue()->and($form->uuid)->toHaveLength(36);

        $this->put("/forms/{$form->id}", ['name' => 'Ngày hội 2', 'fields' => [], 'is_active' => false])->assertRedirect();

        expect($form->refresh()->name)->toBe('Ngày hội 2')->and($form->is_active)->toBeFalse()->and($form->fields)->toHaveCount(2);

        $this->delete("/forms/{$form->id}")->assertRedirect();

        expect(Form::query()->whereKey($form->id)->exists())->toBeFalse();
    });

    it('is managed only with form.manage and the campaign / source must belong to the organization', function () {
        $counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
        $counselor->assignRole('counselor');
        $this->actingAs($counselor)->post('/forms', ['name' => 'x'])->assertForbidden();

        $foreign = LeadSource::query()->withoutGlobalScopes()->where('organization_id', $this->other->id)->value('id');
        $this->actingAs($this->admin)->postJson('/forms', ['name' => 'x', 'source_id' => $foreign])->assertUnprocessable()->assertJsonValidationErrors('source_id');
    });

    it('serves the menu entry instead of the placeholder', function () {
        $this->actingAs($this->admin)->get('/forms')->assertInertia(fn (Assert $page) => $page->component('Forms/Index'));
    });
});
