<?php

namespace App\Domain\Major\Actions;

use App\Domain\Major\Models\Major;

/**
 * Create or update a major from validated input (MajorRequest).
 */
class SaveMajor
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Major $major, array $data): Major
    {
        $major ??= new Major;

        $major->fill([
            'moet_code' => $data['moet_code'] ?? null,
            'admission_code' => $data['admission_code'],
            'name' => $data['name'],
            'faculty_id' => $data['faculty_id'] ?? null,
            'campus_id' => $data['campus_id'] ?? null,
            'program_type' => $data['program_type'] ?? $major->program_type,
            'level' => $data['level'] ?? $major->level,
            'duration_years' => $data['duration_years'] ?? null,
            'description' => $data['description'] ?? null,
            'career_info' => $data['career_info'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->save();

        return $major->refresh();
    }
}
