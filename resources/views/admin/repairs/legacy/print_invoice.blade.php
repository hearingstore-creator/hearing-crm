<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Invoice - {{ $job->job_no }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoice.css') }}">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
            background: #f8fafc;
            margin: 0;
            padding: 20px;
            font-size: 13px;
        }
        .page-container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
            background: #f8fafc;
        }
        .info-box h4 {
            margin: 0 0 8px 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .info-row .label {
            color: #64748b;
        }
        .info-row .val {
            font-weight: 600;
            color: #0f172a;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table-custom th, .table-custom td {
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            text-align: left;
        }
        .table-custom th {
            background: #f1f5f9;
            font-size: 11px;
            text-transform: uppercase;
            color: #475569;
        }
        .barcode-box {
            text-align: center;
            padding: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .guarantee-box {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .terms-box {
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
            border-top: 1px dashed #cbd5e1;
            padding-top: 12px;
            margin-top: 20px;
        }
        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
            text-align: center;
        }
        .sign-line {
            border-top: 1px solid #334155;
            padding-top: 5px;
            font-weight: 600;
            font-size: 12px;
        }
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .page-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Invoice (Ctrl+P)</button>
        <button style="padding: 8px 16px; margin-left: 10px; cursor: pointer;" onclick="window.close()">Close Window</button>
    </div>

    <div class="page-container">
        {{-- Dual-Branding Document Header --}}
        @include('admin.partials.document_header', [
            'vendor'       => $job->vendor,
            'documentType' => 'REPAIR SERVICE TAX INVOICE',
            'documentNo'   => 'INV-' . $job->job_no,
            'date'         => $job->delivered_at ?: now(),
            'status'       => $job->customer_due > 0 ? 'DUE: ৳' . number_format($job->customer_due, 2) : 'PAID IN FULL',
            'statusClass'  => $job->customer_due > 0 ? 'partial' : 'paid',
        ])

        {{-- Center Barcode --}}
        <div class="barcode-box">
            @php
                $cleanNo = preg_replace('/[^A-Za-z0-9\-]/', '', $job->job_no);
            @endphp
            <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($cleanNo, 'C128', 1.4, 34) }}" alt="Barcode" style="max-height: 34px; width: auto; display: inline-block;">
            <div style="font-family: monospace; font-size: 12px; letter-spacing: 2px; margin-top: 4px; font-weight: bold;">
                *{{ $cleanNo }}*
            </div>
        </div>

        {{-- Patient & Device Meta Grid --}}
        <div class="info-grid">
            <div class="info-box">
                <h4>Bill To / Patient Details</h4>
                <div class="info-row">
                    <span class="label">Patient Name:</span>
                    <span class="val">{{ $job->customer?->lead?->full_name ?? 'Walk-in Patient' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Patient UID:</span>
                    <span class="val">{{ $job->customer?->customer_uid ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Contact Phone:</span>
                    <span class="val">{{ $job->customer?->lead?->phone_number ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Address:</span>
                    <span class="val" style="max-width: 220px; text-align: right;">{{ $job->customer?->lead?->address ?? 'N/A' }}</span>
                </div>
            </div>

            <div class="info-box">
                <h4>Job &amp; Device Reference</h4>
                <div class="info-row">
                    <span class="label">Job Card Number:</span>
                    <span class="val" style="color: #2563eb;">#{{ $job->job_no }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Device Name / Model:</span>
                    <span class="val">{{ $job->device_name }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Serial Number:</span>
                    <span class="val font-monospace">{{ $job->original_serial_no }}</span>
                </div>
                @if($job->replaced_serial_no)
                    <div class="info-row">
                        <span class="label" style="color: #059669; font-weight: bold;">Replaced With S/N:</span>
                        <span class="val font-monospace" style="color: #059669;">{{ $job->replaced_serial_no }}</span>
                    </div>
                @endif
                @if($job->lab_ref_no)
                    <div class="info-row">
                        <span class="label">Supplier Cash Memo:</span>
                        <span class="val font-monospace">{{ $job->lab_ref_no }}</span>
                    </div>
                @endif
                <div class="info-row">
                    <span class="label">Ear Side:</span>
                    <span class="val" style="text-transform: uppercase;">{{ $job->side !== 'na' ? $job->side . ' Ear' : 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Warranty Category:</span>
                    <span class="val" style="text-transform: uppercase;">{{ str_replace('_', ' ', $job->warranty_status) }}</span>
                </div>
            </div>
        </div>

        {{-- Itemized Charges Table --}}
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 50%;">Description / Item Name</th>
                    <th style="width: 15%; text-align: center;">Type</th>
                    <th style="width: 10%; text-align: center;">Qty</th>
                    <th style="width: 10%; text-align: right;">Rate (৳)</th>
                    <th style="width: 10%; text-align: right;">Total (৳)</th>
                </tr>
            </thead>
            <tbody>
                @php $sl = 1; @endphp

                @if($job->inspection_fee > 0)
                    <tr>
                        <td>{{ $sl++ }}</td>
                        <td>
                            <b>Clinic Inspection &amp; Diagnosis Fee</b>
                            <div style="font-size: 11px; color: #64748b;">Initial evaluation charge</div>
                        </td>
                        <td style="text-align: center;"><span style="font-size: 11px; text-transform: uppercase; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">Service</span></td>
                        <td style="text-align: center;">1.00</td>
                        <td style="text-align: right;">{{ number_format($job->inspection_fee, 2) }}</td>
                        <td style="text-align: right; font-weight: bold;">{{ number_format($job->inspection_fee, 2) }}</td>
                    </tr>
                @endif

                @forelse($job->items as $item)
                    <tr>
                        <td>{{ $sl++ }}</td>
                        <td>
                            <b>{{ $item->item_name }}</b>
                            @if($item->is_warranty_covered)
                                <span style="font-size: 10px; background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 4px; font-weight: bold; margin-left: 6px;">WARRANTY COVERED (৳0.00)</span>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <span style="font-size: 11px; text-transform: uppercase; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                                {{ str_replace('_', ' ', $item->item_type) }}
                            </span>
                        </td>
                        <td style="text-align: center;">{{ number_format($item->qty, 2) }}</td>
                        <td style="text-align: right;">{{ number_format($item->customer_unit_price, 2) }}</td>
                        <td style="text-align: right; font-weight: bold;">{{ number_format($item->customer_total, 2) }}</td>
                    </tr>
                @empty
                    @if($job->inspection_fee <= 0)
                        @if($job->total_customer_cost > 0)
                            <tr>
                                <td>{{ $sl++ }}</td>
                                <td><b>Hearing Aid Servicing &amp; Repair Charge</b></td>
                                <td style="text-align: center;"><span style="font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">Service</span></td>
                                <td style="text-align: center;">1.00</td>
                                <td style="text-align: right;">{{ number_format($job->total_customer_cost, 2) }}</td>
                                <td style="text-align: right; font-weight: bold;">{{ number_format($job->total_customer_cost, 2) }}</td>
                            </tr>
                        @else
                            <tr>
                                <td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">
                                    <b>100% Free Service under Official Manufacturer Warranty. No charges billed.</b>
                                </td>
                            </tr>
                        @endif
                    @endif
                @endforelse
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc;">
                    <td colspan="5" style="text-align: right; font-weight: bold;">Total Bill Amount:</td>
                    <td style="text-align: right; font-weight: bold; font-size: 14px;">৳{{ number_format($job->total_customer_cost, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: right; font-weight: bold; color: #16a34a;">Total Amount Paid / Settled:</td>
                    <td style="text-align: right; font-weight: bold; color: #16a34a; font-size: 14px;">৳{{ number_format($job->total_customer_paid, 2) }}</td>
                </tr>
                <tr style="{{ $job->customer_due > 0 ? 'background: #fef2f2; color: #dc2626;' : 'background: #f0fdf4; color: #166534;' }}">
                    <td colspan="5" style="text-align: right; font-weight: bold; font-size: 14px;">Net Balance Due:</td>
                    <td style="text-align: right; font-weight: bold; font-size: 16px;">৳{{ number_format($job->customer_due, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        {{-- Payment History Log --}}
        @if($job->customerLedgers && $job->customerLedgers->where('credit', '>', 0)->count() > 0)
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 12px; text-transform: uppercase; color: #64748b; margin-bottom: 8px;">Payment Transaction Records</h4>
                <table class="table-custom" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transaction Description</th>
                            <th style="text-align: right;">Amount Paid (৳)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($job->customerLedgers->where('credit', '>', 0) as $txn)
                            <tr>
                                <td>{{ Carbon\Carbon::parse($txn->date)->format('d M Y') }}</td>
                                <td>{{ $txn->details }}</td>
                                <td style="text-align: right; font-weight: bold; color: #16a34a;">৳{{ number_format($txn->credit, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Post-Warranty Guarantee Banner --}}
        @if($job->repair_warranty_until)
            <div class="guarantee-box">
                <div style="font-size: 24px; margin-right: 12px; color: #16a34a;">🛡️</div>
                <div>
                    <b style="color: #166534; font-size: 13px;">POST-REPAIR SERVICE GUARANTEE INCLUDED</b>
                    <div style="font-size: 12px; color: #1e293b;">
                        This repair is protected by a <b>{{ $job->repair_warranty_months }} Months Service Guarantee</b> valid until <b>{{ Carbon\Carbon::parse($job->repair_warranty_until)->format('d F Y') }}</b> against the same diagnosed defect.
                    </div>
                </div>
            </div>
        @endif

        {{-- Terms & Notice --}}
        <div class="terms-box">
            <b>Terms &amp; Conditions:</b>
            <ol style="padding-left: 18px; margin: 4px 0 0 0;">
                <li>Please retain this invoice and your job card for any future service inquiries or guarantee claims.</li>
                <li>Guarantee covers the specific repair work and replaced components mentioned in this bill only. Physical impact, liquid damage, battery leakage, or tampering by unauthorized third parties voids all warranties.</li>
                <li>Hearing aid electronic components require regular maintenance and dehumidification to prevent moisture damage.</li>
            </ol>
        </div>

        {{-- Signature Blocks --}}
        <div class="sign-grid">
            <div>
                <div style="height: 45px;"></div>
                <div class="sign-line">Customer / Recipient Signature</div>
            </div>
            <div>
                <div style="height: 45px;"></div>
                <div class="sign-line">Authorized Clinic Officer / Cashier</div>
            </div>
        </div>

    </div>

</body>
</html>
