<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warranty Replacement Certificate - {{ $job->job_no }}</title>
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
            padding: 35px;
            border-radius: 8px;
            border: 2px solid #0284c7;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            position: relative;
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
            padding: 14px 16px;
            background: #f8fafc;
        }
        .info-box h4 {
            margin: 0 0 10px 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0369a1;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        .info-row .label {
            color: #64748b;
        }
        .info-row .val {
            font-weight: 600;
            color: #0f172a;
        }
        .lineage-card {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 25px;
        }
        .lineage-flex {
            display: flex;
            align-items: center;
            justify-content: space-around;
            text-align: center;
        }
        .lineage-col {
            flex: 1;
            padding: 10px;
        }
        .lineage-arrow {
            font-size: 24px;
            color: #0284c7;
            padding: 0 15px;
        }
        .badge-old {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
            display: inline-block;
            margin-bottom: 6px;
        }
        .badge-new {
            background: #dcfce7;
            color: #166534;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
            display: inline-block;
            margin-bottom: 6px;
        }
        .guarantee-banner {
            background: #ecfdf5;
            border: 2px dashed #059669;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 25px;
            text-align: center;
        }
        .terms-box {
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
            border-top: 1px dashed #cbd5e1;
            padding-top: 14px;
            margin-top: 20px;
        }
        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-top: 45px;
            text-align: center;
        }
        .sign-line {
            border-top: 1px solid #334155;
            padding-top: 6px;
            font-weight: 600;
            font-size: 12px;
        }
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background: #0284c7;
            color: white;
            border: none;
            padding: 8px 22px;
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
                border: 1px solid #0284c7;
                padding: 20px;
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
        <button class="btn-print" onclick="window.print()">Print Replacement Certificate (Ctrl+P)</button>
        <button style="padding: 8px 16px; margin-left: 10px; cursor: pointer;" onclick="window.close(); setTimeout(function () { window.location.href = '{{ route('repairs.show', $job->id) }}'; }, 250);">Close Window</button>
    </div>

    <div class="page-container">
        {{-- Dual-Branding Document Header --}}
        @include('admin.partials.document_header', [
            'vendor'       => $job->vendor,
            'documentType' => 'OFFICIAL WARRANTY REPLACEMENT CERTIFICATE',
            'documentNo'   => 'REP-CERT-' . $job->job_no,
            'date'         => $job->delivered_at ?: now(),
            'status'       => 'WARRANTY AUTHORIZED',
            'statusClass'  => 'paid',
        ])

        {{-- Serial Number Lineage Transfer Card --}}
        <div class="lineage-card">
            <div style="text-align: center; font-size: 12px; text-transform: uppercase; font-weight: bold; color: #0369a1; margin-bottom: 12px; letter-spacing: 0.5px;">
                Official Unit Replacement &amp; Serial Number Transfer Record
            </div>
            <div class="lineage-flex">
                <div class="lineage-col">
                    <div class="badge-old">DEFECTIVE ORIGINAL UNIT</div>
                    <div style="font-size: 11px; color: #64748b;">Serial Number</div>
                    <div style="font-family: monospace; font-size: 16px; font-weight: bold; color: #991b1b;">
                        {{ $job->original_serial_no }}
                    </div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Status: Retired / Supplier RMA</div>
                </div>

                <div class="lineage-arrow">
                    ➔
                </div>

                <div class="lineage-col">
                    <div class="badge-new">NEW REPLACEMENT UNIT</div>
                    <div style="font-size: 11px; color: #64748b;">Serial Number</div>
                    <div style="font-family: monospace; font-size: 16px; font-weight: bold; color: #166534;">
                        {{ $job->replaced_serial_no ?: ($replacementChain->serial_no ?? 'N/A') }}
                    </div>
                    <div style="font-size: 11px; color: #166534; font-weight: 600; margin-top: 4px;">Status: Active With Patient</div>
                </div>
            </div>
        </div>

        {{-- Patient & Device Meta Grid --}}
        <div class="info-grid">
            <div class="info-box">
                <h4>Patient &amp; Account Details</h4>
                <div class="info-row">
                    <span class="label">Patient Name:</span>
                    <span class="val">{{ $job->customer?->lead?->full_name ?? 'Valued Patient' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Patient UID:</span>
                    <span class="val font-monospace">{{ $job->customer?->customer_uid ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Phone Number:</span>
                    <span class="val">{{ $job->customer?->lead?->phone_number ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Serving Branch:</span>
                    <span class="val">{{ $job->vendor?->name ?? 'Hearing Care Center' }}</span>
                </div>
            </div>

            <div class="info-box">
                <h4>Product &amp; Service Reference</h4>
                <div class="info-row">
                    <span class="label">Job Reference:</span>
                    <span class="val font-weight-bold" style="color: #0284c7;">#{{ $job->job_no }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Device Model:</span>
                    <span class="val">{{ $replacementChain?->product?->product_name ?? $job->device_name }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Original Invoice:</span>
                    <span class="val">{{ $job->saleMaster?->sale_invoice_no ? '#' . $job->saleMaster->sale_invoice_no : 'Walk-in' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Ear Side:</span>
                    <span class="val" style="text-transform: uppercase;">{{ $job->side !== 'na' ? $job->side . ' Ear' : 'N/A' }}</span>
                </div>
                @if($job->supplier)
                    <div class="info-row">
                        <span class="label">Servicing Lab:</span>
                        <span class="val">{{ $job->supplier->supplier_name }}</span>
                    </div>
                @endif
                @if($job->lab_ref_no)
                    <div class="info-row">
                        <span class="label">Supplier Cash Memo / Challan:</span>
                        <span class="val font-monospace" style="color: #0369a1;">{{ $job->lab_ref_no }}</span>
                    </div>
                @endif
                <div class="info-row">
                    <span class="label">Manufacturer Settlement:</span>
                    <span class="val" style="color: {{ $job->total_supplier_cost > 0 ? '#b91c1c' : '#059669' }};">
                        {{ $job->total_supplier_cost > 0 ? 'Charged (৳' . number_format($job->total_supplier_cost, 2) . ')' : 'Free Warranty Replacement (৳0.00)' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Active Inherited Warranty Guarantee Banner --}}
        @php
            $expiryDate = $replacementChain?->warranty_end_date ? Carbon\Carbon::parse($replacementChain->warranty_end_date) : null;
            $daysLeft = $expiryDate ? max(0, Carbon\Carbon::now()->diffInDays($expiryDate, false)) : 0;
        @endphp
        <div class="guarantee-banner">
            <div style="font-size: 18px; font-weight: bold; color: #166534; margin-bottom: 4px;">
                🛡️ WARRANTY INHERITANCE CONFIRMATION (OPTION A)
            </div>
            <div style="font-size: 13px; color: #1e293b; max-width: 650px; margin: 0 auto;">
                In accordance with clinic and manufacturer warranty regulations, the replacement unit (S/N: <b>{{ $job->replaced_serial_no }}</b>) inherits the remaining warranty balance of the original purchased unit.
            </div>
            @if($expiryDate)
                <div style="margin-top: 10px; font-size: 15px;">
                    Active Warranty Expiry Date: <b style="color: #059669;">{{ $expiryDate->format('d F Y') }}</b>
                    <span style="font-size: 12px; color: #475569; margin-left: 8px;">({{ $daysLeft }} days of active coverage remaining)</span>
                </div>
            @endif
        </div>

        {{-- Barcode of New Unit --}}
        <div style="text-align: center; margin-bottom: 20px;">
            @php
                $cleanNew = preg_replace('/[^A-Za-z0-9\-]/', '', $job->replaced_serial_no ?: $job->job_no);
            @endphp
            <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($cleanNew, 'C128', 1.4, 32) }}" alt="Barcode" style="max-height: 32px; width: auto; display: inline-block;">
            <div style="font-family: monospace; font-size: 12px; font-weight: bold; letter-spacing: 2px; margin-top: 3px;">
                *{{ $cleanNew }}*
            </div>
        </div>

        {{-- Replacement Terms & Warranty Conditions --}}
        <div class="terms-box">
            <b>Terms &amp; Replacement Policy:</b>
            <ol style="padding-left: 18px; margin: 4px 0 0 0;">
                <li>The defective unit (S/N: {{ $job->original_serial_no }}) has been permanently surrendered to the clinic and decommissioned from service.</li>
                <li>The new unit is subject to identical warranty terms as the original contract. Physical impact, breakage, liquid/water immersion, battery leakage, or third-party tampering strictly voids all manufacturer warranty.</li>
                <li>Please present this certificate along with the original sales invoice for any subsequent service inquiries or warranty support.</li>
            </ol>
        </div>

        {{-- Signatures --}}
        <div class="sign-grid">
            <div>
                <div style="height: 45px;"></div>
                <div class="sign-line">Customer / Patient Acknowledgement</div>
            </div>
            <div>
                <div style="height: 45px;"></div>
                <div class="sign-line">Authorized Audiologist / Clinic Director</div>
            </div>
        </div>

    </div>

</body>
</html>
