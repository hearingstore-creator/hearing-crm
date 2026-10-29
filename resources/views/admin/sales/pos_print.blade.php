<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    <title>Receipt #{{ $saleMaster->sale_invoice_no }} - {{ $setting->company_name ?? 'POS Receipt' }}</title>
    <style>
        @media screen {
            body {
                background: #f1f5f9;
                padding: 24px 12px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                margin: 0;
            }
            .pos-toolbar {
                width: 100%;
                max-width: 320px;
                margin: 0 auto 16px;
                display: flex;
                gap: 8px;
                justify-content: center;
            }
            .pos-toolbar button, .pos-toolbar a {
                padding: 8px 14px;
                font-size: 13px;
                font-weight: 600;
                border-radius: 6px;
                border: 1px solid transparent;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .pos-toolbar .btn-print {
                background: #2563eb;
                color: #ffffff;
            }
            .pos-toolbar .btn-print:hover {
                background: #1d4ed8;
            }
            .pos-toolbar .btn-close {
                background: #ffffff;
                color: #334155;
                border-color: #cbd5e1;
            }
            .pos-toolbar .btn-close:hover {
                background: #f8fafc;
            }
            .pos-print {
                background: #ffffff;
                padding: 18px 14px;
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
                border-radius: 8px;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .pos-print {
                box-shadow: none !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 80mm !important;
                margin: 0 auto !important;
            }
        }

        .pos-print {
            font-size: 12px;
            color: #0f172a;
            line-height: 1.35;
        }

        .pos-print table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .pos-print th {
            border-top: 1px dashed #334155;
            border-bottom: 1px dashed #334155;
            padding: 4px 2px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .pos-print td {
            padding: 4px 2px;
            font-size: 11px;
            vertical-align: top;
        }

        .pos-print .item-name-cell {
            word-break: break-word;
            font-weight: 600;
        }

        .pos-print .item-sub {
            font-size: 9.5px;
            color: #64748b;
            font-weight: normal;
        }

        .pos-print .memo-info {
            border-top: 1px dashed #334155;
            border-bottom: 1px dashed #334155;
            padding: 6px 0;
            margin: 8px 0;
            font-size: 11px;
            text-align: left;
        }

        .pos-print .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }

        .pos-print .last-part td {
            border-top: 1px dashed #334155;
            padding-top: 6px;
        }

        .pos-print .total-row td {
            font-size: 11.5px;
        }

        .pos-print .grand-total td {
            border-top: 1px solid #0f172a;
            border-bottom: 1px solid #0f172a;
            font-size: 12.5px;
            font-weight: 700;
            padding: 4px 2px;
        }

        .pos-print .text-right {
            text-align: right;
        }

        .pos-print .text-center {
            text-align: center;
        }
    </style>
</head>

<body>
    {{-- Screen-only toolbar --}}
    <div class="pos-toolbar no-print">
        <button type="button" class="btn-print" onclick="window.print()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print Receipt
        </button>
        <button type="button" class="btn-close" onclick="window.close()">Close</button>
    </div>

    @php
        $branch = $saleMaster->vendor;
        $isBranch = !empty($branch) && ($branch->type === 'vendor');
        $hqLogo = !empty($setting->logo) ? asset($setting->logo) : null;
        $branchLogo = ($isBranch && !empty($branch->logo)) ? asset($branch->logo) : $hqLogo;
        $branchName = $isBranch ? $branch->name : ($setting->company_name ?? 'Hearing Store');
        $branchAddress = $isBranch ? ($branch->address ?? $setting->address) : $setting->address;
        $branchMobile = $isBranch ? ($branch->mobile_no ?? $setting->mobile) : $setting->mobile;
        $websiteUrl = $setting->website_url ?? 'www.hearingstore.bd';
        $partnerSlogan = $setting->partner_slogan ?? 'An Authorized Partner of Hearing Store';

        $subtotal = 0;
        $subTotalDiscount = 0;

        $productItems = [];
        foreach ($saleDetail ?? [] as $item) {
            $discTaka = (($item->qty * $item->mrp_price) / 100) * ($item->discount ?? 0);
            $lineTotal = ($item->qty * $item->mrp_price) - $discTaka;
            $subtotal += $lineTotal;
            $subTotalDiscount += $discTaka;

            $name = $item->product?->model ?? ($item->product?->category?->category_name ?? $item->product?->category?->name ?? 'Product');
            $serials = is_array($item->serial_no) ? implode(', ', $item->serial_no) : $item->serial_no;

            $productItems[] = [
                'name' => $name,
                'serial' => $serials,
                'batch' => $item->batch?->batch_no ?? null,
                'qty' => $item->qty,
                'price' => (float) $item->mrp_price,
                'disc' => $discTaka,
                'disc_pct' => (float) ($item->discount ?? 0),
                'total' => $lineTotal,
            ];
        }

        $serviceItems = [];
        foreach ($serviceDetails ?? [] as $item) {
            $discTaka = (($item->qty * $item->service_price) / 100) * ($item->discount ?? 0);
            $lineTotal = ($item->qty * $item->service_price) - $discTaka;
            $subtotal += $lineTotal;
            $subTotalDiscount += $discTaka;

            $name = $item->serviceType?->service_type_name ?? 'Service';

            $serviceItems[] = [
                'name' => $name,
                'serial' => null,
                'batch' => null,
                'qty' => $item->qty,
                'price' => (float) $item->service_price,
                'disc' => $discTaka,
                'disc_pct' => (float) ($item->discount ?? 0),
                'total' => $lineTotal,
            ];
        }

        $allItems = array_merge($productItems, $serviceItems);

        $invoiceDiscount = max(0, (float) $saleMaster->total_discount - $subTotalDiscount);
        $vat = (float) ($saleMaster->vat ?? 0);
        $grandTotal = (float) $saleMaster->total_amount;
        $computedTotal = $subtotal - $invoiceDiscount + $vat;
        $pointsRedeemed = max(0, $computedTotal - $grandTotal);
        $paid = (float) ($saleMaster->paid ?? 0);
        $due = max(0, $grandTotal - $paid);

        $creatorName = $saleMaster->creator?->name ?? (Auth::user()?->name ?? 'Staff');
        $customerName = $saleMaster->customer?->name ?? 'Walk-in Customer';
        $customerPhone = $saleMaster->customer?->lead?->phone ?? null;
        $doctorName = $saleMaster->doctor?->name ?? null;
        $paymentMethod = \App\Classes\Helper::paymentType()[$saleMaster->payment_id] ?? null;
    @endphp

    <div class="pos-print">
        {{-- Header / Brand --}}
        <div class="text-center" style="margin-bottom: 8px;">
            @if (!empty($branchLogo))
                <img src="{{ $branchLogo }}" alt="{{ $branchName }}" style="max-width: 130px; max-height: 48px; object-fit: contain; display: block; margin: 0 auto 4px;">
            @endif
            <strong class="company-name" style="font-size: 15px; display: block; font-weight: 700;">{{ $branchName }}</strong>
            @if ($isBranch)
                <div style="font-size: 9.5px; font-weight: 700; color: #475569; margin: 2px 0;">* {{ $partnerSlogan }} *</div>
            @endif
            @if (!empty($branchAddress))
                <div style="font-size: 10.5px; color: #334155;">{{ $branchAddress }}</div>
            @endif
            @if (!empty($branchMobile))
                <div style="font-size: 10.5px; color: #334155;">Tel: {{ $branchMobile }}</div>
            @endif
            @if (!empty($websiteUrl))
                <div style="font-size: 10px; font-weight: 600; color: #475569;">{{ $websiteUrl }}</div>
            @endif
        </div>

        @if ($saleMaster->trashed())
            <div id="pos-cancelled-stamp" style="border: 2px solid #b91c1c; color: #b91c1c; text-align: center; font-weight: 800; letter-spacing: 2px; padding: 3px; margin: 6px 0;">CANCELLED · NOT VALID</div>
        @endif

        {{-- Memo Info --}}
        <div class="memo-info">
            <div class="meta-row">
                <span>Invoice: <strong>{{ $saleMaster->sale_invoice_no }}</strong></span>
                <span>{{ $saleMaster->date ? date('d M Y', strtotime($saleMaster->date)) : date('d M Y') }}</span>
            </div>
            <div class="meta-row">
                <span>Customer: <strong>{{ $customerName }}</strong></span>
                @if ($customerPhone)
                    <span>{{ $customerPhone }}</span>
                @endif
            </div>
            @if ($doctorName)
                <div class="meta-row" style="font-size: 10px; color: #475569;">
                    <span>Ref Doctor: {{ $doctorName }}</span>
                    @if ($saleMaster->doctorChamber)
                        <span>({{ $saleMaster->doctorChamber->chamber_name }})</span>
                    @endif
                </div>
            @endif
            @if ($paymentMethod)
                <div class="meta-row" style="font-size: 10px; color: #475569;">
                    <span>Payment: {{ $paymentMethod }}</span>
                    <span>Served By: {{ $creatorName }}</span>
                </div>
            @endif
            @if ($saleMaster->delivery_date_time)
                <div class="meta-row" style="font-size: 10.5px; font-weight: 600; color: #0f172a; border-top: 1px dashed #cbd5e1; padding-top: 3px; margin-top: 3px;">
                    <span>{{ $saleMaster->isServiceSale() ? 'Report Ready:' : 'Promised Fitting:' }}</span>
                    <span>{{ $saleMaster->delivery_date_time->format('d M Y, h:i A') }}</span>
                </div>
            @endif
            @if ($saleMaster->lab_name)
                <div class="meta-row" style="font-size: 10px; color: #475569;">
                    <span>Lab:</span>
                    <span>{{ $saleMaster->lab_name }}</span>
                </div>
            @endif
        </div>

        {{-- Items Table --}}
        <table>
            <thead>
                <tr class="item">
                    <th style="text-align: left; width: 44%;">Item</th>
                    <th class="text-center" style="width: 12%;">Qty</th>
                    <th class="text-right" style="width: 20%;">Price</th>
                    <th class="text-right" style="width: 24%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($allItems as $item)
                    <tr>
                        <td class="item-name-cell">
                            {{ $item['name'] }}
                            @if (!empty($item['serial']))
                                <div class="item-sub">S/N: {{ $item['serial'] }}</div>
                            @endif
                            @if (!empty($item['batch']))
                                <div class="item-sub">Batch: {{ $item['batch'] }}</div>
                            @endif
                            @if ($item['disc'] > 0)
                                <div class="item-sub" style="color: #15803d;">Disc: -{{ number_format($item['disc'], 2) }} ({{ rtrim(rtrim(number_format($item['disc_pct'], 2), '0'), '.') }}%)</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $item['qty'] }}</td>
                        <td class="text-right">{{ number_format($item['price'], 2) }}</td>
                        <td class="text-right">{{ number_format($item['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center" style="padding: 10px 0; color: #64748b;">No items recorded</td>
                    </tr>
                @endforelse

                {{-- Totals --}}
                <tr class="last-part total-row">
                    <td colspan="2" style="font-size: 10.5px; color: #64748b;">Total Items: {{ count($allItems) }}</td>
                    <td class="text-right">Sub Total:</td>
                    <td class="text-right">
                        <nobr>{{ number_format($subtotal, 2) }}</nobr>
                    </td>
                </tr>

                @if ($vat > 0)
                    <tr class="total-row">
                        <td colspan="2"></td>
                        <td class="text-right">VAT:</td>
                        <td class="text-right">
                            <nobr>+{{ number_format($vat, 2) }}</nobr>
                        </td>
                    </tr>
                @endif

                @if ($invoiceDiscount > 0.009)
                    <tr class="total-row">
                        <td colspan="2"></td>
                        <td class="text-right">Inv Disc:</td>
                        <td class="text-right" style="color: #15803d;">
                            <nobr>-{{ number_format($invoiceDiscount, 2) }}</nobr>
                        </td>
                    </tr>
                @endif

                @if ($pointsRedeemed > 0.009)
                    <tr class="total-row">
                        <td colspan="2"></td>
                        <td class="text-right">Points:</td>
                        <td class="text-right" style="color: #15803d;">
                            <nobr>-{{ number_format($pointsRedeemed, 2) }}</nobr>
                        </td>
                    </tr>
                @endif

                <tr class="grand-total">
                    <td colspan="2" style="text-transform: uppercase;">Total</td>
                    <td class="text-right" style="text-transform: uppercase;">৳</td>
                    <td class="text-right">
                        <nobr>{{ number_format($grandTotal, 2) }}</nobr>
                    </td>
                </tr>

                <tr class="total-row">
                    <td colspan="2"></td>
                    <td class="text-right">Paid:</td>
                    <td class="text-right">
                        <nobr>{{ number_format($paid, 2) }}</nobr>
                    </td>
                </tr>

                <tr class="total-row">
                    <td colspan="2"></td>
                    <td class="text-right"><strong>{{ $due > 0.009 ? 'Due:' : 'Status:' }}</strong></td>
                    <td class="text-right">
                        @if ($due > 0.009)
                            <strong style="color: #b91c1c;"><nobr>৳ {{ number_format($due, 2) }}</nobr></strong>
                            @if ($saleMaster->due_date)
                                <div style="font-size: 9.5px; color: #b91c1c;">Due: {{ $saleMaster->due_date->format('d M Y') }}</div>
                            @endif
                        @else
                            <strong style="color: #15803d;">PAID</strong>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Footer Note & Barcode --}}
        <div class="text-center" style="margin-top: 14px; border-top: 1px dashed #334155; padding-top: 8px;">
            <strong style="font-size: 11px;">Thanks for your purchase!</strong>
            @if ($saleMaster->details)
                <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">{{ $saleMaster->details }}</div>
            @endif
            <div style="margin: 8px auto 4px; text-align: center;">
                <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($saleMaster->sale_invoice_no, 'C128', 1, 32) }}" alt="Barcode" style="max-height: 32px; width: auto; max-width: 170px; display: inline-block;" />
                <div style="font-size: 9px; font-weight: 700; color: #0f172a; letter-spacing: 1px; margin-top: 2px;">*{{ $saleMaster->sale_invoice_no }}*</div>
            </div>
            <div style="font-size: 9px; color: #94a3b8; margin-top: 4px;">{{ $saleMaster->sale_invoice_no }} &middot; Printed {{ date('d-m-Y h:i A') }}</div>
        </div>
    </div>

    <script src="{{ asset('assets/receipt-print.js') }}"></script>
</body>

</html>
