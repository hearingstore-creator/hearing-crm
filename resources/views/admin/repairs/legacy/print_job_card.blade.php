<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Job Card - {{ $job->job_no }}</title>
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
        <button class="btn-print" onclick="window.print()">Print Job Card (Ctrl+P)</button>
        <button style="padding: 8px 16px; margin-left: 10px; cursor: pointer;" onclick="window.close()">Close Window</button>
    </div>

    <div class="page-container">
        {{-- Dual-Branding Document Header --}}
        @include('admin.partials.document_header', [
            'vendor'       => $job->vendor,
            'documentType' => 'SERVICE JOB CARD',
            'documentNo'   => '#' . $job->job_no,
            'date'         => $job->created_at,
            'status'       => $job->current_status === 'received' ? 'Received at Clinic' : ucfirst(str_replace('_', ' ', $job->current_status)),
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

        {{-- 2-Column Info Grid --}}
        <div class="info-grid">
            <div class="info-box">
                <h4>Patient &amp; Customer Information</h4>
                <div class="info-row"><span class="label">Patient Name:</span> <span class="val">{{ $job->customer?->lead?->full_name ?? 'Walk-in Customer' }}</span></div>
                <div class="info-row"><span class="label">Patient UID:</span> <span class="val font-monospace">{{ $job->customer?->customer_uid ?? 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Phone:</span> <span class="val">{{ $job->customer?->lead?->phone_number ?? 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Address:</span> <span class="val">{{ $job->customer?->lead?->address ?? 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Receiving Branch:</span> <span class="val">{{ $job->vendor?->name ?? 'Head Office Clinic' }}</span></div>
            </div>

            <div class="info-box">
                <h4>Hearing Aid / Device Details</h4>
                <div class="info-row"><span class="label">Brand &amp; Model:</span> <span class="val">{{ $job->device_name }}</span></div>
                <div class="info-row"><span class="label">Serial Number (S/N):</span> <span class="val font-monospace" style="color: #2563eb;">{{ $job->original_serial_no }}</span></div>
                <div class="info-row"><span class="label">Ear Side:</span> <span class="val text-uppercase">{{ $job->side !== 'na' ? $job->side . ' Ear' : 'N/A' }}</span></div>
                <div class="info-row"><span class="label">Original Invoice:</span> <span class="val">{{ $job->saleMaster ? '#' . $job->saleMaster->sale_invoice_no : 'Third-Party' }}</span></div>
                <div class="info-row"><span class="label">Promised Delivery:</span> <span class="val" style="color: #d97706;">{{ $job->promised_date ? $job->promised_date->format('d M Y, h:i A') : 'TBD' }}</span></div>
            </div>
        </div>

        {{-- Warranty Assessment Banner --}}
        <div style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; margin-bottom: 20px; background: #f8fafc;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <b>Warranty Assessment:</b>
                    @if($job->warranty_status === 'under_product_warranty')
                        <span style="color: #059669; font-weight: bold; margin-left: 8px;">[✓ PRODUCT WARRANTY ACTIVE] (100% Covered by Manufacturer)</span>
                    @elseif($job->warranty_status === 'under_service_warranty')
                        <span style="color: #0284c7; font-weight: bold; margin-left: 8px;">[✓ SERVICE GUARANTEE ACTIVE] (Covered under prior repair warranty)</span>
                    @elseif($job->warranty_status === 'void')
                        <span style="color: #dc2626; font-weight: bold; margin-left: 8px;">[WARRANTY VOID] Reason: {{ $job->void_reason ?: 'Physical/Liquid Damage' }}</span>
                    @else
                        <span style="color: #475569; font-weight: bold; margin-left: 8px;">[OUT OF WARRANTY] Standard Chargeable Service</span>
                    @endif
                </div>
                <div>
                    Job Type: <b class="text-uppercase">{{ str_replace('_', ' ', $job->job_type) }}</b>
                </div>
            </div>
        </div>

        {{-- Problem & Accessories Table --}}
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 55%;">Reported Symptoms &amp; Fault Description</th>
                    <th style="width: 45%;">Accessories Received at Clinic</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="vertical-align: top;">
                        <p style="margin: 0; font-weight: 500;">{{ $job->reported_issues ?: 'General checkup & service requested.' }}</p>
                        @if($job->physical_condition)
                            <div style="margin-top: 8px; font-size: 11px; color: #64748b;">
                                <b>Physical Condition Note:</b> {{ $job->physical_condition }}
                            </div>
                        @endif
                    </td>
                    <td style="vertical-align: top;">
                        @if(!empty($job->accessories_included) && count($job->accessories_included) > 0)
                            <ul style="margin: 0; padding-left: 18px;">
                                @foreach($job->accessories_included as $acc)
                                    <li>{{ $acc }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span style="color: #64748b;">Hearing Aid Unit Only</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Financials Table --}}
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="width: 120px; text-align: right;">Amount (৳)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Inspection / Branch Evaluation Fee</td>
                    <td style="text-align: right;">{{ number_format($job->inspection_fee, 2) }}</td>
                </tr>
                <tr>
                    <td>Estimated Service / Repair Charges</td>
                    <td style="text-align: right;">{{ number_format($job->estimate_amount, 2) }}</td>
                </tr>
                <tr style="font-weight: bold; background: #f8fafc;">
                    <td>Total Estimated Amount</td>
                    <td style="text-align: right;">৳{{ number_format($job->total_customer_cost, 2) }}</td>
                </tr>
                <tr>
                    <td style="color: #059669; font-weight: bold;">Advance Paid at Intake</td>
                    <td style="text-align: right; color: #059669; font-weight: bold;">- ৳{{ number_format($job->total_customer_paid, 2) }}</td>
                </tr>
                <tr style="font-weight: bold; font-size: 14px; background: #f1f5f9;">
                    <td>Balance Payable upon Collection</td>
                    <td style="text-align: right; color: {{ $job->customer_due > 0 ? '#dc2626' : '#059669' }};">৳{{ number_format($job->customer_due, 2) }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Terms & Conditions --}}
        <div class="terms-box">
            <b>Important Terms &amp; Conditions:</b>
            <ol style="margin: 4px 0 0 0; padding-left: 16px;">
                <li>Please present this Job Card upon collecting your device. Devices will only be handed over to the authorized holder of this slip.</li>
                <li><b>Provisional Condition Assessment:</b> Intake physical evaluation is preliminary and covers external appearance only. Manufacturer warranty does not cover internal moisture, sweat ingress, battery acid corrosion, or unauthorized tampering detected upon laboratory disassembly. In such events, standard repair charges will apply subject to patient approval.</li>
                <li><b>Cost Estimates &amp; Quotations:</b> Service costs are estimates. If laboratory inspection reveals additional defective components or voided warranty, an updated quotation will be submitted for customer authorization before repairs proceed.</li>
                <li>Estimated delivery dates are subject to parts availability and external lab transit schedules.</li>
                <li>Devices not collected within 60 days of the "Ready for Pickup" notice may be archived in accordance with clinic storage policies.</li>
                <li>Post-warranty repair work carries a dedicated <b>Service Guarantee</b> on the specific components repaired as specified on the Delivery Note.</li>
            </ol>
        </div>

        {{-- Signatures --}}
        <div class="sign-grid">
            <div>
                <div class="sign-line">Customer / Patient Signature</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">Condition &amp; accessories confirmed</div>
            </div>
            <div>
                <div class="sign-line">Authorized Clinic Receiver</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">{{ $job->receivedBy?->name ?? 'Hearing Care Specialist' }}</div>
            </div>
        </div>

    </div>

</body>
</html>
