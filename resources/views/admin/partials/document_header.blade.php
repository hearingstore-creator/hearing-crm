{{-- Reusable Dual-Branding Document Header (Option 1: Dual-Wing Co-Branding)
     Props:
       - $vendor / $branch: Branch User model (optional, nullable)
       - $setting: HQ Setting model (optional, fallback to Setting::first())
       - $documentType: string (e.g. 'TAX INVOICE', 'CREDIT NOTE', 'PURCHASE ORDER', 'RECEIPT')
       - $documentNo: string (e.g. '#6AD075A201581')
       - $date: DateTime/string (optional)
       - $status: string (optional, e.g. 'Paid', 'Partially Paid', 'Completed')
       - $statusClass: string (optional, e.g. 'paid', 'partial', 'unpaid', 'completed')
--}}
@php
    $setting = $setting ?? \App\Models\Setting::first();
    $branch = $vendor ?? ($branch ?? null);
    $isBranch = !empty($branch) && ($branch->type === 'vendor');

    $hqName = $setting->company_name ?? 'Hearing Store';
    $hqLogo = !empty($setting->logo) ? asset($setting->logo) : null;
    $websiteUrl = $setting->website_url ?? 'www.hearingstore.bd';
    $partnerSlogan = $setting->partner_slogan ?? 'An Authorized Partner of Hearing Store';
    $hqPhone = $setting->phone ?? $setting->mobile;
    $hqEmail = $setting->email ?? null;

    if ($isBranch) {
        $branchName = $branch->name ?? 'Partner Clinic';
        $branchLogo = !empty($branch->logo) ? asset($branch->logo) : $hqLogo;
        $branchAddress = $branch->address ?? ($setting->address ?? '');
        $branchPhone = $branch->mobile_no ?? ($branch->phone ?? ($setting->mobile ?? ''));
        $branchEmail = $branch->email ?? ($setting->email ?? '');
    } else {
        $branchName = $hqName;
        $branchLogo = $hqLogo;
        $branchAddress = $setting->address ?? '';
        $branchPhone = $hqPhone ?? '';
        $branchEmail = $hqEmail ?? '';
    }

    $formattedDate = '';
    if (!empty($date)) {
        if ($date instanceof \DateTimeInterface) {
            $formattedDate = $date->format('d M Y');
        } else {
            $formattedDate = date('d M Y', strtotime($date));
        }
    }
@endphp

<div class="hs-doc-header">
    <div class="hs-doc-header-inner">

        {{-- Left Wing: Branch / Local Clinic Letterhead --}}
        <div class="hs-wing hs-wing-left">
            <div class="hs-brand-wrap">
                @if (!empty($branchLogo))
                    <img src="{{ $branchLogo }}" alt="{{ $branchName }}" class="hs-brand-logo">
                @else
                    <div class="hs-brand-initial">{{ strtoupper(mb_substr($branchName, 0, 1)) }}</div>
                @endif
                <div class="hs-brand-text">
                    <h3 class="hs-branch-title">{{ $branchName }}</h3>
                    @if ($isBranch)
                        <div class="hs-partner-pill-inline d-print-none">
                            <i class="fas fa-handshake mr-1"></i>{{ $partnerSlogan }}
                        </div>
                    @endif
                    @if (!empty($branchAddress))
                        <div class="hs-contact-line">
                            <i class="fas fa-map-marker-alt"></i> <span>{{ $branchAddress }}</span>
                        </div>
                    @endif
                    <div class="hs-contact-line">
                        @if (!empty($branchPhone))
                            <span class="mr-2"><i class="fas fa-phone-alt"></i> {{ $branchPhone }}</span>
                        @endif
                        @if (!empty($branchEmail))
                            <span><i class="fas fa-envelope"></i> {{ $branchEmail }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Center: Document Type, Number, Date & Status --}}
        <div class="hs-doc-center">
            <div class="hs-doc-title-badge">{{ $documentType ?? 'DOCUMENT' }}</div>
            <div class="hs-doc-number">{{ $documentNo ?? '' }}</div>
            @php
                $cleanBarcodeVal = !empty($barcodeValue) ? $barcodeValue : (!empty($documentNo) ? trim(ltrim($documentNo, '#')) : null);
            @endphp
            @if (!empty($cleanBarcodeVal))
                <div class="hs-doc-barcode" style="margin: 4px 0 3px; text-align: center;">
                    <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($cleanBarcodeVal, 'C128', 1, 26) }}" alt="Barcode" style="max-height: 26px; width: auto; max-width: 150px; display: inline-block;" />
                </div>
            @endif
            @if (!empty($formattedDate))
                <div class="hs-doc-date">Date: <strong>{{ $formattedDate }}</strong></div>
            @endif
            @if (!empty($status))
                <div class="hs-status-pill hs-status-{{ strtolower($statusClass ?? $status) }}">
                    {{ $status }}
                </div>
            @endif
        </div>

        {{-- Right Wing: Hearing Store HQ Official Endorsement --}}
        <div class="hs-wing hs-wing-right">
            @if ($isBranch)
                {{-- Branch Document: Endorsed by Hearing Store --}}
                <div class="hs-hq-endorsement">
                    <div class="hs-hq-logo-row">
                        @if (!empty($hqLogo))
                            <img src="{{ $hqLogo }}" alt="{{ $hqName }}" class="hs-hq-logo">
                        @else
                            <span class="font-weight-bold text-dark" style="font-size: 15px; letter-spacing: 0.5px;">{{ $hqName }}</span>
                        @endif
                    </div>
                    <div class="hs-partner-badge">
                        <i class="fas fa-certificate mr-1"></i>{{ $partnerSlogan }}
                    </div>
                    <div class="hs-hq-web-line">
                        <i class="fas fa-globe mr-1"></i><a href="https://{{ ltrim($websiteUrl, 'https://') }}" target="_blank">{{ $websiteUrl }}</a>
                    </div>
                    @if (!empty($hqPhone))
                        <div class="hs-hq-care-line">
                            <i class="fas fa-headset mr-1"></i>Central Care: {{ $hqPhone }}
                        </div>
                    @endif
                </div>
            @else
                {{-- Head Office Document: Clean HQ Letterhead --}}
                <div class="hs-hq-endorsement text-right">
                    @if (!empty($hqLogo))
                        <img src="{{ $hqLogo }}" alt="{{ $hqName }}" class="hs-hq-logo mb-1">
                    @endif
                    <div class="hs-hq-web-line">
                        <i class="fas fa-globe mr-1"></i><a href="https://{{ ltrim($websiteUrl, 'https://') }}" target="_blank">{{ $websiteUrl }}</a>
                    </div>
                    @if (!empty($hqPhone))
                        <div class="hs-hq-care-line">
                            <i class="fas fa-headset mr-1"></i>Helpline: {{ $hqPhone }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

    </div>
</div>

<style>
.hs-doc-header {
    background: #ffffff;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 18px;
    margin-bottom: 22px;
    font-family: inherit;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}
.hs-doc-header-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}
.hs-wing {
    flex: 1;
}
.hs-wing-left {
    text-align: left;
}
.hs-wing-right {
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}
.hs-brand-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
}
.hs-brand-logo {
    max-height: 58px;
    max-width: 150px;
    object-fit: contain;
    flex-shrink: 0;
}
.hs-brand-initial {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    background: #4f46e5;
    color: #ffffff;
    font-weight: 800;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.hs-branch-title {
    margin: 0 0 3px 0;
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
    letter-spacing: -0.2px;
}
.hs-partner-pill-inline {
    display: inline-block;
    background: #f1f5f9;
    color: #475569;
    font-size: 10px;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 4px;
    margin-bottom: 4px;
}
.hs-contact-line {
    font-size: 11px;
    color: #475569;
    line-height: 1.45;
}
.hs-contact-line i {
    color: #64748b;
    width: 12px;
    margin-right: 2px;
}

/* Center Document Block */
.hs-doc-center {
    text-align: center;
    flex-shrink: 0;
    min-width: 170px;
}
.hs-doc-title-badge {
    display: inline-block;
    background: #0f172a;
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.5px;
    padding: 3px 12px;
    border-radius: 4px;
    text-transform: uppercase;
}
.hs-doc-number {
    font-size: 15px;
    font-weight: 800;
    color: #4f46e5;
    margin-top: 4px;
    letter-spacing: 0.5px;
}
.hs-doc-date {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}
.hs-status-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 9999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    margin-top: 4px;
    letter-spacing: 0.5px;
}
.hs-status-paid { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.hs-status-partial { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.hs-status-unpaid { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.hs-status-completed { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }

/* Right Wing: Hearing Store HQ */
.hs-hq-endorsement {
    text-align: right;
}
.hs-hq-logo {
    max-height: 42px;
    max-width: 140px;
    object-fit: contain;
    margin-bottom: 4px;
}
.hs-partner-badge {
    display: inline-block;
    background: #eef2ff;
    color: #3730a3;
    border: 1px solid #c7d2fe;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
}
.hs-partner-badge i {
    color: #4f46e5;
}
.hs-hq-web-line {
    font-size: 11px;
    font-weight: 700;
    color: #0f172a;
}
.hs-hq-web-line a {
    color: inherit;
    text-decoration: none;
}
.hs-hq-web-line a:hover {
    text-decoration: underline;
}
.hs-hq-care-line {
    font-size: 11px;
    color: #64748b;
    margin-top: 1px;
}

/* Print optimizations */
@media print {
    .hs-doc-header {
        border-bottom: 2px solid #334155 !important;
        margin-bottom: 16px !important;
        padding-bottom: 12px !important;
    }
    .hs-branch-title {
        color: #000000 !important;
    }
    .hs-doc-number {
        color: #000000 !important;
    }
    .hs-doc-title-badge {
        background: #000000 !important;
        color: #ffffff !important;
    }
    .hs-partner-badge {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border-color: #cbd5e1 !important;
    }
}
</style>
