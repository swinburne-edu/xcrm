<?php

namespace App\Domain\Applicant\Models;

use App\Domain\Applicant\Enums\CertificateType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A language / international certificate of an applicant (IELTS 6.5, SAT 1300…); the score is free text.
 *
 * @property int $id
 * @property int $applicant_id
 * @property CertificateType $type
 * @property string|null $score
 * @property Carbon|null $issued_at
 * @property Carbon|null $expires_at
 * @property bool $verified
 */
#[Fillable(['applicant_id', 'type', 'score', 'issued_at', 'expires_at', 'verified'])]
class ApplicantCertificate extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['type' => CertificateType::class, 'issued_at' => 'date', 'expires_at' => 'date', 'verified' => 'boolean'];
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
