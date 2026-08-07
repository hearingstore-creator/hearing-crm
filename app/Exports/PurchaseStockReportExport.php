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

class PurchaseStockReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected $reportData;
    protected $filters;
    protected $exportType; // 'raw' or 'summary'
    protected $companyName;
    protected $userName;
    protected $vendorName;
    protected $productName;

    /**
     * Constructor
     *
     * @param array $reportData
     * @param array $filters
     * @param string $exportType
     * @param string $companyName
     * @param string $userName
     * @param string $vendorName
     * @param string $productName
     */
    public function __construct($reportData, $filters, $exportType = 'raw', $companyName = 'CRM System', $userName = '', $vendorName = '', $productName = '')
    {
        $this->reportData = $reportData;
        $this->filters = $filters;
        $this->exportType = $exportType;
        $this->companyName = $companyName;
        $this->userName = $userName;
        $this->vendorName = $vendorName;
        $this->productName = $productName;
    }

    /**
     * Get the collection of data
     */
    public function collection()
    {
        $collection = collect($this->reportData);

        // Calculate totals
        $totals = [
            'vendor' => '',
            'product' => '',
            'category' => '',
            'brand' => '',
            'qty_purchased' => 0,
            'purchase_value' => 0,
            'qty_sold' => 0,
            'stock_balance' => 0,
        ];

        foreach ($this->reportData as $row) {
            $totals['qty_purchased'] += $row['qty_purchased'];
            $totals['purchase_value'] += floatval(str_replace(',', '', $row['purchase_value']));
            $totals['qty_sold'] += $row['qty_sold'];
            $totals['stock_balance'] += $row['stock_balance'];
        }

        // Add totals row as the last item
        $collection->push($totals);

        return $collection;
    }

    /**
     * Define headings based on export type
     */
    public function headings(): array
    {
        return [
            ['Vendor', 'Product', 'Category', 'Brand', '', '', 'Qty Purchased', 'Purchase Value', 'Qty Sold', 'Stock Balance'],
        ];
    }

    /**
     * Map data for each row
     */
    public function map($row): array
    {
        // Check if this is the totals row (empty vendor/product/category/brand indicates totals)
        $isTotalsRow = empty($row['vendor']) && empty($row['product']) && empty($row['category']) && empty($row['brand']);

        if ($isTotalsRow) {
            return [
                '', // Vendor
                '', // Product
                '', // Category
                '', // Brand
                '', // Empty column
                'Total:', // Total label
                $row['qty_purchased'],
                number_format($row['purchase_value'], 2),
                $row['qty_sold'],
                $row['stock_balance'],
            ];
        }

        return [
            $row['vendor'] ?? 'N/A',
            $row['product'] ?? 'N/A',
            $row['category'] ?? 'N/A',
            $row['brand'] ?? 'N/A',
            '',
            '',
            $row['qty_purchased'],
            $this->formatNumber($row['purchase_value'] ?? 0),
            $row['qty_sold'],
            $row['stock_balance'],
        ];
    }

    /**
     * Format number from string with commas to float
     */
    private function formatNumber($value)
    {
        if (is_string($value)) {
            return (float) str_replace(',', '', $value);
        }
        return (float) $value;
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
        return [
            'A' => 25, // Vendor
            'B' => 30, // Product
            'C' => 20, // Category
            'D' => 20, // Brand
            'E' => 5,  // Empty
            'F' => 10, // Total label
            'G' => 18, // Qty Purchased
            'H' => 18, // Purchase Value
            'I' => 15, // Qty Sold
            'J' => 18, // Stock Balance
        ];
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Purchase & Stock';
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
                $sheet->setCellValue('A2', 'Purchase & Stock Report');
                $sheet->mergeCells('A2:' . $highestColumn . '2');

                // Row 3: Filters Info (including date range)
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

                // Style data header - blue background with white text
                $sheet->getStyle('A' . $dataHeaderRow . ':J' . $dataHeaderRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '3B82F6'], // Blue
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

                    // Center align numeric columns for regular data
                    $sheet->getStyle('G' . ($dataHeaderRow + 1) . ':J' . $dataLastRow)->applyFromArray([
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
                    $sheet->getStyle('F' . $highestRow)->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        ],
                    ]);
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

        if (!empty($this->filters['vendor_id']) && !empty($this->vendorName)) {
            $filters[] = 'Vendor: ' . $this->vendorName;
        }

        if (!empty($this->filters['product_id']) && !empty($this->productName)) {
            $filters[] = 'Product: ' . $this->productName;
        }

        return !empty($filters) ? implode(' | ', $filters) : 'All Data';
    }
}
