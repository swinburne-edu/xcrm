<?php

namespace App\Domain\Report\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The funnel table of one dimension as an Excel sheet (rows from FunnelReport::breakdown).
 */
class BreakdownExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<array{label: string, total: int, contacted: int, admitted: int, enrolled: int, lost: int, rate: float}>  $rows
     */
    public function __construct(private readonly string $dimensionLabel, private readonly array $rows) {}

    /** @return list<string> */
    public function headings(): array
    {
        return [$this->dimensionLabel, 'Hồ sơ', 'Đã liên hệ', 'Trúng tuyển', 'Nhập học', 'Thất bại', 'Tỷ lệ nhập học (%)'];
    }

    /** @return list<list<int|float|string>> */
    public function array(): array
    {
        return array_map(fn (array $r) => [$r['label'], $r['total'], $r['contacted'], $r['admitted'], $r['enrolled'], $r['lost'], $r['rate']], $this->rows);
    }
}
