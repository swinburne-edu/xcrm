<?php

namespace App\Http\Controllers\Applicant;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Actions\ReviewApplicantDocument;
use App\Domain\Applicant\Actions\StoreApplicantDocument;
use App\Domain\Applicant\Enums\DocumentStatus;
use App\Domain\Applicant\Enums\DocumentType;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Supporting documents of an applicant (task 2.2).
 *  POST   /applicants/{uuid}/documents   upload (needs applicant.update on that applicant)
 *  GET    /documents/{id}/file           view the file (needs applicant.view; viewing a CCCD scan is logged)
 *  POST   /documents/{id}/review         approve / reject (needs application.review)
 *  DELETE /documents/{id}                remove a document that is not approved yet
 */
class ApplicantDocumentController extends Controller
{
    public function store(Request $request, string $uuid, StoreApplicantDocument $store): RedirectResponse
    {
        $applicant = Applicant::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $applicant);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimetypes:'.implode(',', ApplicantDocument::ALLOWED_MIMES)],
            'doc_type' => ['required', Rule::enum(DocumentType::class)],
            'case_uuid' => ['nullable', 'string'],
        ], [], ['file' => 'tệp', 'doc_type' => 'loại giấy tờ']);

        $case = filled($data['case_uuid'] ?? null) ? AdmissionCase::query()->where('uuid', $data['case_uuid'])->first() : null;
        $document = $store->handle($applicant, $request->file('file'), DocumentType::from($data['doc_type']), $case, $request->user());

        return $this->toast("Đã tải lên {$document->doc_type->label()}.");
    }

    public function file(Request $request, int $id): BinaryFileResponse
    {
        $document = ApplicantDocument::query()->with('applicant')->findOrFail($id);
        $this->authorize('view', $document->applicant);

        $media = $document->getFirstMedia(ApplicantDocument::COLLECTION);
        abort_if($media === null || ! is_file($media->getPath()), 404);

        if ($document->doc_type === DocumentType::CitizenId) {
            activity()->causedBy($request->user())->performedOn($document->applicant)->withProperties(['document_id' => $document->id])->log('Xem giấy tờ CCCD');
        }

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '_', $media->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function review(Request $request, int $id, ReviewApplicantDocument $review): RedirectResponse
    {
        $document = ApplicantDocument::query()->with('applicant')->findOrFail($id);
        $this->authorize('view', $document->applicant);
        abort_unless($request->user()->can('application.review'), 403);

        $data = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:500']], [], ['note' => 'lý do']);
        $review->handle($document, (bool) $data['approve'], $data['note'] ?? null, $request->user());

        return $this->toast($data['approve'] ? 'Đã duyệt giấy tờ.' : 'Đã từ chối giấy tờ.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $document = ApplicantDocument::query()->with('applicant')->findOrFail($id);
        $this->authorize('update', $document->applicant);
        abort_if($document->status === DocumentStatus::Approved, 422, 'Giấy tờ đã duyệt không xoá được.');

        $document->delete();

        return $this->toast('Đã xoá giấy tờ.');
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back(fallback: '/applicants');
    }
}
