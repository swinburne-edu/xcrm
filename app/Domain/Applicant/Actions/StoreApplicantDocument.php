<?php

namespace App\Domain\Applicant\Actions;

use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Enums\DocumentType;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\ApplicantDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;

/**
 * Stores one supporting document of an applicant (pending review). The file goes to the private "documents"
 * collection; it is only served through ApplicantDocumentController after a permission and data-scope check.
 * A case, when given, must belong to the applicant.
 */
class StoreApplicantDocument
{
    public function __construct(private readonly RecordActivity $record) {}

    public function handle(Applicant $applicant, UploadedFile $file, DocumentType $type, ?AdmissionCase $case = null, ?User $by = null): ApplicantDocument
    {
        return DB::transaction(function () use ($applicant, $file, $type, $case, $by): ApplicantDocument {
            $document = ApplicantDocument::query()->create([
                'organization_id' => $applicant->organization_id,
                'applicant_id' => $applicant->id,
                'case_id' => $case?->applicant_id === $applicant->id ? $case->id : null,
                'doc_type' => $type,
            ]);

            try {
                $document->addMedia($file)->usingName($type->label())->toMediaCollection(ApplicantDocument::COLLECTION);
            } catch (FileUnacceptableForCollection) {
                // The content is not what the extension says (the real type is sniffed, not trusted).
                throw ValidationException::withMessages(['file' => 'Tệp không hợp lệ: chỉ nhận PDF hoặc ảnh JPG, PNG, WEBP.']);
            }

            $this->record->handle($applicant, ActivityType::System, [
                'title' => "Tải lên giấy tờ: {$type->label()}",
                'meta' => ['document_id' => $document->id, 'doc_type' => $type->value],
            ], $by);

            return $document;
        });
    }
}
