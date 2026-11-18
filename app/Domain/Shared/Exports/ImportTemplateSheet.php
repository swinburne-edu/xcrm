<?php

namespace App\Domain\Shared\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One sheet of an import template: bold heading row, optional example rows, and text-formatted columns
 * (so phone numbers, national IDs and codes keep their leading zeros).
 */
class ImportTemplateSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<int|string>>  $rows
     * @param  list<string>  $textColumns  column letters formatted as text
     */
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows = [],
        private readonly array $textColumns = [],
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->headings;
    }

    /** @return list<list<int|string>> */
    public function array(): array
    {
        return $this->rows;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return array_fill_keys($this->textColumns, NumberFormat::FORMAT_TEXT);
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
