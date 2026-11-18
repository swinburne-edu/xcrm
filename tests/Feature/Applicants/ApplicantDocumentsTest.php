<?php

use App\Domain\Admission\Actions\CreateAdmissionCase;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantDocument;
use App\Domain\Catalog\Actions\SeedOrganizationCatalogs;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Organization\Enums\DataScopeLevel;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleSeeder::class);
    $this->org = Organization::factory()->create();
    app(SeedOrganizationCatalogs::class)->handle($this->org);
    $this->cycle = AdmissionCycle::factory()->for($this->org)->current()->create();

    $this->admin = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->admin->assignRole('admin');
    $this->counselor = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $this->counselor->assignRole('counselor');
    $this->clerk = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::All]);
    $this->clerk->assignRole('admission_clerk');

    $this->applicant = Applicant::factory()->for($this->org)->create();
    $this->case = app(CreateAdmissionCase::class)->handle($this->applicant, $this->cycle, ['owner_id' => $this->counselor->id], $this->admin);

    $this->upload = fn (User $by, array $extra = []) => $this->actingAs($by)->post("/applicants/{$this->applicant->uuid}/documents", $extra + [
        'doc_type' => 'transcript',
        'file' => UploadedFile::fake()->createWithContent('hoc-ba.pdf', "%PDF-1.4
1 0 obj
<< /Type /Catalog >>
endobj
trailer
<< /Root 1 0 R >>
%%EOF"),
    ]);
});

it('stores an upload on the private disk, pending review, and logs it on the timeline', function () {
    ($this->upload)($this->counselor, ['case_uuid' => $this->case->uuid])->assertSessionHasNoErrors();

    $document = ApplicantDocument::query()->firstOrFail();
    $media = $document->getFirstMedia(ApplicantDocument::COLLECTION);

    expect($document->status->value)->toBe('pending')->and($document->case_id)->toBe($this->case->id)->and($media->disk)->toBe('local')
        ->and(Storage::disk('local')->exists($media->getPathRelativeToRoot()))->toBeTrue()
        ->and(DB::table('activities')->where('subject_id', $this->applicant->id)->where('title', 'like', 'Tải lên giấy tờ%')->count())->toBe(1);
});

it('accepts pdf and images up to 10 MB only', function () {
    ($this->upload)($this->counselor, ['file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
    ($this->upload)($this->counselor, ['file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')])->assertSessionHasErrors('file');
    ($this->upload)($this->counselor, ['doc_type' => 'nonsense'])->assertSessionHasErrors('doc_type');
    ($this->upload)($this->counselor, ['file' => UploadedFile::fake()->image('cccd.jpg')])->assertSessionHasNoErrors();

    expect(ApplicantDocument::query()->count())->toBe(1);
});

it('refuses a file whose content is not what its extension says', function () {
    ($this->upload)($this->counselor, ['file' => UploadedFile::fake()->createWithContent('fake.pdf', 'MZ this is an executable')])->assertSessionHasErrors('file');

    expect(ApplicantDocument::query()->count())->toBe(0);
});

it('ignores a case that belongs to another applicant', function () {
    $other = app(CreateAdmissionCase::class)->handle(Applicant::factory()->for($this->org)->create(), $this->cycle, ['owner_id' => $this->counselor->id], $this->admin);

    ($this->upload)($this->counselor, ['case_uuid' => $other->uuid])->assertSessionHasNoErrors();

    expect(ApplicantDocument::query()->firstOrFail()->case_id)->toBeNull();
});

it('needs the right to update the applicant, within the data scope', function () {
    $stranger = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $stranger->assignRole('counselor');

    ($this->upload)($stranger)->assertNotFound();
    expect(ApplicantDocument::query()->count())->toBe(0);
});

it('serves the file inline to whoever may view the applicant, and logs a CCCD view', function () {
    ($this->upload)($this->counselor, ['doc_type' => 'citizen_id']);
    $document = ApplicantDocument::query()->firstOrFail();

    $response = $this->actingAs($this->counselor)->get("/documents/{$document->id}/file");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')->and($response->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and(DB::table('activity_log')->where('description', 'Xem giấy tờ CCCD')->count())->toBe(1);
});

it('does not serve a file to someone outside the data scope', function () {
    ($this->upload)($this->counselor);
    $document = ApplicantDocument::query()->firstOrFail();
    $stranger = User::factory()->for($this->org)->create(['data_scope' => DataScopeLevel::Own]);
    $stranger->assignRole('counselor');

    $this->actingAs($stranger)->get("/documents/{$document->id}/file")->assertForbidden();
});

it('lets only the registrar review: approve, or reject with a reason', function () {
    ($this->upload)($this->counselor);
    $document = ApplicantDocument::query()->firstOrFail();

    $this->actingAs($this->counselor)->post("/documents/{$document->id}/review", ['approve' => true])->assertForbidden();

    $this->actingAs($this->clerk)->post("/documents/{$document->id}/review", ['approve' => false])->assertSessionHasErrors('note');
    $this->actingAs($this->clerk)->post("/documents/{$document->id}/review", ['approve' => false, 'note' => 'Ảnh mờ'])->assertSessionHasNoErrors();
    expect($document->refresh()->status->value)->toBe('rejected')->and($document->note)->toBe('Ảnh mờ')->and($document->reviewed_by)->toBe($this->clerk->id);

    $this->actingAs($this->clerk)->post("/documents/{$document->id}/review", ['approve' => true])->assertSessionHasNoErrors();
    expect($document->refresh()->status->value)->toBe('approved')->and($document->note)->toBeNull();
});

it('deletes a pending or rejected document but never an approved one', function () {
    ($this->upload)($this->counselor);
    ($this->upload)($this->counselor);
    [$first, $second] = ApplicantDocument::query()->orderBy('id')->get()->all();
    $this->actingAs($this->clerk)->post("/documents/{$second->id}/review", ['approve' => true]);

    $this->actingAs($this->counselor)->delete("/documents/{$second->id}")->assertStatus(422);
    $this->actingAs($this->counselor)->delete("/documents/{$first->id}")->assertSessionHasNoErrors();

    expect(ApplicantDocument::query()->pluck('id')->all())->toBe([$second->id]);
});

it('lists the documents on the applicant page with what the user may do', function () {
    ($this->upload)($this->counselor);

    $this->actingAs($this->clerk)->get("/applicants/{$this->applicant->uuid}")->assertInertia(fn (Assert $page) => $page->has('document_panel.items', 1)
        ->where('document_panel.items.0.type_label', 'Học bạ')->where('document_panel.items.0.status', 'pending')->where('document_panel.items.0.file_name', 'hoc-ba.pdf')
        ->where('document_panel.can.review', true)->has('document_panel.types', 6));

    $this->actingAs($this->counselor)->get("/applicants/{$this->applicant->uuid}")->assertInertia(fn (Assert $page) => $page->where('document_panel.can.review', false)->where('document_panel.can.upload', true));
});
