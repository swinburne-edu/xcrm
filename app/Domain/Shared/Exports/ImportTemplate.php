<?php

namespace App\Domain\Shared\Exports;

use App\Domain\HighSchool\Enums\PartnershipStatus;
use App\Domain\HighSchool\Enums\SchoolTier;
use App\Domain\HighSchool\Enums\SchoolType;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Downloadable Excel templates for the import screens. Two sheets each:
 *   "Dữ liệu"    only the heading row (names the importer recognises) — nothing to delete before uploading
 *   "Hướng dẫn"  per column: required?, accepted values, example
 *
 * Keep the headings in step with ApplicantImport::ALIASES and ImportHighSchools::ALIASES.
 */
class ImportTemplate implements Export, WithMultipleSheets
{
    use Exportable;

    public const TYPES = ['applicants', 'high-schools'];

    /** @var array<string, array{file: string, columns: list<array{0: string, 1: bool, 2: string, 3: string}>, text: list<string>}> */
    private array $definitions;

    public function __construct(private readonly string $type)
    {
        $labels = fn (string $enum) => implode(', ', array_map(fn ($c) => $c->label(), $enum::cases()));

        // column heading, required, accepted values, example
        $this->definitions = [
            'applicants' => [
                'file' => 'mau-nhap-thi-sinh.xlsx',
                'text' => ['B', 'E', 'F'],
                'columns' => [
                    ['Họ và tên', true, 'Họ tên đầy đủ.', 'Nguyễn Văn An'],
                    ['Số điện thoại', true, 'Số di động Việt Nam (0912345678 hoặc +84912345678). Cột để dạng văn bản để giữ số 0 đầu. Bắt buộc nếu không có Email.', '0912345678'],
                    ['Email', false, 'Địa chỉ email hợp lệ. Bắt buộc nếu không có Số điện thoại.', 'an.nguyen@example.com'],
                    ['Giới tính', false, 'Nam, Nữ hoặc Khác.', 'Nam'],
                    ['Ngày sinh', false, 'Dạng văn bản ngày/tháng/năm (31/12/2008) hoặc năm-tháng-ngày (2008-12-31).', '31/12/2008'],
                    ['CCCD', false, '12 chữ số; dùng để kiểm tra trùng.', '001208000123'],
                    ['Tỉnh/thành phố', false, 'Tên tỉnh/thành phố như trong danh mục (Cài đặt → Danh mục).', 'Hà Nội'],
                    ['Trường THPT', false, 'Tên trường đã có trong danh sách trường.', 'THPT Chu Văn An'],
                    ['Năm tốt nghiệp', false, 'Năm dương lịch, 4 chữ số.', '2027'],
                    ['Ngành quan tâm', false, 'Tên ngành hoặc mã tuyển sinh của ngành.', 'Công nghệ thông tin'],
                    ['Ghi chú', false, 'Văn bản tự do.', 'Quan tâm học bổng'],
                ],
            ],
            'high-schools' => [
                'file' => 'mau-nhap-truong-thpt.xlsx',
                'text' => ['A', 'J'],
                'columns' => [
                    ['Mã trường', false, 'Tối đa 20 ký tự. Trùng mã sẽ cập nhật trường đó; để trống thì so khớp theo tên + tỉnh.', 'HN001'],
                    ['Tên trường', true, 'Tên đầy đủ, tối đa 255 ký tự.', 'THPT Chu Văn An'],
                    ['Loại hình', false, $labels(SchoolType::class), 'Công lập'],
                    ['Tỉnh/thành phố', false, 'Tên hoặc mã tỉnh trong danh mục; tên không tồn tại sẽ báo lỗi dòng.', 'Hà Nội'],
                    ['Địa chỉ', false, 'Tối đa 500 ký tự.', '10 Thuỵ Khuê, Tây Hồ'],
                    ['Hạng', false, 'A, B hoặc C ('.implode('; ', array_map(fn ($c) => $c->label(), SchoolTier::cases())).').', 'A'],
                    ['Trạng thái hợp tác', false, $labels(PartnershipStatus::class), 'Chưa liên hệ'],
                    ['Số học sinh khối 12', false, 'Số nguyên không âm.', '450'],
                    ['Số lớp khối 12', false, 'Số nguyên không âm.', '10'],
                    ['Điện thoại', false, 'Dạng văn bản, tối đa 20 ký tự.', '0243xxxxxxx'],
                    ['Email', false, 'Email hợp lệ.', 'vanthu@example.edu.vn'],
                    ['Website', false, 'Tối đa 255 ký tự.', 'https://example.edu.vn'],
                    ['Ghi chú', false, 'Văn bản tự do.', ''],
                ],
            ],
        ];
    }

    public function fileName(): string
    {
        return $this->definitions[$this->type]['file'];
    }

    /** @return list<ImportTemplateSheet> */
    public function sheets(): array
    {
        $definition = $this->definitions[$this->type];
        $columns = $definition['columns'];

        $guide = [
            ['Quy tắc chung', '', '', ''],
            ['Dòng đầu của sheet "Dữ liệu" là tiêu đề cột, giữ nguyên tên cột. Cột không cần có thể xoá hoặc bỏ trống.', '', '', ''],
            ['Tệp .xlsx, .xls hoặc .csv, tối đa 5 MB và 5.000 dòng. Chỉ đọc sheet đầu tiên ("Dữ liệu").', '', '', ''],
            ['', '', '', ''],
            ...array_map(fn (array $c) => [$c[0], $c[1] ? 'Bắt buộc' : 'Tuỳ chọn', $c[2], $c[3]], $columns),
        ];

        return [
            new ImportTemplateSheet('Dữ liệu', array_map(fn (array $c) => $c[0], $columns), [], $definition['text']),
            new ImportTemplateSheet('Hướng dẫn', ['Cột', 'Yêu cầu', 'Giá trị hợp lệ', 'Ví dụ'], $guide),
        ];
    }
}
