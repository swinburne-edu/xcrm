<?php

namespace Database\Seeders;

use App\Domain\Catalog\Enums\ProvinceType;
use App\Domain\Catalog\Enums\ScoreSource;
use App\Domain\Catalog\Enums\WardType;
use App\Domain\Catalog\Models\AdmissionMethod;
use App\Domain\Catalog\Models\PriorityArea;
use App\Domain\Catalog\Models\PriorityGroup;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Models\Subject;
use App\Domain\Catalog\Models\SubjectCombination;
use App\Domain\Catalog\Models\Ward;
use App\Domain\Catalog\Models\WardMapping;
use App\Domain\Catalog\Services\CombinationValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * System catalogs (docs/04-database.md §8). Safe to run again: rows are matched by code.
 *
 * Administrative units come from database/data/*.json (see database/data/README.md);
 * when a file is missing the seeder only warns.
 */
class CatalogSeeder extends Seeder
{
    public const PRIORITY_AREAS = [
        ['code' => 'KV1', 'name' => 'Khu vực 1', 'bonus_score' => 0.75],
        ['code' => 'KV2-NT', 'name' => 'Khu vực 2 - nông thôn', 'bonus_score' => 0.50],
        ['code' => 'KV2', 'name' => 'Khu vực 2', 'bonus_score' => 0.25],
        ['code' => 'KV3', 'name' => 'Khu vực 3', 'bonus_score' => 0],
    ];

    public const PRIORITY_GROUPS = [
        ['code' => 'UT1', 'name' => 'Nhóm ưu tiên 1 (đối tượng 01–04)', 'bonus_score' => 2.00],
        ['code' => 'UT2', 'name' => 'Nhóm ưu tiên 2 (đối tượng 05–07)', 'bonus_score' => 1.00],
    ];

    public const SUBJECTS = [
        ['code' => 'TOAN', 'name' => 'Toán', 'is_core' => true],
        ['code' => 'VAN', 'name' => 'Ngữ văn', 'is_core' => true],
        ['code' => 'ANH', 'name' => 'Tiếng Anh', 'is_core' => false],
        ['code' => 'LY', 'name' => 'Vật lí', 'is_core' => false],
        ['code' => 'HOA', 'name' => 'Hoá học', 'is_core' => false],
        ['code' => 'SINH', 'name' => 'Sinh học', 'is_core' => false],
        ['code' => 'SU', 'name' => 'Lịch sử', 'is_core' => false],
        ['code' => 'DIA', 'name' => 'Địa lí', 'is_core' => false],
        ['code' => 'GDKTPL', 'name' => 'Giáo dục kinh tế và pháp luật', 'is_core' => false],
        ['code' => 'TIN', 'name' => 'Tin học', 'is_core' => false],
        ['code' => 'CNCN', 'name' => 'Công nghệ công nghiệp', 'is_core' => false],
        ['code' => 'CNNN', 'name' => 'Công nghệ nông nghiệp', 'is_core' => false],
    ];

    /** code => subject codes (weight 1 each) */
    public const COMBINATIONS = [
        'A00' => ['TOAN', 'LY', 'HOA'],
        'A01' => ['TOAN', 'LY', 'ANH'],
        'B00' => ['TOAN', 'HOA', 'SINH'],
        'B08' => ['TOAN', 'SINH', 'ANH'],
        'C00' => ['VAN', 'SU', 'DIA'],
        'C01' => ['VAN', 'TOAN', 'LY'],
        'C03' => ['VAN', 'TOAN', 'SU'],
        'C04' => ['VAN', 'TOAN', 'DIA'],
        'D01' => ['VAN', 'TOAN', 'ANH'],
        'D07' => ['TOAN', 'HOA', 'ANH'],
        'D14' => ['VAN', 'SU', 'ANH'],
        'D15' => ['VAN', 'DIA', 'ANH'],
    ];

    public const ADMISSION_METHODS = [
        ['code' => '100', 'name' => 'Xét kết quả thi tốt nghiệp THPT', 'score_source' => ScoreSource::ThptExam],
        ['code' => '200', 'name' => 'Xét học bạ THPT', 'score_source' => ScoreSource::Transcript],
        ['code' => '301', 'name' => 'Xét tuyển thẳng theo quy chế', 'score_source' => ScoreSource::Direct],
        ['code' => '401', 'name' => 'Thi ĐGNL/ĐGTD do trường tổ chức', 'score_source' => ScoreSource::CompetencyExam],
        ['code' => '402', 'name' => 'Sử dụng kết quả ĐGNL/ĐGTD đơn vị khác tổ chức', 'score_source' => ScoreSource::CompetencyExam],
        ['code' => '405', 'name' => 'Kết hợp thi THPT và năng khiếu', 'score_source' => ScoreSource::Combined],
        ['code' => '500', 'name' => 'Phương thức khác', 'score_source' => null],
    ];

    /** Folder with provinces.json / wards.json / ward_mappings.json (default database/data). */
    public ?string $dataDirectory = null;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedByCode(PriorityArea::class, self::PRIORITY_AREAS);
            $this->seedByCode(PriorityGroup::class, self::PRIORITY_GROUPS);
            $this->seedByCode(Subject::class, self::SUBJECTS);
            $this->seedCombinations();
            $this->seedByCode(AdmissionMethod::class, self::ADMISSION_METHODS);
        });

        $this->seedAdministrativeUnits();
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array<string, mixed>>  $rows
     */
    private function seedByCode(string $model, array $rows): void
    {
        foreach ($rows as $row) {
            $model::query()->updateOrCreate(['code' => $row['code']], $row);
        }
    }

    private function seedCombinations(): void
    {
        $subjectIds = Subject::query()->pluck('id', 'code');
        $validator = app(CombinationValidator::class);

        foreach (self::COMBINATIONS as $code => $codes) {
            $subjects = array_map(fn (string $c) => ['code' => $c, 'weight' => 1], $codes);

            if (! $validator->passes($subjects)) {
                throw new RuntimeException("Seed combination {$code} violates rule R2.");
            }

            $names = Subject::query()->whereIn('code', $codes)->pluck('name', 'code');

            $combination = SubjectCombination::query()->updateOrCreate(
                ['code' => $code],
                ['name' => implode(', ', array_map(fn (string $c) => $names[$c], $codes)), 'is_active' => true],
            );

            $combination->subjects()->sync(
                collect($codes)->mapWithKeys(fn (string $c) => [$subjectIds[$c] => ['weight' => 1]])->all(),
            );
        }
    }

    private function seedAdministrativeUnits(): void
    {
        $provinces = $this->readJson('provinces.json');

        if ($provinces === null) {
            return;
        }

        foreach ($provinces as $row) {
            Province::query()->updateOrCreate(
                ['code' => (string) $row['code']],
                [
                    'name' => $row['name'],
                    'type' => ProvinceType::tryFrom((string) ($row['type'] ?? '')),
                    'is_active' => $row['is_active'] ?? true,
                ],
            );
        }

        $this->info(count($provinces).' tỉnh/thành phố.');

        $wards = $this->readJson('wards.json');

        if ($wards === null) {
            return;
        }

        $provinceIds = Province::query()->pluck('id', 'code');
        $rows = [];

        foreach ($wards as $row) {
            $provinceId = $provinceIds[(string) $row['province_code']] ?? null;

            if ($provinceId === null) {
                $this->warn("Bỏ qua xã/phường {$row['code']}: không có tỉnh mã {$row['province_code']}.");

                continue;
            }

            $rows[] = [
                'province_id' => $provinceId,
                'code' => (string) $row['code'],
                'name' => $row['name'],
                'type' => WardType::tryFrom((string) ($row['type'] ?? ''))?->value,
                'is_active' => $row['is_active'] ?? true,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Ward::query()->upsert($chunk, ['code'], ['province_id', 'name', 'type', 'is_active']);
        }

        $this->info(count($rows).' xã/phường.');

        $this->seedWardMappings();
    }

    /**
     * The JSON file is the source of truth: existing mappings are replaced.
     */
    private function seedWardMappings(): void
    {
        $mappings = $this->readJson('ward_mappings.json', warnWhenMissing: false);

        if ($mappings === null) {
            return;
        }

        $wardIds = Ward::query()->pluck('id', 'code');

        DB::transaction(function () use ($mappings, $wardIds): void {
            WardMapping::query()->delete();

            $rows = array_map(fn (array $row) => [
                'old_province_name' => $row['old_province_name'] ?? null,
                'old_district_name' => $row['old_district_name'] ?? null,
                'old_ward_name' => $row['old_ward_name'] ?? null,
                'new_ward_id' => $wardIds[(string) ($row['new_ward_code'] ?? '')] ?? null,
            ], $mappings);

            foreach (array_chunk($rows, 500) as $chunk) {
                WardMapping::query()->insert($chunk);
            }
        });

        $this->info(count($mappings).' dòng ánh xạ địa giới cũ → mới.');
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function readJson(string $file, bool $warnWhenMissing = true): ?array
    {
        $path = ($this->dataDirectory ?? database_path('data')).DIRECTORY_SEPARATOR.$file;

        if (! is_file($path)) {
            if ($warnWhenMissing) {
                $this->warn("Chưa có database/data/{$file} — bỏ qua (xem database/data/README.md).");
            }

            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException("database/data/{$file} không phải JSON hợp lệ.");
        }

        return array_values($data);
    }

    private function warn(string $message): void
    {
        $this->command?->warn($message);
    }

    private function info(string $message): void
    {
        $this->command?->info($message);
    }
}
