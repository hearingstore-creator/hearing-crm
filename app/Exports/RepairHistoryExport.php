<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RepairHistoryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected Collection $jobs;

    public function __construct(Collection $jobs)
    {
        $this->jobs = $jobs;
    }

    public function collection()
    {
        return $this->jobs;
    }

    public function headings(): array
    {
        return [
            'Job No',
            'Received Date',
            'Delivered / Closed Date',
            'Customer Name',
            'Customer Phone',
            'Product / Model',
            'Original Serial',
            'New Serial (If Replaced)',
            'Outcome',
            'Warranty Status',
            'Customer Paid (BDT)',
            'Supplier Cost (BDT)',
            'Supplier Memo No',
            'Courier Tracking No',
            'Branch',
            'Status',
        ];
    }

    public function map($job): array
    {
        $deliveredDate = $job->delivered_at
            ? $job->delivered_at->format('d M Y')
            : ($job->updated_at ? $job->updated_at->format('d M Y') : '—');

        $isReplaced = !empty($job->replaced_serial_no) && $job->replaced_serial_no !== $job->original_serial_no;

        return [
            $job->job_no,
            $job->created_at ? $job->created_at->format('d M Y') : '—',
            $deliveredDate,
            $job->customer?->lead?->full_name ?? ($job->custom_brand ? 'Walk-in Customer' : 'Customer'),
            $job->customer?->lead?->phone_number ?? '—',
            $job->device_name,
            $job->original_serial_no,
            $isReplaced ? $job->replaced_serial_no : '—',
            $job->outcome,
            ucwords(str_replace('_', ' ', $job->warranty_status ?? '—')),
            (float)($job->total_customer_paid ?? 0),
            (float)($job->total_supplier_cost ?? 0),
            $job->lab_ref_no ?? '—',
            $job->courier_tracking_no ?? '—',
            $job->vendor?->name ?? 'Head Office',
            ucwords(str_replace('_', ' ', $job->current_status ?? '—')),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1E293B'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 15,
            'C' => 18,
            'D' => 22,
            'E' => 16,
            'F' => 24,
            'G' => 18,
            'H' => 18,
            'I' => 22,
            'J' => 20,
            'K' => 18,
            'L' => 18,
            'M' => 18,
            'N' => 20,
            'O' => 18,
            'P' => 16,
        ];
    }
}
