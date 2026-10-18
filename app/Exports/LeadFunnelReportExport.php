<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LeadFunnelReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected $reportData;
    protected $filters;
    protected $totals;
    protected $exportType; // 'full' or 'summary'
    protected $companyName;
    protected $userName;
    protected $sourceName;
    protected $staffName;
    protected $vendorName;
    protected $stageName;

    /**
     * Constructor
     */
    public function __construct($reportData, $filters, $totals, $exportType = 'full', $companyName = 'CRM System', $userName = '', $sourceName = '', $staffName = '', $vendorName = '', $stageName = '')
    {
        $this->reportData = $reportData;
        $this->filters = $filters;
        $this->totals = $totals;
        $this->exportType = $exportType;
        $this->companyName = $companyName;
        $this->userName = $userName;
        $this->sourceName = $sourceName;
        $this->staffName = $staffName;
        $this->vendorName = $vendorName;
        $this->stageName = $stageName;
    }

    /**
     * Get the collection of data
     */
    public function collection()
    {
        if ($this->exportType === 'summary') {
            // Return only summary data
            return collect([
                [
                    'label' => 'Total Leads',
                    'value' => $this->totals['totalLeads'],
                ],
                [
                    'label' => 'Won Leads',
                    'value' => $this->totals['wonLeads'],
                ],
                [
                    'label' => 'Lost Leads',
                    'value' => $this->totals['lostLeads'],
                ],
                [
                    'label' => 'Conversion Rate',
                    'value' => $this->totals['overallConversionRate'] . '%',
                ],
            ]);
        }

        // Full data
        return collect($this->reportData);
    }

    /**
     * Define headings
     */
    public function headings(): array
    {
        if ($this->exportType === 'summary') {
            return [
                ['Metric', 'Value'],
            ];
        }

        return [
            ['Stage', 'Count', 'Conversion %', 'Overall Conversion %', 'Avg Time (Days)'],
        ];
    }

    /**
     * Map data for each row
     */
    public function map($row): array
    {
        if ($this->exportType === 'summary') {
            // Summary export
            $label = $row['label'];
            $value = $row['value'];

            // Format values based on label
            if (in_array($label, ['Total Leads', 'Won Leads', 'Lost Leads'])) {
                $value = number_format($value);
            }

            return [$label, $value];
        }

        // Full data export
        return [
            $row->stage ?? 'N/A',
            $row->count ?? 0,
            number_format($row->conversion_percent, 2) . '%',
            number_format($row->overall_conversion, 2) . '%',
            $row->avg_time_days ? number_format($row->avg_time_days, 1) . ' days' : '-',
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [];
    }

    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        if ($this->exportType === 'summary') {
            return [
                'A' => 30, // Metric
                'B' => 20, // Value
            ];
        }

        return [
            'A' => 25, // Stage
            'B' => 12, // Count
            'C' => 18, // Conversion %
            'D' => 20, // Overall Conversion %
            'E' => 18, // Avg Time (Days)
        ];
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Lead Funnel & Conversion';
    }

    /**
     * Register events for additional customization
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Add company info and metadata at the top
                $sheet->insertNewRowBefore(1, 4);

                // Row 1: Company Name
                $sheet->setCellValue('A1', $this->companyName);
                $sheet->mergeCells('A1:' . $highestColumn . '1');

                // Row 2: Report Name
                $reportName = $this->exportType === 'summary' ? 'Lead Funnel & Conversion Report - Summary' : 'Lead Funnel & Conversion Report';
                $sheet->setCellValue('A2', $reportName);
                $sheet->mergeCells('A2:' . $highestColumn . '2');

                // Row 3: Filters Info
                $filterInfo = $this->buildFilterInfo();
                $sheet->setCellValue('A3', $filterInfo);
                $sheet->mergeCells('A3:' . $highestColumn . '3');

                // Row 4: Generated Info
                $generatedInfo = 'Generated: ' . now()->format('Y-m-d H:i:s') . ' | By: ' . $this->userName;
                $sheet->setCellValue('A4', $generatedInfo);
                $sheet->mergeCells('A4:' . $highestColumn . '4');

                // Apply styles to header rows
                $sheet->getStyle('A1:A4')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Style company name
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'size' => 18,
                        'color' => ['rgb' => '1F2937'],
                    ],
                ]);

                // Style report name
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'size' => 14,
                        'color' => ['rgb' => '374151'],
                    ],
                ]);

                // Style filters info
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'color' => ['rgb' => '6B7280'],
                        'italic' => true,
                    ],
                ]);

                // Style generated info
                $sheet->getStyle('A4')->applyFromArray([
                    'font' => [
                        'size' => 9,
                        'color' => ['rgb' => '9CA3AF'],
                    ],
                ]);

                // Data header is at row 5 (headings start at row 1, then we insert 4 rows, so row 1 becomes row 5)
                $dataHeaderRow = 5;

                if ($this->exportType === 'summary') {
                    // Summary style - cleaner, simpler format
                    $sheet->getStyle('A' . $dataHeaderRow . ':B' . $dataHeaderRow)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 12,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '10B981'], // Green for summary
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                    ]);

                    // Style data rows
                    if ($highestRow > $dataHeaderRow) {
                        $sheet->getStyle('A' . ($dataHeaderRow + 1) . ':B' . $highestRow)->applyFromArray([
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => 'E5E7EB'],
                                ],
                            ],
                            'alignment' => [
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                        ]);

                        // Bold the metric labels (column A)
                        $sheet->getStyle('A' . ($dataHeaderRow + 1) . ':A' . $highestRow)->applyFromArray([
                            'font' => [
                                'bold' => true,
                            ],
                        ]);

                        // Right align values (column B)
                        $sheet->getStyle('B' . ($dataHeaderRow + 1) . ':B' . $highestRow)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            ],
                        ]);
                    }
                } else {
                    // Full data style
                    $sheet->getStyle('A' . $dataHeaderRow . ':E' . $dataHeaderRow)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 11,
                            'color' => ['rgb' => 'FFFFFF'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '3B82F6'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'D1D5DB'],
                            ],
                        ],
                    ]);

                    // Apply borders to data rows
                    if ($highestRow > $dataHeaderRow) {
                        $sheet->getStyle('A' . ($dataHeaderRow + 1) . ':E' . $highestRow)->applyFromArray([
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => 'E5E7EB'],
                                ],
                            ],
                            'alignment' => [
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                        ]);

                        // Center align numeric columns
                        $sheet->getStyle('B' . ($dataHeaderRow + 1) . ':E' . $highestRow)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                            ],
                        ]);
                    }
                }

                // Set row heights
                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(3)->setRowHeight(18);
                $sheet->getRowDimension(4)->setRowHeight(16);
                $sheet->getRowDimension($dataHeaderRow)->setRowHeight(20);

                // Auto-height for data rows
                foreach (range($dataHeaderRow + 1, $highestRow) as $row) {
                    $sheet->getRowDimension($row)->setRowHeight(-1);
                }
            },
        ];
    }

    /**
     * Build filter information string
     */
    private function buildFilterInfo(): string
    {
        $filters = [];

        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $filters[] = 'Date: ' . $this->filters['start_date'] . ' to ' . $this->filters['end_date'];
        }

        if (!empty($this->filters['source_of_entry']) && !empty($this->sourceName)) {
            $filters[] = 'Source: ' . $this->sourceName;
        }

        if (!empty($this->filters['staff_id']) && !empty($this->staffName)) {
            $filters[] = 'Staff: ' . $this->staffName;
        }

        if (!empty($this->filters['vendor_id']) && !empty($this->vendorName)) {
            $filters[] = 'Branch: ' . $this->vendorName;
        }

        if (!empty($this->filters['stage']) && !empty($this->stageName)) {
            $filters[] = 'Stage: ' . $this->stageName;
        }

        return !empty($filters) ? implode(' | ', $filters) : 'All Data';
    }
}
