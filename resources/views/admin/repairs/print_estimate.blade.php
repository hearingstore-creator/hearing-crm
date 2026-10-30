<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Cost Estimate - {{ $job->job_no }}</title>
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
        .info-row .label { color: #64748b; }
        .info-row .val { font-weight: 600; color: #0f172a; }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table-custom th, .table-custom td {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            text-align: left;
        }
        .table-custom th {
            background: #f1f5f9;
            font-size: 11px;
            text-transform: uppercase;
            color: #475569;
        }
        .teardown-box {
            border: 1px solid #fecaca;
            background: #fff5f5;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 50px;
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
            body { background: white; padding: 0; }
            .page-container { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Print Quotation / Estimate (Ctrl+P)</button>
        <button style="padding: 8px 16px; margin-left: 10px; cursor: pointer;" onclick="window.close(); setTimeout(function () { window.location.href = '{{ route('repairs.show', $job->id) }}'; }, 250);">Close Window</button>
    </div>

    <div class="page-container">
        {{-- Document Header --}}
        @include('admin.partials.document_header', [
            'vendor'       => $job->vendor,
            'documentType' => 'SERVICE COST ESTIMATE & QUOTATION',
            'documentNo'   => '#' . $job->job_no,
            'date'         => now(),
            'status'       => 'Pending Patient Approval',
            'statusClass'  => 'pending',
        ])

        {{-- Info Grid --}}
        <div class="info-grid">
            <div class="info-box">
                <h4>Patient Information</h4>
                <div class="info-row"><span class="label">Patient Name:</span> <span class="val">{{ $job->customer?->lead?->full_name ?? 'Walk-in Customer' }}</span></div>
                <div class="info-row"><span class="label">Patient UID:</span> <span class="val font-monospace">{{ $job->customer?->customer_uid ?? 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Phone:</span> <span class="val">{{ $job->customer?->lead?->phone_number ?? '-' }}</span></div>
                <div class="info-row"><span class="label">Branch:</span> <span class="val">{{ $job->vendor?->name ?? 'Main Clinic' }}</span></div>
            </div>

            <div class="info-box">
                <h4>Device Information</h4>
                <div class="info-row"><span class="label">Product / Model:</span> <span class="val">{{ $job->product_display_name }}</span></div>
                <div class="info-row"><span class="label">Serial Number:</span> <span class="val font-monospace">{{ $job->original_serial_no }}</span></div>
                <div class="info-row"><span class="label">Warranty Status:</span> 
                    <span class="val" style="color: {{ $job->warranty_status === 'void' ? '#dc2626' : '#475569' }};">
                        {{ ucwords(str_replace('_', ' ', $job->warranty_status)) }}
                    </span>
                </div>
                <div class="info-row"><span class="label">Quotation Date:</span> <span class="val">{{ date('d M Y') }}</span></div>
            </div>
        </div>

        {{-- Teardown Findings (if void or inspected) --}}
        @if($job->void_reason || $job->warranty_status === 'void')
        <div class="teardown-box">
            <h4 style="margin: 0 0 6px 0; font-size: 12px; color: #b91c1c; text-transform: uppercase;">
                <i class="fas fa-exclamation-triangle mr-1"></i> Technical Inspection &amp; Teardown Findings
            </h4>
            <div style="font-size: 13px; color: #991b1b; line-height: 1.5;">
                {{ $job->void_reason ?: 'Internal examination revealed physical damage or moisture ingress, exempting repair from standard warranty.' }}
            </div>
        </div>
        @endif

        {{-- Estimate Breakdown Table --}}
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="width: 80px; text-align: center;">Qty</th>
                    <th style="width: 140px; text-align: right;">Estimated Amount</th>
                </tr>
            </thead>
            <tbody>
                @if($job->items->count() > 0)
                    @foreach($job->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->product ? $item->product->product_name : $item->item_name }}</strong>
                            <div style="font-size: 11px; color: #64748b;">{{ $item->item_type === 'part' ? 'Spare Part Replacement' : 'Service Labor' }}</div>
                        </td>
                        <td style="text-align: center;">{{ $item->qty }}</td>
                        <td style="text-align: right; font-weight: 600;">৳{{ number_format($item->customer_price * $item->qty, 2) }}</td>
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td>
                            <strong>Technical Service, Calibration &amp; Component Repair</strong>
                            <div style="font-size: 11px; color: #64748b;">Comprehensive servicing as evaluated by specialized hearing lab</div>
                        </td>
                        <td style="text-align: center;">1</td>
                        <td style="text-align: right; font-weight: 600;">৳{{ number_format($job->estimate_amount, 2) }}</td>
                    </tr>
                @endif

                @if($job->inspection_fee > 0)
                <tr>
                    <td colspan="2" style="text-align: right; font-weight: 600;">Standard Diagnostic / Inspection Fee:</td>
                    <td style="text-align: right; font-weight: 600;">৳{{ number_format($job->inspection_fee, 2) }}</td>
                </tr>
                @endif

                <tr style="background: #f8fafc; font-size: 14px;">
                    <td colspan="2" style="text-align: right; font-weight: bold; color: #0f172a;">Total Estimated Repair Cost:</td>
                    <td style="text-align: right; font-weight: bold; color: #2563eb;">
                        ৳{{ number_format($job->estimate_amount + $job->inspection_fee, 2) }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align: right; color: #64748b;">Advance Paid at Intake / Approval:</td>
                    <td style="text-align: right; font-weight: 600; color: #15803d;">
                        ৳{{ number_format($job->total_customer_paid, 2) }}
                    </td>
                </tr>
                <tr style="background: #f1f5f9;">
                    <td colspan="2" style="text-align: right; font-weight: bold; color: #0f172a;">Estimated Balance Payable on Delivery:</td>
                    <td style="text-align: right; font-weight: bold; color: #b91c1c;">
                        ৳{{ number_format(max(0, ($job->estimate_amount + $job->inspection_fee) - $job->total_customer_paid), 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Terms & Conditions --}}
        <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; font-size: 11px; color: #64748b; line-height: 1.5; margin-bottom: 20px;">
            <strong>Estimate Terms &amp; Policies:</strong>
            <ul style="margin: 4px 0 0 16px; padding: 0;">
                <li>This estimate is valid for 7 days from the date of issue.</li>
                <li>Work will commence only upon written or confirmed verbal approval and required advance payment.</li>
                <li>If estimate is declined, device will be returned unrepaired upon settlement of the initial inspection fee (if applicable).</li>
                <li>Repaired components carry a 30-day service warranty against identical defects.</li>
            </ul>
        </div>

        {{-- Patient Approval Slip --}}
        <div class="sign-grid">
            <div>
                <div class="sign-line">Clinic Service Advisor</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ auth()->user()?->name ?? 'Representative' }}</div>
            </div>
            <div>
                <div class="sign-line">Patient Approval Signature &amp; Date</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">"I approve the estimate and authorize repair work."</div>
            </div>
        </div>
    </div>

</body>
</html>
