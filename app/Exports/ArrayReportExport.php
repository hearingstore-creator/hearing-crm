<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** A plain sheet: a title, a heading row and rows. Used by reports that only need a table in Excel. */
class ArrayReportExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(private string $title, private array $headings, private array $rows)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }
}
