<?php

namespace App\Domain\Applicant\Models;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Applicant\Enums\DocumentStatus;
use App\Domain\Applicant\Enums\DocumentType;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Models\User;
use Database\Factories\ApplicantDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A supporting document (học bạ, CCCD, giấy tờ ưu tiên…) of an applicant; the file lives in the private
 * "documents" media collection. The registrar approves or rejects it.
 *
 * @property int $id
 * @property int $applicant_id
 * @property int|null $case_id
 * @property DocumentType $doc_type
 * @property DocumentStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $note
 */
#[Fillable(['organization_id', 'applicant_id', 'case_id', 'doc_type', 'status', 'reviewed_by', 'reviewed_at', 'note'])]
#[UseFactory(ApplicantDocumentFactory::class)]
class ApplicantDocument extends Model implements HasMedia
{
    /** @use HasFactory<ApplicantDocumentFactory> */
    use BelongsToOrganization, HasAuditColumns, HasFactory, InteractsWithMedia, SoftDeletes;

    public const COLLECTION = 'documents';

    public const ALLOWED_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['doc_type' => DocumentType::class, 'status' => DocumentStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        // Identity documents must never be public: private disk, served through ApplicantDocumentController.
        $this->addMediaCollection(self::COLLECTION)->useDisk('local')->singleFile()->acceptsMimeTypes(self::ALLOWED_MIMES);
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /** @return BelongsTo<AdmissionCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(AdmissionCase::class, 'case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
