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

class DoctorReferralReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected $reportData;
    protected $filters;
    protected $exportType; // 'full' or 'summary'
    protected $companyName;
    protected $userName;
    protected $doctorName;
    protected $vendorName;

    /**
     * Constructor
     *
     * @param array $reportData
     * @param array $filters
     * @param string $exportType
     * @param string $companyName
     * @param string $userName
     * @param string $doctorName
     * @param string $vendorName
     */
    public function __construct($reportData, $filters, $exportType = 'full', $companyName = 'CRM System', $userName = '', $doctorName = '', $vendorName = '')
    {
        $this->reportData = $reportData;
        $this->filters = $filters;
        $this->exportType = $exportType;
        $this->companyName = $companyName;
        $this->userName = $userName;
        $this->doctorName = $doctorName;
        $this->vendorName = $vendorName;
    }

    /**
     * Get the collection of data
     */
    public function collection()
    {
        $collection = collect($this->reportData);

        if ($this->exportType === 'summary') {
            // Return only summary data
            $summary = $this->calculateSummary();
            return collect([
                [
                    'label' => 'Total Doctors',
                    'value' => $summary['total_doctors'],
                ],
                [
                    'label' => 'Total Sales Value',
                    'value' => $summary['total_sales_value'],
                ],
                [
                    'label' => 'Total Commission',
                    'value' => $summary['total_commission'],
                ],
                [
                    'label' => 'Total Paid',
                    'value' => $summary['total_paid'],
                ],
                [
                    'label' => 'Total Pending',
                    'value' => $summary['total_pending'],
                ],
                [
                    'label' => 'Average Commission %',
                    'value' => $summary['avg_commission_percent'],
                ],
            ]);
        }

        // Full data - add totals row
        $totals = [
            'doctor_id' => '',
            'doctor_name' => '',
            'qualification' => '',
            'sales_value' => 0,
            'commission_percent' => 0,
            'commission_amount' => 0,
            'sale_count' => 0,
            'paid' => 0,
            'pending' => 0,
        ];

        foreach ($this->reportData as $row) {
            $totals['sales_value'] += $row['sales_value'];
            $totals['commission_amount'] += $row['commission_amount'];
            $totals['sale_count'] += $row['sale_count'];
            $totals['paid'] += $row['paid'];
            $totals['pending'] += $row['pending'];
        }

        // Calculate average commission percent
        if ($totals['sales_value'] > 0) {
            $totals['commission_percent'] = round(($totals['commission_amount'] / $totals['sales_value']) * 100, 2);
        }

        // Add totals row as the last item
        $collection->push($totals);

        return $collection;
    }

    /**
     * Calculate summary statistics
     */
    private function calculateSummary(): array
    {
        $totalDoctors = count($this->reportData);
        $totalSalesValue = 0;
        $totalCommission = 0;
        $totalPaid = 0;
        $totalPending = 0;

        foreach ($this->reportData as $row) {
            $totalSalesValue += $row['sales_value'];
            $totalCommission += $row['commission_amount'];
            $totalPaid += $row['paid'];
            $totalPending += $row['pending'];
        }

        $avgCommissionPercent = $totalSalesValue > 0 ? round(($totalCommission / $totalSalesValue) * 100, 2) : 0;

        return [
            'total_doctors' => $totalDoctors,
            'total_sales_value' => $totalSalesValue,
            'total_commission' => $totalCommission,
            'total_paid' => $totalPaid,
            'total_pending' => $totalPending,
            'avg_commission_percent' => $avgCommissionPercent . '%',
        ];
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
            ['Doctor', 'Qualification', '', '', 'Sales Value', 'Commission %', 'Commission Amount', 'Paid', 'Pending'],
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
            if (in_array($label, ['Total Sales Value', 'Total Commission', 'Total Paid', 'Total Pending'])) {
                $value = number_format($value, 2);
            } elseif ($label === 'Total Doctors') {
                $value = (string) $value;
            }

            return [$label, $value];
        }

        // Full data export
        // Check if this is the totals row
        $isTotalsRow = empty($row['doctor_name']) && empty($row['qualification']);

        if ($isTotalsRow) {
            return [
                '', // Doctor
                '', // Qualification
                '', // Empty
                'Total:', // Total label
                $row['sales_value'],
                $row['commission_percent'] . '%',
                number_format($row['commission_amount'], 2),
                number_format($row['paid'], 2),
                number_format($row['pending'], 2),
            ];
        }

        return [
            $row['doctor_name'] ?? 'N/A',
            $row['qualification'] ?? 'N/A',
            '',
            '',
            number_format($row['sales_value'], 2),
            $row['commission_percent'] . '%',
            number_format($row['commission_amount'], 2),
            number_format($row['paid'], 2),
            number_format($row['pending'], 2),
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
            'A' => 25, // Doctor
            'B' => 20, // Qualification
            'C' => 5,  // Empty
            'D' => 10, // Total label
            'E' => 15, // Sales Value
            'F' => 15, // Commission %
            'G' => 20, // Commission Amount
            'H' => 15, // Paid
            'I' => 15, // Pending
        ];
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Doctor & Referral';
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
                $reportName = $this->exportType === 'summary' ? 'Doctor & Referral Report - Summary' : 'Doctor & Referral Report';
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
                    $sheet->getStyle('A' . $dataHeaderRow . ':' . $highestColumn . $dataHeaderRow)->applyFromArray([
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
                        // Style regular data rows (excluding totals row)
                        $dataLastRow = $highestRow - 1;
                        $sheet->getStyle('A' . ($dataHeaderRow + 1) . ':' . $highestColumn . $dataLastRow)->applyFromArray([
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

                        // Right align numeric columns for regular data
                        $sheet->getStyle('E' . ($dataHeaderRow + 1) . ':I' . $dataLastRow)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            ],
                        ]);

                        // Center align commission percent
                        $sheet->getStyle('F' . ($dataHeaderRow + 1) . ':F' . $dataLastRow)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                            ],
                        ]);

                        // Style totals row (last row)
                        $sheet->getStyle('A' . $highestRow . ':' . $highestColumn . $highestRow)->applyFromArray([
                            'font' => [
                                'bold' => true,
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F3F4F6'],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => 'D1D5DB'],
                                ],
                            ],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                        ]);

                        // Right align "Total:" label
                        $sheet->getStyle('D' . $highestRow)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_RIGHT,
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

        if (!empty($this->filters['doctor_id']) && !empty($this->doctorName)) {
            $filters[] = 'Doctor: ' . $this->doctorName;
        }

        if (!empty($this->filters['vendor_id']) && !empty($this->vendorName)) {
            $filters[] = 'Branch: ' . $this->vendorName;
        }

        if (!empty($this->filters['payment_status']) && $this->filters['payment_status'] != 'all') {
            $filters[] = 'Payment Status: ' . ucfirst($this->filters['payment_status']);
        }

        return !empty($filters) ? implode(' | ', $filters) : 'All Data';
    }
}
