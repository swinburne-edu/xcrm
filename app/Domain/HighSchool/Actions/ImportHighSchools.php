<?php

namespace App\Domain\HighSchool\Actions;

use App\Domain\Catalog\Models\Province;
use App\Domain\HighSchool\Enums\PartnershipStatus;
use App\Domain\HighSchool\Enums\SchoolTier;
use App\Domain\HighSchool\Enums\SchoolType;
use App\Domain\HighSchool\Imports\HighSchoolSheet;
use App\Domain\HighSchool\Models\HighSchool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Imports high schools from an Excel/CSV sheet. Columns are recognised by heading (Vietnamese or
 * English): Mã trường/code, Tên trường/name, Loại hình/type, Tỉnh/province (name or code), Địa chỉ/address,
 * Hạng/tier, Trạng thái hợp tác/partnership_status, Số học sinh khối 12/grade12_students, Số lớp khối 12/grade12_classes,
 * Điện thoại/phone, Email, Website, Ghi chú/note.
 *
 * A row with a known code (or, without a code, the same name in the same province) updates that school with
 * the cells that are filled; otherwise a school is created. Rows with errors are skipped and reported;
 * a dry run reports the same result without writing anything.
 */
class ImportHighSchools
{
    public const MAX_ROWS = 5000;

    private const ALIASES = [
        'code' => ['code', 'ma_truong', 'ma'],
        'name' => ['name', 'ten_truong', 'ten'],
        'type' => ['type', 'loai_hinh', 'loai'],
        'province' => ['province', 'tinh', 'tinh_thanh_pho', 'tinh_tp'],
        'address' => ['address', 'dia_chi'],
        'tier' => ['tier', 'hang'],
        'partnership_status' => ['partnership_status', 'trang_thai_hop_tac', 'trang_thai'],
        'grade12_students' => ['grade12_students', 'so_hoc_sinh_khoi_12', 'hoc_sinh_khoi_12'],
        'grade12_classes' => ['grade12_classes', 'so_lop_khoi_12', 'lop_khoi_12'],
        'phone' => ['phone', 'dien_thoai', 'sdt'],
        'email' => ['email'],
        'website' => ['website', 'web'],
        'note' => ['note', 'ghi_chu'],
    ];

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, message: string}>, dry_run: bool}
     */
    public function handle(UploadedFile $file, bool $dryRun = false): array
    {
        $sheet = new HighSchoolSheet;
        Excel::import($sheet, $file);
        $rows = array_slice($sheet->rows, 0, self::MAX_ROWS);

        $provincesByCode = Province::query()->pluck('id', 'code')->all();
        $provincesByName = Province::query()->get(['id', 'name'])->mapWithKeys(fn ($p) => [Str::lower($p->name) => $p->id])->all();

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'dry_run' => $dryRun];

        DB::beginTransaction();

        try {
            foreach ($rows as $i => $raw) {
                $line = $i + 2; // heading is row 1
                $row = $this->pick($raw);

                if ($row === []) {
                    continue;
                }

                [$data, $errors] = $this->normalise($row, $provincesByCode, $provincesByName);

                if ($errors !== []) {
                    $result['skipped']++;
                    $result['errors'][] = ['row' => $line, 'message' => implode(' ', $errors)];

                    continue;
                }

                $existing = $this->find($data);

                if ($existing !== null) {
                    $existing->fill(array_filter($data, fn ($v) => $v !== null))->save();
                    $result['updated']++;
                } else {
                    HighSchool::query()->create(array_filter($data, fn ($v) => $v !== null));
                    $result['created']++;
                }
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    private function pick(array $raw): array
    {
        $normalised = [];

        foreach ($raw as $heading => $value) {
            $normalised[Str::slug((string) $heading, '_')] = is_string($value) ? trim($value) : $value;
        }

        $row = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($normalised[$alias]) && $normalised[$alias] !== '') {
                    $row[$field] = (string) $normalised[$alias];

                    break;
                }
            }
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int>  $byCode
     * @param  array<string, int>  $byName
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function normalise(array $row, array $byCode, array $byName): array
    {
        $errors = [];
        $data = [
            'code' => isset($row['code']) ? Str::upper($row['code']) : null,
            'name' => $row['name'] ?? null,
            'address' => $row['address'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'website' => $row['website'] ?? null,
            'note' => $row['note'] ?? null,
            'province_id' => null, 'type' => null, 'tier' => null, 'partnership_status' => null,
            'grade12_students' => null, 'grade12_classes' => null,
        ];

        if (blank($data['name'])) {
            $errors[] = 'Thiếu tên trường.';
        }

        if (isset($row['province'])) {
            $data['province_id'] = $byCode[$row['province']] ?? $byName[Str::lower($row['province'])] ?? null;

            if ($data['province_id'] === null) {
                $errors[] = "Không tìm thấy tỉnh/thành phố \"{$row['province']}\".";
            }
        }

        foreach (['type' => SchoolType::class, 'partnership_status' => PartnershipStatus::class] as $field => $enum) {
            if (isset($row[$field])) {
                $case = $this->matchEnum($enum, $row[$field]);
                $case === null ? $errors[] = "Giá trị \"{$row[$field]}\" không hợp lệ cho cột {$field}." : $data[$field] = $case;
            }
        }

        if (isset($row['tier'])) {
            $tier = SchoolTier::tryFrom(Str::upper($row['tier']));
            $tier === null ? $errors[] = 'Hạng phải là A, B hoặc C.' : $data['tier'] = $tier;
        }

        foreach (['grade12_students', 'grade12_classes'] as $field) {
            if (isset($row[$field])) {
                is_numeric($row[$field]) && $row[$field] >= 0
                    ? $data[$field] = (int) $row[$field]
                    : $errors[] = "{$field} phải là số không âm.";
            }
        }

        if ($data['email'] !== null && Validator::make(['e' => $data['email']], ['e' => 'email'])->fails()) {
            $errors[] = 'Email không hợp lệ.';
        }

        foreach (['code' => 20, 'phone' => 20, 'name' => 255, 'website' => 255, 'address' => 500] as $field => $max) {
            if (($data[$field] ?? null) !== null && mb_strlen((string) $data[$field]) > $max) {
                $errors[] = "{$field} dài quá {$max} ký tự.";
            }
        }

        return [$data, $errors];
    }

    /**
     * @param  class-string<\BackedEnum>  $enum
     */
    private function matchEnum(string $enum, string $value): ?\BackedEnum
    {
        $needle = Str::lower($value);

        foreach ($enum::cases() as $case) {
            if (Str::lower((string) $case->value) === $needle || Str::lower($case->label()) === $needle) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function find(array $data): ?HighSchool
    {
        if ($data['code'] !== null) {
            return HighSchool::query()->where('code', $data['code'])->first();
        }

        return HighSchool::query()->whereNull('code')->where('name', $data['name'])
            ->when($data['province_id'] !== null, fn ($q) => $q->where('province_id', $data['province_id']))->first();
    }
}
