<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Receipt - {{ $advance->refund_receipt_no ?? $advance->receipt_no }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        body { background: #f1f5f9; padding: 24px; color: #1e293b; }
        .receipt-container { max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); padding: 32px; border: 1px solid #e2e8f0; }
        .receipt-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 20px; border-bottom: 2px dashed #cbd5e1; }
        .brand-name { font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .brand-meta { font-size: 12px; color: #64748b; line-height: 1.4; }
        .receipt-title-box { text-align: right; }
        .receipt-badge { display: inline-block; background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.5px; border: 1px solid #fca5a5; margin-bottom: 6px; }
        .receipt-no { font-size: 16px; font-weight: 800; color: #0f172a; }
        .receipt-date { font-size: 12px; color: #64748b; }
        
        .receipt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px 0; border-bottom: 1px solid #f1f5f9; }
        .info-card { background: #f8fafc; border-radius: 8px; padding: 12px 16px; font-size: 12.5px; border: 1px solid #e2e8f0; }
        .info-card h4 { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 6px; letter-spacing: 0.5px; }
        .info-card p { margin-bottom: 3px; }
        .info-card strong { color: #0f172a; }

        .amount-hero { background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); color: #ffffff; border-radius: 10px; padding: 20px; margin: 20px 0; text-align: center; }
        .amount-hero .lbl { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; }
        .amount-hero .val { font-size: 32px; font-weight: 800; margin: 4px 0; }
        .amount-hero .sub { font-size: 12px; opacity: 0.95; }

        .meta-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 24px; }
        .meta-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        .meta-table td:first-child { color: #64748b; width: 45%; font-weight: 600; }
        .meta-table td:last-child { color: #0f172a; font-weight: 700; text-align: right; }

        .signature-row { display: flex; justify-content: space-between; margin-top: 60px; padding-top: 10px; }
        .sig-block { width: 220px; text-align: center; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 12px; color: #475569; font-weight: 600; }

        .no-print { display: flex; justify-content: center; gap: 12px; margin-bottom: 20px; }
        .btn-act { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-prt { background: #0f172a; color: #ffffff; }
        .btn-cls { background: #e2e8f0; color: #334155; }

        @media print {
            body { background: #ffffff; padding: 0; }
            .receipt-container { box-shadow: none; border: none; padding: 10px; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn-act btn-prt"><i class="fas fa-print"></i> Print Refund Receipt</button>
        <button onclick="window.close()" class="btn-act btn-cls"><i class="fas fa-times"></i> Close Window</button>
    </div>

    <div class="receipt-container">
        {{-- Header --}}
        <div class="receipt-header">
            <div>
                <div class="brand-name">{{ $branding->company_name ?? 'Hearing Solutions' }}</div>
                <div class="brand-meta">
                    {{ $branding->address ?? '' }}<br>
                    Phone: {{ $branding->mobile ?? $branding->phone ?? '' }} &bull; Email: {{ $branding->email ?? '' }}
                </div>
            </div>
            <div class="receipt-title-box">
                <span class="receipt-badge">Refund Receipt</span>
                <div class="receipt-no">{{ $advance->refund_receipt_no ?? $advance->receipt_no }}</div>
                <div class="receipt-date">{{ $advance->refund_date?->format('d M Y') ?? date('d M Y') }}</div>
            </div>
        </div>

        {{-- Patient & Original Advance Info --}}
        <div class="receipt-grid">
            <div class="info-card">
                <h4>Patient Details</h4>
                <p>Name: <strong>{{ $advance->customer?->lead?->full_name ?? ($advance->lead?->full_name ?? 'Patient') }}</strong></p>
                <p>Phone: <strong>{{ $advance->customer?->lead?->phone_number ?? ($advance->lead?->phone_number ?? 'N/A') }}</strong></p>
                @if ($advance->customer?->customer_uid)
                    <p>Customer ID: <strong>{{ $advance->customer->customer_uid }}</strong></p>
                @endif
            </div>
            <div class="info-card">
                <h4>Original Advance Details</h4>
                <p>Advance Receipt #: <strong>{{ $advance->receipt_no }}</strong></p>
                <p>Advance Date: <strong>{{ $advance->date?->format('d M Y') }}</strong></p>
                @if ($advance->appointment)
                    <p>Linked Appointment: <strong>#{{ $advance->appointment->id }}</strong></p>
                @endif
                <p>Original Amount: <strong>৳{{ number_format($advance->amount, 2) }}</strong></p>
            </div>
        </div>

        {{-- Hero Amount --}}
        <div class="amount-hero">
            <div class="lbl">Net Amount Refunded</div>
            <div class="val">৳{{ number_format($advance->refunded_amount, 2) }}</div>
            <div class="sub">Disbursed via {{ ucfirst($advance->refund_method ?? 'Cash') }} @if($advance->refund_reference)&bull; Ref: {{ $advance->refund_reference }} @endif</div>
        </div>

        {{-- Breakdown Table --}}
        <table class="meta-table">
            <tr>
                <td>Original Advance Deposit</td>
                <td>৳{{ number_format($advance->amount, 2) }}</td>
            </tr>
            @if ($advance->deduction_fee > 0)
            <tr style="color: #b91c1c;">
                <td>Cancellation / Deduction Fee</td>
                <td>- ৳{{ number_format($advance->deduction_fee, 2) }}</td>
            </tr>
            @endif
            <tr style="background: #f8fafc; font-size: 14px;">
                <td style="color: #0f172a; font-weight: 800;">Total Refunded to Patient</td>
                <td style="color: #b91c1c; font-weight: 800;">৳{{ number_format($advance->refunded_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Refund Disbursed Via</td>
                <td>{{ strtoupper($advance->refund_method ?? 'CASH') }}</td>
            </tr>
            @if ($advance->refund_reference)
            <tr>
                <td>Disbursement Reference / Cheque</td>
                <td>{{ $advance->refund_reference }}</td>
            </tr>
            @endif
            <tr>
                <td>Refund Reason</td>
                <td>{{ $advance->refund_reason ?? 'Patient requested cancellation' }}</td>
            </tr>
            <tr>
                <td>Processed By</td>
                <td>{{ $advance->refundByUser?->name ?? 'Admin Staff' }}</td>
            </tr>
        </table>

        <div style="font-size: 11.5px; color: #475569; line-height: 1.5; background: #fff1f2; padding: 10px 14px; border-radius: 8px; border-left: 3px solid #e11d48;">
            <i class="fas fa-file-contract text-danger mr-1"></i>
            <strong>Acknowledgment:</strong> I hereby acknowledge receipt of the refund amount stated above in full and final settlement of my advance deposit for Appointment #{{ $advance->appointment_id ?? 'N/A' }}.
        </div>

        {{-- Signatures --}}
        <div class="signature-row">
            <div class="sig-block">
                Patient's Signature &amp; Date
            </div>
            <div class="sig-block">
                Authorized Officer / Cashier
            </div>
        </div>
    </div>

</body>
</html>
