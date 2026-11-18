<?php

namespace App\Domain\HighSchool\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads the first sheet as rows keyed by the slugged heading ("Mã trường" → ma_truong).
 */
class HighSchoolSheet implements ToArray, WithHeadingRow
{
    /** @var list<array<string, mixed>> */
    public array $rows = [];

    /** @param  array<int, array<string, mixed>>  $array */
    public function array(array $array): void
    {
        $this->rows = array_values($array);
    }
}
