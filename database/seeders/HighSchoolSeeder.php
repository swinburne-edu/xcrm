<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Ward;
use App\Domain\HighSchool\Enums\SchoolType;
use App\Domain\Organization\Models\Organization;
use App\Domain\HighSchool\Models\HighSchool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * High schools of the whole country from database/data/danh_sach_truong_thpt_2026.json (the MOET list), for one
 * organization (the first one unless seedFor() is called). Needs CatalogSeeder first (provinces, wards, priority areas).
 *
 * The MOET school code is only unique inside a province, so `high_schools.code` = province code + school code
 * (Phan Đình Phùng, Hà Nội → "01066"). Rows are matched by that code: running again updates, never duplicates.
 * Entries whose ward is not in xa_phuong.json (e.g. "Học ở nước ngoài") keep `ward_id` empty.
 */
class HighSchoolSeeder extends Seeder
{
    /** Folder with the JSON file (default database/data). */
    public ?string $dataDirectory = null;

    public function run(): void
    {
        $org = Organization::query()->orderBy('id')->first();

        if ($org === null) {
            $this->command?->warn('Chưa có tổ chức nào — bỏ qua danh sách trường THPT.');

            return;
        }

        $this->seedFor($org);
    }

    /** @return int number of schools written */
    public function seedFor(Organization $org): int
    {
        $path = ($this->dataDirectory ?? database_path('data')).DIRECTORY_SEPARATOR.'danh_sach_truong_thpt_2026.json';

        if (! is_file($path)) {
            $this->command?->warn('Chưa có database/data/danh_sach_truong_thpt_2026.json — bỏ qua.');

            return 0;
        }

        $schools = json_decode((string) file_get_contents($path), true);

        if (! is_array($schools)) {
            throw new RuntimeException('database/data/danh_sach_truong_thpt_2026.json không phải JSON hợp lệ.');
        }

        $provinceIds = Province::query()->pluck('id', 'code');
        $wardIds = Ward::query()->pluck('id', 'code');
        $areaIds = PriorityArea::query()->pluck('id', 'code');
        $now = now();
        $rows = [];

        foreach ($schools as $row) {
            $code = $row['province_code'].$row['school_code'];

            $rows[$code] = [
                'organization_id' => $org->id,
                'code' => $code,
                'name' => trim($row['school_name']),
                'type' => self::typeOf($row['school_name'])->value,
                'province_id' => $provinceIds[(string) $row['province_code']] ?? null,
                'ward_id' => $wardIds[(string) $row['ward_code']] ?? null,
                'address' => mb_substr(trim((string) ($row['address'] ?? '')), 0, 500) ?: null,
                'priority_area_id' => $areaIds[str_replace('_', '-', (string) ($row['region'] ?? ''))] ?? null,
                'partnership_status' => 'not_contacted',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                // Only the facts that come from the list are refreshed; owner, tier, partnership… are the user's.
                HighSchool::query()->withoutGlobalScopes()->upsert($chunk, ['organization_id', 'code'], ['name', 'type', 'province_id', 'ward_id', 'address', 'priority_area_id']);
            }
        });

        $this->command?->info(count($rows).' trường THPT.');

        return count($rows);
    }

    private static function typeOf(string $name): SchoolType
    {
        return match (true) {
            (bool) preg_match('/GDTX|GDNN|Giáo dục thường xuyên|Giáo dục nghề nghiệp|^TT\b|Trung tâm/u', $name) => SchoolType::ContinuingEdu,
            (bool) preg_match('/quốc tế/iu', $name) => SchoolType::International,
            (bool) preg_match('/Chuyên|Năng khiếu/u', $name) => SchoolType::Gifted,
            (bool) preg_match('/PTDTNT|Dân tộc nội trú|DTNT|Dân tộc bán trú/u', $name) => SchoolType::Specialized,
            (bool) preg_match('/Tư thục|Dân lập|Ngoài công lập/u', $name) => SchoolType::Private,
            default => SchoolType::Public,
        };
    }
}
