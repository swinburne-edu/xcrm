<?php

namespace App\Domain\Applicant\Actions;

use App\Domain\Activity\Actions\RecordActivity;
use App\Domain\Activity\Enums\ActivityType;
use App\Domain\Applicant\Enums\DocumentStatus;
use App\Domain\Applicant\Models\ApplicantDocument;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The registrar approves or rejects a document. A rejection needs a reason; a decision can be changed
 * (e.g. the applicant uploads a better scan and the reviewer re-reviews it).
 */
class ReviewApplicantDocument
{
    public function __construct(private readonly RecordActivity $record) {}

    public function handle(ApplicantDocument $document, bool $approve, ?string $note, User $by): ApplicantDocument
    {
        $note = $note === null ? null : trim($note);

        if (! $approve && ($note === null || $note === '')) {
            throw ValidationException::withMessages(['note' => 'Cần ghi lý do khi từ chối giấy tờ.']);
        }

        $document->forceFill([
            'status' => $approve ? DocumentStatus::Approved : DocumentStatus::Rejected,
            'note' => $approve ? null : $note,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
        ])->save();

        $this->record->handle($document->applicant, ActivityType::System, [
            'title' => ($approve ? 'Duyệt' : 'Từ chối')." giấy tờ: {$document->doc_type->label()}",
            'meta' => ['document_id' => $document->id, 'status' => $document->status->value, 'note' => $document->note],
        ], $by);

        return $document;
    }
}
