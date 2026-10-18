<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Dispatch Note / Challan - {{ $job->job_no }}</title>
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
            padding: 8px 12px;
            text-align: left;
        }
        .table-custom th {
            background: #f1f5f9;
            font-size: 11px;
            text-transform: uppercase;
            color: #475569;
        }
        .dispatch-banner {
            border: 2px dashed #0284c7;
            background: #f0f9ff;
            border-radius: 8px;
            padding: 14px;
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
        <button class="btn-print" onclick="window.print()">Print Dispatch Challan (Ctrl+P)</button>
        <button style="padding: 8px 16px; margin-left: 10px; cursor: pointer;" onclick="window.close()">Close Window</button>
    </div>

    <div class="page-container">
        {{-- Document Header --}}
        @include('admin.partials.document_header', [
            'vendor'       => $job->vendor,
            'documentType' => 'EXTERNAL LAB DISPATCH CHALLAN',
            'documentNo'   => '#' . $job->job_no,
            'date'         => $job->dispatched_at ?: now(),
            'status'       => 'Dispatched to Lab',
            'statusClass'  => 'pending',
        ])

        {{-- Logistics & Courier Details --}}
        <div class="dispatch-banner">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #0369a1; margin-bottom: 6px;">
                <i class="fas fa-truck mr-1"></i> Logistics &amp; Transport Mode
            </div>
            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; font-size: 13px;">
                <div>
                    <strong>Dispatch Method:</strong> 
                    <span style="text-transform: uppercase; font-weight: 600; color: #0f172a;">
                        {{ $job->dispatch_type === 'courier' ? 'Courier Service' : 'By Hand / Staff Bearer' }}
                    </span>
                </div>
                @if($job->dispatch_type === 'courier')
                    <div><strong>Courier Name:</strong> {{ $job->courier_name ?? 'N/A' }}</div>
                    <div><strong>Tracking / CN #:</strong> <span style="font-family: monospace; font-weight: bold;">{{ $job->courier_tracking_no ?? 'N/A' }}</span></div>
                @else
                    <div><strong>Bearer Person:</strong> {{ $job->carrier_person_name ?? 'Clinic Representative' }}</div>
                @endif
                <div><strong>Dispatch Date:</strong> {{ $job->dispatched_at ? $job->dispatched_at->format('d M Y, h:i A') : date('d M Y') }}</div>
            </div>
        </div>

        {{-- Destination & Device Info --}}
        <div class="info-grid">
            <div class="info-box">
                <h4>Destination Supplier Lab</h4>
                <div class="info-row"><span class="label">Lab / Supplier:</span> <span class="val">{{ $job->supplier?->supplier_name ?? 'Manufacturer Service Lab' }}</span></div>
                <div class="info-row"><span class="label">Contact Person:</span> <span class="val">{{ $job->supplier?->contact_person ?? 'Lab In-Charge' }}</span></div>
                <div class="info-row"><span class="label">Contact Phone:</span> <span class="val">{{ $job->supplier?->phone ?? 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Lab Reference / Memo:</span> <span class="val">{{ $job->lab_ref_no ?: 'To be assigned' }}</span></div>
            </div>

            <div class="info-box">
                <h4>Origin Clinic Details</h4>
                <div class="info-row"><span class="label">Branch:</span> <span class="val">{{ $job->vendor?->name ?? 'Headquarters' }}</span></div>
                <div class="info-row"><span class="label">Dispatched By:</span> <span class="val">{{ auth()->user()?->name ?? 'Counter Staff' }}</span></div>
                <div class="info-row"><span class="label">Clinic Phone:</span> <span class="val">{{ $job->vendor?->phone_number ?? '-' }}</span></div>
                <div class="info-row"><span class="label">Promised Turnaround:</span> <span class="val">{{ $job->promised_date ? $job->promised_date->format('d M Y') : 'Standard SLA' }}</span></div>
            </div>
        </div>

        {{-- Dispatched Unit Table --}}
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 50px;">Item</th>
                    <th>Product / Model Description</th>
                    <th>Serial Number</th>
                    <th>Warranty Class</th>
                    <th>Physical Condition</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>
                        <strong>{{ $job->product_display_name }}</strong>
                        <div style="font-size: 11px; color: #64748b;">Ear Side: {{ strtoupper($job->side) }}</div>
                    </td>
                    <td style="font-family: monospace; font-weight: bold; font-size: 13px;">{{ $job->original_serial_no }}</td>
                    <td>
                        @if($job->pipeline_path === 'supplier_warranty')
                            <span style="color: #15803d; font-weight: bold;">Warranty Claim (BDT 0)</span>
                        @else
                            <span style="color: #b45309; font-weight: bold;">Paid Inspection &amp; Estimate</span>
                        @endif
                    </td>
                    <td>{{ $job->physical_condition ?: 'Good condition' }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Problem Description & Instructions --}}
        <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; background: #ffffff;">
            <h4 style="margin: 0 0 6px 0; font-size: 12px; text-transform: uppercase; color: #64748b;">Customer Reported Issue &amp; Instructions to Lab</h4>
            <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #1e293b;">
                {{ $job->reported_issues ?: 'General checkup, internal diagnostic & servicing.' }}
            </p>
            @if(!empty($job->accessories_included))
                <div style="margin-top: 10px; font-size: 12px; color: #475569;">
                    <strong>Accessories Enclosed:</strong> {{ implode(', ', $job->accessories_included) }}
                </div>
            @endif
        </div>

        {{-- Signatures --}}
        <div class="sign-grid">
            <div>
                <div class="sign-line">Clinic Authorized Signature</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ auth()->user()?->name ?? 'Audiologist / Technician' }}</div>
            </div>
            <div>
                <div class="sign-line">Lab Received By (Sign &amp; Date)</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Supplier Lab Technician / Courier Receiver</div>
            </div>
        </div>
    </div>

</body>
</html>
