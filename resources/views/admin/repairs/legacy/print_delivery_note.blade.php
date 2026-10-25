<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Note &amp; Warranty Certificate - {{ $job->job_no }}</title>
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
        .certificate-box {
            border: 2px solid #0284c7;
            background: #f0f9ff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            text-align: center;
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
        <button class="btn-print" onclick="window.print()">Print Delivery Note (Ctrl+P)</button>
        <button style="padding: 8px 16px; margin-left: 10px; cursor: pointer;" onclick="window.close(); setTimeout(function () { window.location.href = '{{ route('repairs.show', $job->id) }}'; }, 250);">Close Window</button>
    </div>

    <div class="page-container">
        {{-- Dual-Branding Document Header --}}
        @include('admin.partials.document_header', [
            'vendor'       => $job->vendor,
            'documentType' => 'DELIVERY NOTE & WARRANTY CERTIFICATE',
            'documentNo'   => '#' . $job->job_no,
            'date'         => $job->delivered_at ?: now(),
            'status'       => 'Delivered & Handed Over',
            'statusClass'  => 'paid',
        ])

        {{-- 2-Column Info Grid --}}
        <div class="info-grid">
            <div class="info-box">
                <h4>Patient Information</h4>
                <div class="info-row"><span class="label">Patient Name:</span> <span class="val">{{ $job->customer?->lead?->full_name ?? 'Walk-in Customer' }}</span></div>
                <div class="info-row"><span class="label">Patient UID:</span> <span class="val font-monospace">{{ $job->customer?->customer_uid ?? 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Phone:</span> <span class="val">{{ $job->customer?->lead?->phone_number ?? 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Delivery Date:</span> <span class="val">{{ $job->delivered_at ? $job->delivered_at->format('d M Y, h:i A') : date('d M Y') }}</span></div>
            </div>

            <div class="info-box">
                <h4>Device &amp; Handover Details</h4>
                <div class="info-row"><span class="label">Product / Model:</span> <span class="val">{{ $job->device_name }}</span></div>
                <div class="info-row"><span class="label">Original Serial (S/N):</span> <span class="val font-monospace">{{ $job->original_serial_no }}</span></div>
                @if($job->replaced_serial_no)
                    <div class="info-row" style="background: #ecfdf5; padding: 2px 4px; border-radius: 4px;">
                        <span class="label" style="color: #065f46; font-weight: bold;">New Replacement S/N:</span>
                        <span class="val font-monospace" style="color: #059669; font-weight: bold;">{{ $job->replaced_serial_no }}</span>
                    </div>
                @endif
                <div class="info-row"><span class="label">Ear Side:</span> <span class="val text-uppercase">{{ $job->side !== 'na' ? $job->side . ' Ear' : 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Handed Over By:</span> <span class="val">{{ $job->deliveredBy?->name ?? auth()->user()->name }}</span></div>
            </div>
        </div>

        {{-- Service Summary Table --}}
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Service &amp; Repair Work Performed</th>
                    <th style="width: 140px; text-align: right;">Amount (৳)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div style="font-weight: bold;">{{ $job->is_quick_service ? 'Quick Counter Servicing' : ($job->job_type === 'replacement' ? 'Warranty Unit Replacement' : 'Electronic Repair & Acoustic Servicing') }}</div>
                        <div style="color: #64748b; font-size: 12px; margin-top: 2px;">
                            {{ $job->lab_diagnosis ?: ($job->reported_issues ?: 'Acoustic cleaning, component testing, listening check completed.') }}
                        </div>
                    </td>
                    <td style="text-align: right; vertical-align: top;">
                        ৳{{ number_format($job->total_customer_cost, 2) }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: right; font-weight: 500;">Total Paid / Settled:</td>
                    <td style="text-align: right; font-weight: bold; color: #059669;">৳{{ number_format($job->total_customer_paid, 2) }}</td>
                </tr>
                <tr style="background: #f8fafc;">
                    <td style="text-align: right; font-weight: bold;">Net Due Remaining:</td>
                    <td style="text-align: right; font-weight: bold; color: {{ $job->customer_due > 0 ? '#dc2626' : '#059669' }};">
                        ৳{{ number_format($job->customer_due, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Service Warranty Guarantee Certificate --}}
        @if($job->repair_warranty_until && Carbon\Carbon::parse($job->repair_warranty_until)->isFuture())
            <div class="certificate-box">
                <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #0369a1; font-weight: bold;">Official Service Warranty Certificate</div>
                <h3 style="margin: 6px 0; color: #0c4a6e; font-size: 18px;">
                    <i class="fas fa-shield-alt" style="color: #0284c7; margin-right: 6px;"></i>
                    {{ $job->repair_warranty_months }}-Month Post-Warranty Service Guarantee
                </h3>
                <div style="font-size: 13px; color: #334155;">
                    Valid Until: <b style="color: #0284c7; font-size: 15px;">{{ Carbon\Carbon::parse($job->repair_warranty_until)->format('d F Y') }}</b>
                </div>
                <div style="font-size: 11px; color: #64748b; margin-top: 6px;">
                    This hearing aid has undergone precision bench testing. In the unlikely event of recurrence of the same defect within the guarantee period, service will be provided free of charge under our repair warranty.
                </div>
            </div>
        @elseif($job->warranty_status === 'under_product_warranty')
            <div style="border: 1px solid #10b981; background: #ecfdf5; border-radius: 6px; padding: 12px; margin-bottom: 20px; text-align: center;">
                <b style="color: #065f46;">Original Product Manufacturer Warranty Continues Uninterrupted</b>
                <div style="font-size: 11px; color: #047857; margin-top: 2px;">
                    Unit remains covered under full manufacturer warranty as per original purchase date.
                </div>
            </div>
        @endif

        {{-- Handover Signatures --}}
        <div class="sign-grid">
            <div>
                <div class="sign-line">Patient / Recipient Signature</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">I confirm receipt of my device in proper working order</div>
            </div>
            <div>
                <div class="sign-line">Dispensing Specialist / Authorized Staff</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">{{ $job->deliveredBy?->name ?? auth()->user()->name }}</div>
            </div>
        </div>

    </div>

</body>
</html>
