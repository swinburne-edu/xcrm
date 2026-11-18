<?php

namespace App\Domain\HighSchool\Actions;

use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolContact;
use Illuminate\Support\Facades\DB;

/**
 * Create or update a contact of a school. At most one contact per school is primary: marking
 * one primary clears the flag on the others.
 */
class SaveSchoolContact
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(HighSchool $school, ?SchoolContact $contact, array $data): SchoolContact
    {
        return DB::transaction(function () use ($school, $contact, $data): SchoolContact {
            $contact ??= new SchoolContact(['organization_id' => $school->organization_id, 'high_school_id' => $school->id]);

            $contact->fill([
                'full_name' => $data['full_name'],
                'role' => $data['role'] ?? null,
                'homeroom_class' => $data['homeroom_class'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'zalo' => $data['zalo'] ?? null,
                'relationship_level' => $data['relationship_level'] ?? null,
                'is_primary' => (bool) ($data['is_primary'] ?? false),
                'note' => $data['note'] ?? null,
            ])->save();

            if ($contact->is_primary) {
                SchoolContact::query()->where('high_school_id', $school->id)->whereKeyNot($contact->id)->update(['is_primary' => false]);
            }

            return $contact;
        });
    }
}
