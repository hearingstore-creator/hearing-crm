<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance Money Receipt - {{ $job->job_no }}</title>
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
            max-width: 750px;
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
        .receipt-card {
            border: 2px solid #10b981;
            background: #f0fdf4;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .receipt-card .amount {
            font-size: 28px;
            font-weight: 800;
            color: #047857;
            margin: 8px 0;
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
        <button class="btn-print" onclick="window.print()">Print Money Receipt (Ctrl+P)</button>
        <button style="padding: 8px 16px; margin-left: 10px; cursor: pointer;" onclick="window.close(); setTimeout(function () { window.location.href = '{{ route('repairs.show', $job->id) }}'; }, 250);">Close Window</button>
    </div>

    <div class="page-container">
        {{-- Document Header --}}
        @include('admin.partials.document_header', [
            'vendor'       => $job->vendor,
            'documentType' => 'ADVANCE PAYMENT MONEY RECEIPT',
            'documentNo'   => '#' . ($job->advance_receipt_no ?: $job->job_no . '-ADV'),
            'date'         => now(),
            'status'       => 'Payment Received',
            'statusClass'  => 'paid',
        ])

        {{-- Money Highlight --}}
        <div class="receipt-card">
            <div style="font-size: 13px; font-weight: 600; text-transform: uppercase; color: #065f46; letter-spacing: 0.5px;">
                Amount Received in Advance
            </div>
            <div class="amount">
                ৳{{ number_format($job->total_customer_paid, 2) }}
            </div>
            <div style="font-size: 12px; color: #065f46;">
                Received with thanks towards Service Job #{{ $job->job_no }}
            </div>
        </div>

        {{-- Info Grid --}}
        <div class="info-grid">
            <div class="info-box">
                <h4>Patient Information</h4>
                <div class="info-row"><span class="label">Patient Name:</span> <span class="val">{{ $job->customer?->lead?->full_name ?? 'Walk-in Customer' }}</span></div>
                <div class="info-row"><span class="label">Patient UID:</span> <span class="val font-monospace">{{ $job->customer?->customer_uid ?? 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Phone:</span> <span class="val">{{ $job->customer?->lead?->phone_number ?? '-' }}</span></div>
                <div class="info-row"><span class="label">Clinic Branch:</span> <span class="val">{{ $job->vendor?->name ?? 'Headquarters' }}</span></div>
            </div>

            <div class="info-box">
                <h4>Job &amp; Device Details</h4>
                <div class="info-row"><span class="label">Job Card #:</span> <span class="val font-monospace">{{ $job->job_no }}</span></div>
                <div class="info-row"><span class="label">Device:</span> <span class="val">{{ $job->product_display_name }}</span></div>
                <div class="info-row"><span class="label">Serial Number:</span> <span class="val font-monospace">{{ $job->original_serial_no }}</span></div>
                <div class="info-row"><span class="label">Receipt Date:</span> <span class="val">{{ date('d M Y, h:i A') }}</span></div>
            </div>
        </div>

        {{-- Payment Summary Table --}}
        <table class="table-custom">
            <thead>
                <tr style="background: #f1f5f9; text-transform: uppercase; font-size: 11px; color: #475569;">
                    <th>Particulars</th>
                    <th style="width: 150px; text-align: right;">Amount (BDT)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Quoted Service &amp; Diagnostic Charges</td>
                    <td style="text-align: right; font-weight: 600;">৳{{ number_format($job->total_customer_cost, 2) }}</td>
                </tr>
                <tr style="color: #047857;">
                    <td><strong>Less: Advance Paid (This Receipt)</strong></td>
                    <td style="text-align: right; font-weight: bold;">- ৳{{ number_format($job->total_customer_paid, 2) }}</td>
                </tr>
                <tr style="background: #f8fafc; font-size: 13px;">
                    <td><strong>Remaining Estimated Balance Due on Handover:</strong></td>
                    <td style="text-align: right; font-weight: bold; color: #b91c1c;">
                        ৳{{ number_format($job->customer_due, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Signatures --}}
        <div class="sign-grid">
            <div>
                <div class="sign-line">Received By (Clinic Cashier)</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ auth()->user()?->name ?? 'Accounts Officer' }}</div>
            </div>
            <div>
                <div class="sign-line">Patient / Bearer Signature</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Thank you for your trust</div>
            </div>
        </div>
    </div>

</body>
</html>
