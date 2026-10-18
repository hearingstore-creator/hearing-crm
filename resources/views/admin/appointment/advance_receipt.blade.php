<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Receipt - {{ $advance->receipt_no }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        body { background: #f1f5f9; padding: 24px; color: #1e293b; }
        .receipt-container { max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); padding: 32px; border: 1px solid #e2e8f0; }
        .receipt-header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 20px; border-bottom: 2px dashed #cbd5e1; }
        .brand-name { font-size: 20px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .brand-meta { font-size: 12px; color: #64748b; line-height: 1.4; }
        .receipt-title-box { text-align: right; }
        .receipt-badge { display: inline-block; background: #ecfdf5; color: #065f46; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 6px; letter-spacing: 0.5px; border: 1px solid #a7f3d0; margin-bottom: 6px; }
        .receipt-no { font-size: 16px; font-weight: 800; color: #0f172a; }
        .receipt-date { font-size: 12px; color: #64748b; }
        
        .receipt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 20px 0; border-bottom: 1px solid #f1f5f9; }
        .info-card { background: #f8fafc; border-radius: 8px; padding: 12px 16px; font-size: 12.5px; border: 1px solid #e2e8f0; }
        .info-card h4 { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 6px; letter-spacing: 0.5px; }
        .info-card p { margin-bottom: 3px; }
        .info-card strong { color: #0f172a; }

        .amount-hero { background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #ffffff; border-radius: 10px; padding: 20px; margin: 20px 0; text-align: center; }
        .amount-hero .lbl { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.9; }
        .amount-hero .val { font-size: 32px; font-weight: 800; margin: 4px 0; }
        .amount-hero .sub { font-size: 12px; opacity: 0.95; }

        .meta-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 24px; }
        .meta-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        .meta-table td:first-child { color: #64748b; width: 40%; font-weight: 600; }
        .meta-table td:last-child { color: #0f172a; font-weight: 700; text-align: right; }

        .signature-row { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 10px; }
        .sig-block { width: 200px; text-align: center; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 12px; color: #475569; font-weight: 600; }

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
        <button onclick="window.print()" class="btn-act btn-prt"><i class="fas fa-print"></i> Print Receipt</button>
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
                <span class="receipt-badge">Advance Payment</span>
                <div class="receipt-no">{{ $advance->receipt_no }}</div>
                <div class="receipt-date">{{ $advance->date?->format('d M Y') ?? date('d M Y') }}</div>
            </div>
        </div>

        {{-- Patient & Appointment Info --}}
        <div class="receipt-grid">
            <div class="info-card">
                <h4>Patient Details</h4>
                <p>Name: <strong>{{ $appointment->customer?->lead?->full_name ?? ($appointment->lead?->full_name ?? 'Patient') }}</strong></p>
                <p>Phone: <strong>{{ $appointment->customer?->lead?->phone_number ?? ($appointment->lead?->phone_number ?? 'N/A') }}</strong></p>
                @if ($appointment->customer?->customer_uid)
                    <p>Customer ID: <strong>{{ $appointment->customer->customer_uid }}</strong></p>
                @endif
            </div>
            <div class="info-card">
                <h4>Appointment Details</h4>
                <p>Appointment #: <strong>#{{ $appointment->id }}</strong></p>
                <p>Date: <strong>{{ $appointment->appointment_date?->format('d M Y') }}</strong></p>
                <p>Doctor / Staff: <strong>{{ $appointment->user?->name ?? 'Attending Professional' }}</strong></p>
                <p>Service: <strong>{{ $appointment->service?->name ?? 'Diagnostic / Consultation' }}</strong></p>
            </div>
        </div>

        {{-- Hero Amount --}}
        <div class="amount-hero">
            <div class="lbl">Advance Amount Received</div>
            <div class="val">৳{{ number_format($advance->amount, 2) }}</div>
            <div class="sub">Method: {{ ucfirst($advance->payment_method) }} @if($advance->reference)&bull; Ref: {{ $advance->reference }} @endif</div>
        </div>

        {{-- Meta Table --}}
        <table class="meta-table">
            <tr>
                <td>Receipt Number</td>
                <td>{{ $advance->receipt_no }}</td>
            </tr>
            <tr>
                <td>Payment Method</td>
                <td>{{ strtoupper($advance->payment_method) }}</td>
            </tr>
            @if ($advance->reference)
            <tr>
                <td>Transaction Reference</td>
                <td>{{ $advance->reference }}</td>
            </tr>
            @endif
            <tr>
                <td>Advance Status</td>
                <td>{{ $advance->statusLabel() }}</td>
            </tr>
            @if ($advance->notes)
            <tr>
                <td>Notes / Remarks</td>
                <td>{{ $advance->notes }}</td>
            </tr>
            @endif
        </table>

        <div style="font-size: 11.5px; color: #64748b; line-height: 1.5; background: #f8fafc; padding: 10px 14px; border-radius: 8px; border-left: 3px solid #059669;">
            <i class="fas fa-info-circle text-success mr-1"></i>
            <strong>Advance Policy:</strong> This advance deposit is credited to your customer account and will be automatically deducted from your final billing upon visit. In case of cancellation, advance refund or credit retention is subject to clinic policy.
        </div>

        {{-- Signatures --}}
        <div class="signature-row">
            <div class="sig-block">Patient's Signature</div>
            <div class="sig-block">Authorized Signatory / Cashier</div>
        </div>
    </div>

</body>
</html>
