<?php

namespace App\Domain\Applicant\Actions;

use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\Guardian;
use App\Domain\Shared\Services\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Add or edit a guardian of an applicant. A new guardian whose phone already belongs to a guardian of the
 * organization is linked instead of duplicated (siblings share parents). At most one primary contact per applicant.
 */
class SaveGuardian
{
    public function __construct(private readonly PhoneNormalizer $phones) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Applicant $applicant, ?Guardian $guardian, array $data): Guardian
    {
        return DB::transaction(function () use ($applicant, $guardian, $data): Guardian {
            $phone = $this->phones->normalize($data['phone'] ?? null);

            if ($guardian === null && $phone !== null) {
                $guardian = Guardian::query()->where('phone_normalized', $phone)->first();
            }

            $guardian ??= new Guardian(['organization_id' => $applicant->organization_id]);
            $guardian->fill([
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'zalo_id' => $data['zalo_id'] ?? null,
                'occupation' => $data['occupation'] ?? null,
                'note' => $data['note'] ?? null,
            ])->save();

            $applicant->guardians()->syncWithoutDetaching([$guardian->id => [
                'relationship' => $data['relationship'] ?? null,
                'is_decision_maker' => (bool) ($data['is_decision_maker'] ?? false),
                'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
            ]]);

            if ($data['is_primary_contact'] ?? false) {
                DB::table('applicant_guardian')->where('applicant_id', $applicant->id)->where('guardian_id', '!=', $guardian->id)->update(['is_primary_contact' => false]);
            }

            return $guardian;
        });
    }
}
