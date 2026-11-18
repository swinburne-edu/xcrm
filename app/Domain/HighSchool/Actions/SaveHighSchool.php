<?php

namespace App\Domain\HighSchool\Actions;

use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\Organization\Services\TerritoryResolver;

/**
 * Create or update a high school from validated input. A new school without an owner is given
 * to the owner of its province's territory (rule 17), when there is one.
 */
class SaveHighSchool
{
    public function __construct(private readonly TerritoryResolver $territories) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?HighSchool $school, array $data): HighSchool
    {
        $isNew = $school === null;
        $school ??= new HighSchool;

        $school->fill([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'province_id' => $data['province_id'] ?? null,
            'ward_id' => $data['ward_id'] ?? null,
            'address' => $data['address'] ?? null,
            'priority_area_id' => $data['priority_area_id'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'grade12_students' => $data['grade12_students'] ?? null,
            'grade12_classes' => $data['grade12_classes'] ?? null,
            'strong_combinations' => array_values($data['strong_combinations'] ?? []) ?: null,
            'tier' => $data['tier'] ?? null,
            'partnership_status' => $data['partnership_status'] ?? $school->partnership_status,
            'mou_signed_at' => $data['mou_signed_at'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        if ($isNew && $school->owner_id === null) {
            $school->owner_id = $this->territories->ownerFor($school)?->userId;
        }

        $school->save();

        return $school->refresh();
    }
}
