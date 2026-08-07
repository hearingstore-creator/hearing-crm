<?php

namespace App\Exports;

use App\Models\Lead;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function collection()
    {
        return Lead::all();
    }

    /**
     * Custom headings for the Excel file
     */
    public function headings(): array
    {
        return [
            'Lead UID',
            'Full Name',
            'Email Address',
            'Age',
            'Gender',
            'Phone Number',
            'Contact Person Name',
            'Relation',
            'Contact Person Phone',
            'Inquiry For',
            'Interest Brand/Product',
            'Hearing Loss',
            'Approximate Budget',
            'Referred By',
            'Notes',
            'Loss Reason',
            'Loss To Whom',
            'Source of Entry',
            'Lead Type',
            'Lead Status',
            'Created At',
            'Updated At',
        ];
    }

    /**
     * Map data for each row
     */
    public function map($lead): array
    {
        return [
            $lead->lead_uid,
            $lead->full_name,
            $lead->email,
            $lead->age,
            ucfirst($lead->gender), // Capitalize first letter
            $lead->phone_number,
            $lead->contact_person_name,
            $lead->relation,
            $lead->contact_person_phone,
            $lead->inquiry_for,
            $lead->interest_brand_product,
            $lead->hearing_loss,
            $lead->approx_budget ? '৳' . number_format($lead->approx_budget, 2) : '', // Format budget
            $lead->ref_by,
            $lead->notes,
            $lead->loss_reason,
            $lead->loss_to_whom,
            $lead->source_of_entry,
            $lead->lead_type,
            $lead->lead_status,
            \Carbon\Carbon::parse($lead->created_at)->format('Y-m-d H:i:s'),
            \Carbon\Carbon::parse($lead->updated_at)->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Style the header row
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row (header)
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'], // Light gray background
                ],
            ],
        ];
    }

    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 20, // Lead UID
            'B' => 25, // Full Name
            'C' => 30, // Email
            'D' => 8,  // Age
            'E' => 12, // Gender
            'F' => 18, // Phone Number
            'G' => 25, // Contact Person Name
            'H' => 15, // Relation
            'I' => 18, // Contact Person Phone
            'J' => 20, // Inquiry For
            'K' => 25, // Interest Brand/Product
            'L' => 15, // Hearing Loss
            'M' => 18, // Approx Budget
            'N' => 20, // Ref By
            'O' => 35, // Notes
            'P' => 20, // Loss Reason
            'Q' => 20, // Loss To Whom
            'R' => 20, // Source of Entry
            'S' => 15, // Lead Type
            'T' => 15, // Lead Status
            'U' => 18, // Created At
            'V' => 18, // Updated At
        ];
    }
}
