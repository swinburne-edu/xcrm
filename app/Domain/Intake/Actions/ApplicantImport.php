<?php

namespace App\Domain\Intake\Actions;

use App\Domain\Applicant\Services\ApplicantDeduplicator;
use App\Domain\Catalog\Models\LeadSource;
use App\Domain\Intake\Models\ImportJob;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Shared\Services\PhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Applicants from a spreadsheet (first sheet, first row = headings). Every row goes through IntakeLead, so it
 * gets the same duplicate check, case, owner and "Gọi lần đầu" task as a web lead; the source is "Import".
 *
 *   read()     headings + rows of a file
 *   suggest()  which heading holds which field, recognised by name (Vietnamese or English)
 *   preview()  what an import would do (new / duplicate / error per row), writing nothing
 *   process()  the real import of an ImportJob, run from the queue
 */
class ApplicantImport
{
    public const MAX_ROWS = 5000;

    public const PREVIEW_ROWS = 15;

    public const MAX_ERRORS = 200;

    /** field => label shown in the wizard */
    public const FIELDS = [
        'full_name' => 'Họ và tên',
        'phone' => 'Số điện thoại',
        'email' => 'Email',
        'gender' => 'Giới tính',
        'dob' => 'Ngày sinh',
        'citizen_id' => 'CCCD',
        'province' => 'Tỉnh/thành phố',
        'high_school' => 'Trường THPT',
        'graduation_year' => 'Năm tốt nghiệp',
        'major' => 'Ngành quan tâm',
        'note' => 'Ghi chú',
    ];

    private const ALIASES = [
        'full_name' => ['full_name', 'ho_ten', 'ho_va_ten', 'ten', 'ten_thi_sinh', 'name'],
        'phone' => ['phone', 'sdt', 'so_dien_thoai', 'dien_thoai', 'di_dong', 'mobile'],
        'email' => ['email', 'e_mail', 'thu_dien_tu'],
        'gender' => ['gender', 'gioi_tinh'],
        'dob' => ['dob', 'ngay_sinh', 'birthday'],
        'citizen_id' => ['citizen_id', 'cccd', 'can_cuoc', 'so_cccd', 'cmnd'],
        'province' => ['province', 'tinh', 'tinh_thanh_pho', 'tinh_tp', 'thanh_pho'],
        'high_school' => ['high_school', 'truong', 'truong_thpt', 'ten_truong'],
        'graduation_year' => ['graduation_year', 'nam_tot_nghiep', 'nam_tn'],
        'major' => ['major', 'nganh', 'nganh_quan_tam', 'nganh_dang_ky'],
        'note' => ['note', 'ghi_chu', 'loi_nhan'],
    ];

    public function __construct(private readonly IntakeLead $intake, private readonly CurrentOrganization $current) {}

    /**
     * @return array{headings: list<string>, rows: list<list<mixed>>}
     */
    public function read(string|UploadedFile $file): array
    {
        $sheets = Excel::toArray(new class implements ToArray
        {
            public function array(array $array): void {}
        }, $file);
        $table = array_values($sheets[0] ?? []);
        $headings = array_map(fn ($h) => trim((string) $h), $table[0] ?? []);
        $rows = array_values(array_filter(
            array_slice($table, 1, self::MAX_ROWS),
            fn (array $row) => count(array_filter($row, fn ($c) => trim((string) $c) !== '')) > 0,
        ));

        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * @param  list<string>  $headings
     * @return array<string, string> field => heading
     */
    public function suggest(array $headings): array
    {
        $slugs = [];

        foreach ($headings as $heading) {
            $slugs[Str::slug($heading, '_')] ??= $heading;
        }

        $mapping = [];

        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($slugs[$alias]) && ! in_array($slugs[$alias], $mapping, true)) {
                    $mapping[$field] = $slugs[$alias];

                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Dry run: first rows as they would be read, and the totals over the whole file.
     *
     * @param  array<string, string>  $mapping
     * @return array{total: int, new: int, duplicate: int, error: int, rows: list<array<string, mixed>>}
     */
    public function preview(int $organizationId, string|UploadedFile $file, array $mapping): array
    {
        ['headings' => $headings, 'rows' => $rows] = $this->read($file);
        $result = ['total' => count($rows), 'new' => 0, 'duplicate' => 0, 'error' => 0, 'rows' => []];
        $duplicates = app(ApplicantDeduplicator::class);
        $seen = [];

        foreach ($rows as $i => $row) {
            $data = $this->rowData($row, $headings, $mapping);
            $entry = ['row' => $i + 2, 'data' => $data, 'status' => 'new', 'message' => null];

            try {
                $clean = $this->intake->clean($organizationId, $data);
                $key = app(PhoneNormalizer::class)->normalize($clean['phone']) ?? $clean['email'];

                if ($this->current->run($organizationId, fn () => $duplicates->find($clean)) !== null || ($key !== null && isset($seen[$key]))) {
                    $entry['status'] = 'duplicate';
                    $entry['message'] = 'Trùng thí sinh đã có.';
                }

                if ($key !== null) {
                    $seen[$key] = true;
                }
            } catch (ValidationException $e) {
                $entry['status'] = 'error';
                $entry['message'] = implode(' ', array_map(fn ($m) => $m[0], $e->errors()));
            }

            $result[$entry['status'] === 'error' ? 'error' : $entry['status']]++;

            if ($i < self::PREVIEW_ROWS) {
                $result['rows'][] = $entry;
            }
        }

        return $result;
    }

    public function process(ImportJob $job): void
    {
        $job->update(['status' => 'processing']);

        try {
            ['headings' => $headings, 'rows' => $rows] = $this->read(Storage::disk('local')->path($job->file_path));
            $sourceId = LeadSource::query()->withoutGlobalScopes()->where('organization_id', $job->organization_id)->where('code', 'import')->value('id');
            $errors = [];
            $counts = ['success' => 0, 'duplicate' => 0, 'error' => 0];

            foreach ($rows as $i => $row) {
                try {
                    $result = $this->intake->handle($job->organization_id, $this->rowData($row, $headings, $job->mapping ?? []), [
                        'source_id' => $sourceId,
                        'campaign_id' => $job->campaign_id,
                        'label' => 'Nhập từ Excel',
                        'payload' => ['import_job_id' => $job->id],
                    ]);
                    $counts[$result['created'] ? 'success' : 'duplicate']++;
                } catch (ValidationException $e) {
                    $counts['error']++;

                    if (count($errors) < self::MAX_ERRORS) {
                        $errors[] = ['row' => $i + 2, 'message' => implode(' ', array_map(fn ($m) => $m[0], $e->errors()))];
                    }
                }

                if (($i + 1) % 50 === 0) {
                    $job->update(['success_rows' => $counts['success'], 'duplicate_rows' => $counts['duplicate'], 'error_rows' => $counts['error']]);
                }
            }

            $job->update([
                'status' => 'done',
                'total_rows' => count($rows),
                'success_rows' => $counts['success'],
                'duplicate_rows' => $counts['duplicate'],
                'error_rows' => $counts['error'],
                'errors' => $errors ?: null,
            ]);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'errors' => [['row' => 0, 'message' => 'Không đọc được file: '.$e->getMessage()]]]);
        } finally {
            Storage::disk('local')->delete($job->file_path);
        }
    }

    /**
     * @param  list<mixed>  $row
     * @param  list<string>  $headings
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    private function rowData(array $row, array $headings, array $mapping): array
    {
        $data = [];

        foreach ($mapping as $field => $heading) {
            $index = array_search($heading, $headings, true);

            if ($index !== false && array_key_exists($field, self::FIELDS)) {
                $data[$field] = $row[$index] ?? null;
            }
        }

        return $data;
    }
}
