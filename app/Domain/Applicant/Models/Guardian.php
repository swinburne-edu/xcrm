<?php

namespace App\Domain\Applicant\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Services\PhoneNormalizer;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A parent / guardian. One guardian can be linked to several applicants (siblings), matched by phone.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $full_name
 * @property string|null $phone
 * @property string|null $phone_normalized
 * @property string|null $email
 * @property string|null $zalo_id
 * @property string|null $occupation
 * @property string|null $note
 */
#[Fillable(['organization_id', 'full_name', 'phone', 'email', 'zalo_id', 'occupation', 'note'])]
#[UseFactory(GuardianFactory::class)]
class Guardian extends Model
{
    /** @use HasFactory<GuardianFactory> */
    use BelongsToOrganization, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $guardian): void {
            $guardian->phone_normalized = app(PhoneNormalizer::class)->normalize($guardian->phone);
        });
    }

    /** @return BelongsToMany<Applicant, $this> */
    public function applicants(): BelongsToMany
    {
        return $this->belongsToMany(Applicant::class, 'applicant_guardian')
            ->withPivot(['relationship', 'is_decision_maker', 'is_primary_contact']);
    }
}
