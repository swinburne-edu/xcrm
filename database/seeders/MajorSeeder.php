<?php

namespace Database\Seeders;

use App\Domain\Major\Models\Major;
use App\Domain\Organization\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * The two majors the institution opens: Leadership and Management, Marketing and Communication. They belong to one
 * organization (the first one unless seedFor() is called). Quotas are per admission cycle, so they are set on the
 * "Chỉ tiêu" screen, not here. Safe to run again: majors are matched by admission code.
 */
class MajorSeeder extends Seeder
{
    /** @var list<array{admission_code: string, name: string, description: string}> */
    public const MAJORS = [
        ['admission_code' => 'LM', 'name' => 'Leadership and Management', 'description' => 'Chương trình đào tạo về lãnh đạo và quản trị.'],
        ['admission_code' => 'MC', 'name' => 'Marketing and Communication', 'description' => 'Chương trình đào tạo về marketing và truyền thông.'],
    ];

    public function run(): void
    {
        $org = Organization::query()->orderBy('id')->first();

        if ($org === null) {
            $this->command?->warn('Chưa có tổ chức nào — bỏ qua danh sách ngành.');

            return;
        }

        $this->seedFor($org);
    }

    /** @return list<Major> */
    public function seedFor(Organization $org): array
    {
        $majors = [];

        foreach (self::MAJORS as $row) {
            $majors[] = Major::query()->withoutGlobalScopes()->updateOrCreate(
                ['organization_id' => $org->id, 'admission_code' => $row['admission_code']],
                ['name' => $row['name'], 'description' => $row['description'], 'program_type' => 'standard', 'level' => 'university', 'duration_years' => 4, 'is_active' => true],
            );
        }

        $this->command?->info(count($majors).' ngành.');

        return $majors;
    }
}
