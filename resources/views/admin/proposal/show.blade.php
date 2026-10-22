<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $proposal->proposal_no ?: 'Quotation' }} - {{ $proposal->recipient_display_name }}</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #1e40af;
            --brand-accent: #0284c7;
            --brand-dark: #0f172a;
            --brand-gray: #64748b;
            --brand-border: #e2e8f0;
            --brand-bg: #f8fafc;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f1f5f9;
            color: #334155;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }

        /* Fixed Top Action Bar */
        .action-toolbar {
            position: sticky;
            top: 0;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Quotation A4 Container */
        .page-container {
            max-width: 210mm;
            min-height: 297mm;
            margin: 24px auto;
            background: #ffffff;
            padding: 20mm 20mm;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
            box-sizing: border-box;
            position: relative;
        }

        /* Letterhead Header */
        .clinic-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 18px;
            border-bottom: 2px solid var(--brand-primary);
            margin-bottom: 24px;
        }

        .clinic-logo img {
            max-height: 65px;
            width: auto;
            object-fit: contain;
        }

        .clinic-info {
            text-align: right;
            font-size: 11px;
            color: var(--brand-gray);
            line-height: 1.4;
        }

        .clinic-name {
            font-size: 18px;
            font-weight: 800;
            color: var(--brand-dark);
            margin-bottom: 2px;
            letter-spacing: -0.3px;
        }

        .clinic-slogan {
            font-size: 12px;
            font-weight: 600;
            color: var(--brand-accent);
            margin-bottom: 4px;
        }

        /* Document Title Badge */
        .doc-title-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 10px 16px;
            margin-bottom: 20px;
        }

        .doc-title-text {
            font-size: 16px;
            font-weight: 800;
            color: var(--brand-primary);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .quote-no {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 700;
            color: #1e3a8a;
            background: #ffffff;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #93c5fd;
        }

        /* Metadata Two-Column Grid */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .meta-card {
            background: var(--brand-bg);
            border: 1px solid var(--brand-border);
            border-radius: 8px;
            padding: 14px 16px;
        }

        .meta-card-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--brand-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 12px;
        }

        .meta-label {
            color: var(--brand-gray);
            font-weight: 500;
        }

        .meta-value {
            color: var(--brand-dark);
            font-weight: 600;
            text-align: right;
        }

        /* Treatment Goal Banner */
        .treatment-goal {
            background: #f8fafc;
            border-left: 4px solid var(--brand-primary);
            padding: 10px 16px;
            margin-bottom: 24px;
            border-radius: 0 6px 6px 0;
        }
        .treatment-goal h4 {
            font-size: 13px;
            font-weight: 700;
            color: var(--brand-dark);
            margin: 0 0 2px 0;
        }
        .treatment-goal p {
            font-size: 12px;
            color: var(--brand-gray);
            margin: 0;
        }

        /* Tables */
        .section-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--brand-dark);
            margin: 16px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .doc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 12px;
        }

        .doc-table th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            padding: 8px 10px;
            border: 1px solid var(--brand-border);
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }

        .doc-table td {
            padding: 8px 10px;
            border: 1px solid var(--brand-border);
            color: #1e293b;
        }

        .warranty-tag {
            display: inline-block;
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-top: 2px;
        }

        /* Totals Block */
        .summary-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
            margin-bottom: 24px;
        }

        .summary-table {
            width: 320px;
            border-collapse: collapse;
            font-size: 12px;
        }

        .summary-table td {
            padding: 6px 10px;
        }

        .summary-table .grand-total-row td {
            background: #eff6ff;
            border-top: 2px solid var(--brand-primary);
            border-bottom: 2px solid var(--brand-primary);
            font-size: 15px;
            font-weight: 800;
            color: var(--brand-primary);
        }

        /* Terms & Conditions Box */
        .terms-box {
            background: #fafafa;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 11px;
            color: #525252;
            line-height: 1.6;
            margin-bottom: 40px;
        }

        .terms-title {
            font-weight: 700;
            color: #262626;
            text-transform: uppercase;
            margin-bottom: 6px;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        /* Signatures */
        .signatures-grid {
            display: flex;
            justify-content: space-between;
            margin-top: 48px;
            padding-top: 12px;
        }

        .sig-block {
            width: 220px;
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid #94a3b8;
            margin-bottom: 6px;
        }

        .sig-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--brand-dark);
        }

        .sig-sub {
            font-size: 10px;
            color: var(--brand-gray);
        }

        /* Print Styling */
        @media print {
            body {
                background: #ffffff !important;
            }
            .action-toolbar, #proposal-online {
                display: none !important;
            }
            .page-container {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 12mm 15mm !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

    {{-- Top Action Toolbar (Hidden in Print) --}}
    <div class="action-toolbar">
        <div class="toolbar-left">
            <a href="{{ route('proposal.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Quotations
            </a>
            <span class="badge badge-light px-2 py-1" style="font-family: monospace; font-size: 13px;">
                {{ $proposal->proposal_no ?: '#' . $proposal->id }}
            </span>
            @php
                $statusEnum = \App\Enums\ProposalStatus::tryFrom($proposal->status) ?: \App\Enums\ProposalStatus::DRAFT;
            @endphp
            <span class="badge" style="background: {{ $statusEnum->color() }}; color: #ffffff; font-size: 11px; padding: 5px 10px;">
                {{ $proposal->status }}
            </span>
        </div>

        <div class="toolbar-right">
            {{-- Status Switcher --}}
            <form action="{{ route('proposal.status', $proposal->id) }}" method="POST" class="d-inline-flex" style="gap: 6px;">
                @csrf
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()" style="font-size: 12px; width: 130px;">
                    <option value="" disabled>Change Status...</option>
                    @foreach(\App\Enums\ProposalStatus::cases() as $case)
                        <option value="{{ $case->value }}" {{ $proposal->status === $case->value ? 'selected' : '' }}>
                            {{ $case->value }}
                        </option>
                    @endforeach
                </select>
            </form>

            {{-- 1-Click Convert to Sale --}}
            @if($proposal->status !== \App\Enums\ProposalStatus::CONVERTED->value)
                <form action="{{ route('proposal.convert', $proposal->id) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold" onclick="return confirm('Convert this Quotation into an active Sale Invoice?')">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> Convert to Invoice
                    </button>
                </form>
            @else
                <span class="btn btn-outline-success btn-sm disabled">
                    <i class="fas fa-check-circle mr-1"></i> Invoiced
                </span>
            @endif

            {{-- WhatsApp Share --}}
            @php
                $waText = urlencode("Hello " . $proposal->recipient_display_name . ",\nHere is your official Hearing Care & Audiology Treatment Quotation (" . ($proposal->proposal_no ?: '#' . $proposal->id) . ") from " . ($setting->company_name ?? 'our clinic') . ".\n\nTotal Quoted Amount: ৳" . number_format($proposal->grand_total ?: $proposal->total_amount, 2) . "\nValidity: " . ($proposal->valid_until ? $proposal->valid_until->format('d M Y') : '30 Days') . "\n\nPlease let us know if you have any questions.");
                $phoneClean = preg_replace('/[^0-9]/', '', $proposal->recipient_phone_display);
                if (strlen($phoneClean) === 11 && str_starts_with($phoneClean, '01')) {
                    $phoneClean = '88' . $phoneClean;
                }
            @endphp
            <a href="https://wa.me/{{ $phoneClean }}?text={{ $waText }}" target="_blank" class="btn btn-outline-success btn-sm" title="Share Quotation Summary via WhatsApp">
                <i class="fab fa-whatsapp mr-1"></i> WhatsApp
            </a>

            {{-- Print / PDF --}}
            <button class="btn btn-primary btn-sm font-weight-bold" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print / Save as PDF
            </button>

            {{-- Edit --}}
            @can('permission', 'proposals.edit')
                <a href="{{ route('proposal.edit', $proposal->id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-pen mr-1"></i> Edit
                </a>
            @endcan
        </div>
    </div>

    {{-- Online link: patient opens, accepts or declines; optional payment link --}}
    @can('permission', 'proposals.edit')
    <div class="no-print" id="proposal-online" style="max-width: 900px; margin: 0 auto 14px; background: var(--card-bg, #fff); border: 1px solid var(--gray-200, #e2e8f0); border-radius: 10px; padding: 12px 16px; font-size: 13px;">
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <b><i class="fas fa-link mr-1"></i>Online quotation</b>
            @if ($proposal->public_token)
                <input type="text" readonly id="proposal-link" class="form-control form-control-sm" style="max-width:420px;" value="{{ url('q/' . $proposal->public_token) }}" onclick="this.select()">
                <span class="text-muted">
                    {{ $proposal->viewed_at ? 'Opened ' . $proposal->viewed_at->diffForHumans() : 'Not opened yet' }}
                    @if ($proposal->responded_at) · {{ $proposal->status }} {{ $proposal->responded_at->diffForHumans() }}{{ $proposal->responded_name ? ' by ' . $proposal->responded_name : '' }}{{ $proposal->response_note ? ' – ' . $proposal->response_note : '' }}@endif
                </span>
            @else
                <span class="text-muted">The patient can open it on their phone, accept or decline, and pay through your payment link.</span>
            @endif
        </div>
        <form method="POST" action="{{ route('proposal.share', $proposal->id) }}" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:8px;">
            @csrf
            <input type="url" name="payment_url" class="form-control form-control-sm" style="max-width:360px;" value="{{ old('payment_url', $proposal->payment_url) }}" placeholder="Payment link (optional): https://… bKash / SSLCommerz">
            <label class="mb-0" style="font-weight:400;"><input type="checkbox" name="send_sms" value="1"> SMS the link to the patient</label>
            <button type="submit" class="btn btn-outline-primary btn-sm" id="proposal-share">{{ $proposal->public_token ? 'Update link' : 'Create link' }}</button>
        </form>
    </div>
    @endcan

    {{-- Official A4 Quotation Sheet --}}
    <div class="page-container">

        {{-- Clinic Letterhead Header --}}
        <div class="clinic-header">
            <div class="clinic-logo">
                @if(!empty($setting->logo))
                    <img src="{{ asset('file/setting/' . $setting->logo) }}" alt="Logo">
                @else
                    <div style="font-size: 24px; font-weight: 800; color: var(--brand-primary); letter-spacing: -0.5px;">
                        <i class="fas fa-ear-listen mr-2"></i>{{ $setting->company_name ?? 'HEARING CARE CLINIC' }}
                    </div>
                @endif
            </div>
            <div class="clinic-info">
                <div class="clinic-name">{{ $setting->company_name ?? 'ZenHear Hearing Care' }}</div>
                <div class="clinic-slogan">{{ $setting->partner_slogan ?? 'Audiological Diagnosis, Digital Hearing Aids & Rehabilitation' }}</div>
                <div>{{ $proposal->vendor ? $proposal->vendor->address : ($setting->address ?? 'Dhaka, Bangladesh') }}</div>
                <div>Tel: {{ $proposal->vendor ? $proposal->vendor->phone : ($setting->phone ?? '+880 1700-000000') }} | Email: {{ $setting->email ?? 'info@zenhear.com' }}</div>
                @if(!empty($setting->website_url))
                    <div>Website: {{ $setting->website_url }}</div>
                @endif
            </div>
        </div>

        {{-- Document Title Bar --}}
        <div class="doc-title-bar">
            <div class="doc-title-text">
                <i class="fas fa-file-medical-alt mr-1"></i> Hearing Care Treatment Quotation
            </div>
            <div class="quote-no">
                {{ $proposal->proposal_no ?: 'QUO-' . str_pad($proposal->id, 5, '0', STR_PAD_LEFT) }}
            </div>
        </div>

        {{-- Two-Column Meta Grid --}}
        <div class="meta-grid">
            {{-- Patient / Recipient Box --}}
            <div class="meta-card">
                <div class="meta-card-title"><i class="fas fa-user-injured text-primary"></i> Patient Information</div>
                <div class="meta-row">
                    <span class="meta-label">Patient Name:</span>
                    <span class="meta-value">{{ $proposal->recipient_display_name }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Contact Phone:</span>
                    <span class="meta-value">{{ $proposal->recipient_phone_display }}</span>
                </div>
                @if($proposal->recipient_email_display && $proposal->recipient_email_display !== 'N/A')
                <div class="meta-row">
                    <span class="meta-label">Email:</span>
                    <span class="meta-value">{{ $proposal->recipient_email_display }}</span>
                </div>
                @endif
                @if($proposal->recipient_address || ($proposal->lead && $proposal->lead->address))
                <div class="meta-row">
                    <span class="meta-label">Address:</span>
                    <span class="meta-value" style="max-width: 60%;">{{ $proposal->recipient_address ?: $proposal->lead->address }}</span>
                </div>
                @endif
                <div class="meta-row" style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #e2e8f0;">
                    <span class="meta-label">Ref Doctor:</span>
                    <span class="meta-value" style="color: #0f766e;">{{ $proposal->doctor ? $proposal->doctor->name : 'Direct Consultation' }}</span>
                </div>
            </div>

            {{-- Quote Details Box --}}
            <div class="meta-card">
                <div class="meta-card-title"><i class="fas fa-info-circle text-info"></i> Quotation Metadata</div>
                <div class="meta-row">
                    <span class="meta-label">Quotation Date:</span>
                    <span class="meta-value">{{ $proposal->proposal_date ? $proposal->proposal_date->format('F d, Y') : date('F d, Y') }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Valid Until:</span>
                    <span class="meta-value">{{ $proposal->valid_until ? $proposal->valid_until->format('F d, Y') : '30 Days from Issue' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Consulting Branch:</span>
                    <span class="meta-value">{{ $proposal->vendor ? $proposal->vendor->name : 'Head Office' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Quotation Status:</span>
                    <span class="meta-value">
                        <span class="badge" style="background: {{ $statusEnum->color() }}; color: #ffffff; font-size: 10px;">
                            {{ $proposal->status }}
                        </span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Treatment Title Banner --}}
        @if($proposal->title)
        <div class="treatment-goal">
            <h4><i class="fas fa-bullseye text-primary mr-1"></i> {{ $proposal->title }}</h4>
            <p>Tailored audiological rehabilitation plan based on patient hearing thresholds, ear anatomy, and communication lifestyle.</p>
        </div>
        @endif

        {{-- Section 1: Hearing Devices & Technology (if present) --}}
        @php
            $prodItems = $proposal->items->where('item_type', 'product');
            $svcItems = $proposal->items->where('item_type', 'service');
            $customItems = $proposal->items->where('item_type', 'custom');
        @endphp

        @if($prodItems->isNotEmpty())
            <div class="section-header">
                <i class="fas fa-box text-primary"></i> Part A: Digital Hearing Instruments &amp; Accessories
            </div>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">SL</th>
                        <th>Device Model &amp; Specifications</th>
                        <th style="width: 130px; text-align: center;">Warranty Coverage</th>
                        <th style="width: 60px; text-align: center;">Qty</th>
                        <th style="width: 100px; text-align: right;">Unit Price (৳)</th>
                        <th style="width: 90px; text-align: right;">Discount (৳)</th>
                        <th style="width: 110px; text-align: right;">Total (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($prodItems as $idx => $it)
                    <tr>
                        <td style="text-align: center; color: var(--brand-gray);">{{ ++$idx }}</td>
                        <td>
                            <strong>{{ $it->item_name }}</strong>
                            @if($it->description)
                                <div style="font-size: 11px; color: var(--brand-gray);">{{ $it->description }}</div>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <span class="warranty-tag">
                                <i class="fas fa-shield-alt mr-1"></i>{{ $it->warranty_months ?: 24 }} Months
                            </span>
                        </td>
                        <td style="text-align: center;">{{ (float) $it->qty }}</td>
                        <td style="text-align: right;">{{ number_format($it->unit_price, 2) }}</td>
                        <td style="text-align: right;">
                            @if($it->discount > 0)
                                {{ number_format($it->discount, 2) }}
                                @if($it->discount_percent > 0)
                                    <div style="font-size: 10px; color: var(--brand-gray);">({{ rtrim(rtrim(number_format($it->discount_percent, 2), '0'), '.') }}%)</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: 700;">{{ number_format($it->total_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Section 2: Clinical & Audiology Services (if present) --}}
        @if($svcItems->isNotEmpty())
            <div class="section-header">
                <i class="fas fa-stethoscope text-success"></i> Part B: Audiological Diagnostics &amp; Clinical Fitting
            </div>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">SL</th>
                        <th>Clinical Procedure / Session</th>
                        <th style="width: 60px; text-align: center;">Sessions</th>
                        <th style="width: 100px; text-align: right;">Rate (৳)</th>
                        <th style="width: 90px; text-align: right;">Discount (৳)</th>
                        <th style="width: 110px; text-align: right;">Total (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($svcItems as $sIdx => $it)
                    <tr>
                        <td style="text-align: center; color: var(--brand-gray);">{{ ++$sIdx }}</td>
                        <td>
                            <strong>{{ $it->item_name }}</strong>
                            @if($it->description)
                                <div style="font-size: 11px; color: var(--brand-gray);">{{ $it->description }}</div>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ (float) $it->qty }}</td>
                        <td style="text-align: right;">{{ number_format($it->unit_price, 2) }}</td>
                        <td style="text-align: right;">
                            @if($it->discount > 0)
                                {{ number_format($it->discount, 2) }}
                                @if($it->discount_percent > 0)
                                    <div style="font-size: 10px; color: var(--brand-gray);">({{ rtrim(rtrim(number_format($it->discount_percent, 2), '0'), '.') }}%)</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: 700;">{{ number_format($it->total_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Section 3: Custom items or unclassified items (if present) --}}
        @if($customItems->isNotEmpty() || ($prodItems->isEmpty() && $svcItems->isEmpty() && $proposal->items->isNotEmpty()))
            @php
                $otherItems = $customItems->isNotEmpty() ? $customItems : $proposal->items;
            @endphp
            <div class="section-header">
                <i class="fas fa-list text-secondary"></i> Part C: Services &amp; Care Details
            </div>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th style="width: 35px; text-align: center;">SL</th>
                        <th>Item Description</th>
                        <th style="width: 60px; text-align: center;">Qty</th>
                        <th style="width: 100px; text-align: right;">Price (৳)</th>
                        <th style="width: 90px; text-align: right;">Discount (৳)</th>
                        <th style="width: 110px; text-align: right;">Total (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($otherItems as $cIdx => $it)
                    <tr>
                        <td style="text-align: center; color: var(--brand-gray);">{{ ++$cIdx }}</td>
                        <td>
                            <strong>{{ $it->item_name }}</strong>
                            @if($it->description)
                                <div style="font-size: 11px; color: var(--brand-gray);">{{ $it->description }}</div>
                            @endif
                        </td>
                        <td style="text-align: center;">{{ (float) $it->qty }}</td>
                        <td style="text-align: right;">{{ number_format($it->unit_price, 2) }}</td>
                        <td style="text-align: right;">
                            @if($it->discount > 0)
                                {{ number_format($it->discount, 2) }}
                                @if($it->discount_percent > 0)
                                    <div style="font-size: 10px; color: var(--brand-gray);">({{ rtrim(rtrim(number_format($it->discount_percent, 2), '0'), '.') }}%)</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: 700;">{{ number_format($it->total_price, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Rich-Text Content (if filled) --}}
        @if(!empty($proposal->content))
        <div style="font-size: 12px; line-height: 1.6; margin: 16px 0; background: #fafafa; border: 1px solid #e5e5e5; padding: 12px; border-radius: 6px;">
            {!! \App\Support\SafeHtml::clean($proposal->content) !!}
        </div>
        @endif

        {{-- Financial Summary Block --}}
        <div class="summary-container">
            <table class="summary-table">
                <tr>
                    <td class="meta-label">Subtotal:</td>
                    <td style="text-align: right; font-weight: 600;">৳{{ number_format($proposal->subtotal ?: $proposal->total_amount, 2) }}</td>
                </tr>
                @if($proposal->discount_amount > 0)
                @php
                    $appliedDiscount = $proposal->discount_amount;
                    $appliedPercent = $proposal->discount_percent ?: ($proposal->subtotal > 0 ? round(($appliedDiscount / $proposal->subtotal) * 100, 2) : 0);
                @endphp
                <tr>
                    <td class="meta-label">Special Discount{{ $appliedPercent > 0 ? ' (' . rtrim(rtrim(number_format($appliedPercent, 2), '0'), '.') . '%)' : '' }}:</td>
                    <td style="text-align: right; color: #16a34a; font-weight: 600;">-৳{{ number_format($appliedDiscount, 2) }}</td>
                </tr>
                @endif
                @if($proposal->vat_amount > 0)
                <tr>
                    <td class="meta-label">VAT / Tax:</td>
                    <td style="text-align: right; font-weight: 600;">৳{{ number_format($proposal->vat_amount, 2) }}</td>
                </tr>
                @endif
                <tr class="grand-total-row">
                    <td>Grand Total Payable:</td>
                    <td style="text-align: right;">৳{{ number_format($proposal->grand_total ?: $proposal->total_amount, 2) }}</td>
                </tr>
            </table>
        </div>

        {{-- Clinical Warranty & Aftercare Terms Box --}}
        <div class="terms-box">
            <div class="terms-title"><i class="fas fa-award text-warning mr-1"></i> Clinical Warranty, Aftercare &amp; Policies</div>
            {!! nl2br(e($proposal->terms_and_conditions ?: "• Includes 2 Years manufacturer warranty on digital hearing aid instruments.\n• Complimentary 3 Trial & Tuning sessions within 60 days of device delivery.\n• Free lifetime hearing aid cleaning, dehumidification & audiological check-ups.\n• Quotation validity: 30 days. Payment terms: 50% advance upon order placement.")) !!}
        </div>

        {{-- Signature Blocks --}}
        <div class="signatures-grid">
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-title">Authorized Clinical Audiologist</div>
                <div class="sig-sub">{{ $setting->company_name ?? 'Hearing Care Specialist' }}</div>
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-title">Patient / Legal Guardian Acceptance</div>
                <div class="sig-sub">Signature &amp; Date</div>
            </div>
        </div>

    </div>

</body>
</html>
