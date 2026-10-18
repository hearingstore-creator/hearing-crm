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

class MembershipLoyaltyReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected $reportData;
    protected $totals;
    protected $filters;
    protected $exportType; // 'full' or 'summary'
    protected $companyName;
    protected $userName;
    protected $tierName;
    protected $vendorName;

    /**
     * Constructor
     */
    public function __construct($reportData, $totals, $filters, $exportType = 'full', $companyName = 'CRM System', $userName = '', $tierName = '', $vendorName = '')
    {
        $this->reportData = $reportData;
        $this->totals = $totals;
        $this->filters = $filters;
        $this->exportType = $exportType;
        $this->companyName = $companyName;
        $this->userName = $userName;
        $this->tierName = $tierName;
        $this->vendorName = $vendorName;
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
                    'label' => 'Total Tiers',
                    'value' => $this->reportData->count(),
                ],
                [
                    'label' => 'Total Members',
                    'value' => $this->totals['totalMembers'],
                ],
                [
                    'label' => 'Total Revenue',
                    'value' => $this->totals['totalRevenue'],
                ],
                [
                    'label' => 'Total Points Issued',
                    'value' => $this->totals['totalPointsIssued'],
                ],
                [
                    'label' => 'Total Points Redeemed',
                    'value' => $this->totals['totalPointsRedeemed'],
                ],
                [
                    'label' => 'Points Balance',
                    'value' => ($this->totals['totalPointsIssued'] ?? 0) - ($this->totals['totalPointsRedeemed'] ?? 0),
                ],
            ]);
        }

        // Full data - add totals row
        $collection = collect($this->reportData);

        // Add totals row as the last item
        $collection->push((object) [
            'tier' => '',
            'members' => $this->totals['totalMembers'],
            'revenue' => $this->totals['totalRevenue'],
            'points_issued' => $this->totals['totalPointsIssued'],
            'points_redeemed' => $this->totals['totalPointsRedeemed'],
            'points_balance' => ($this->totals['totalPointsIssued'] ?? 0) - ($this->totals['totalPointsRedeemed'] ?? 0),
        ]);

        return $collection;
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
            ['Tier', '', 'Members', 'Revenue', 'Points Issued', 'Points Redeemed', 'Points Balance'],
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
            if (in_array($label, ['Total Revenue'])) {
                $value = number_format($value, 2);
            } elseif (in_array($label, ['Total Points Issued', 'Total Points Redeemed', 'Points Balance'])) {
                $value = number_format($value, 0);
            } elseif (in_array($label, ['Total Tiers', 'Total Members'])) {
                $value = (string) $value;
            }

            return [$label, $value];
        }

        // Full data export
        // Check if this is the totals row
        $isTotalsRow = empty($row->tier);

        if ($isTotalsRow) {
            return [
                '', // Tier
                '', // Empty
                'Total:', // Total label
                $row->revenue,
                $row->points_issued,
                $row->points_redeemed,
                $row->points_balance,
            ];
        }

        return [
            $row->tier ?? 'N/A',
            '',
            $row->members,
            number_format($row->revenue, 2),
            number_format($row->points_issued, 0),
            number_format($row->points_redeemed, 0),
            number_format($row->points_balance, 0),
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
            'A' => 25, // Tier
            'B' => 5,  // Empty
            'C' => 12, // Members
            'D' => 15, // Revenue
            'E' => 18, // Points Issued
            'F' => 18, // Points Redeemed
            'G' => 18, // Points Balance
        ];
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Membership & Loyalty';
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
                $reportName = $this->exportType === 'summary' ? 'Membership & Loyalty Report - Summary' : 'Membership & Loyalty Report';
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
                        $sheet->getStyle('C' . ($dataHeaderRow + 1) . ':G' . $dataLastRow)->applyFromArray([
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            ],
                        ]);

                        // Center align members column
                        $sheet->getStyle('C' . ($dataHeaderRow + 1) . ':C' . $dataLastRow)->applyFromArray([
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
                        $sheet->getStyle('C' . $highestRow)->applyFromArray([
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

        if (!empty($this->filters['tier_id']) && !empty($this->tierName)) {
            $filters[] = 'Tier: ' . $this->tierName;
        }

        if (!empty($this->filters['vendor_id']) && !empty($this->vendorName)) {
            $filters[] = 'Branch: ' . $this->vendorName;
        }

        if (!empty($this->filters['points_status'])) {
            $filters[] = 'Points Status: ' . ucfirst($this->filters['points_status']);
        }

        return !empty($filters) ? implode(' | ', $filters) : 'All Data';
    }
}
