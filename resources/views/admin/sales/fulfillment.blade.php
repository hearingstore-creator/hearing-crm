@extends('admin.layouts.master')
@section('page_title', 'Report & Delivery Hub')
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/sale'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
    <style>
        .hub-tabs {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .hub-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            color: var(--page-text-muted, #64748b);
            background: #fff;
            border: 1px solid var(--gray-200, #e2e8f0);
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .hub-tab-btn:hover {
            color: var(--accent-600, #4f46e5);
            border-color: var(--accent-300, #a5b4fc);
            background: var(--accent-50, #eef2ff);
            text-decoration: none;
        }
        .hub-tab-btn.active {
            color: #fff;
            background: var(--accent-600, #4f46e5);
            border-color: var(--accent-600, #4f46e5);
            box-shadow: 0 3px 6px rgba(79, 70, 229, 0.25);
            text-decoration: none;
        }
        .hub-tab-btn .badge-pill-count {
            display: inline-block;
            padding: 2px 8px;
            font-size: 11px;
            border-radius: 9999px;
            background: rgba(0,0,0,0.08);
        }
        .hub-tab-btn.active .badge-pill-count {
            background: rgba(255,255,255,0.25);
            color: #fff;
        }

        .status-pill-list {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 14px;
            overflow-x: auto;
            padding-bottom: 2px;
        }
        .status-sub-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #475569;
            background: #f1f5f9;
            border: 1px solid transparent;
            text-decoration: none;
            transition: all 0.12s ease;
            white-space: nowrap;
        }
        .status-sub-pill:hover {
            background: #e2e8f0;
            color: #0f172a;
            text-decoration: none;
        }
        .status-sub-pill.active {
            background: #0f172a;
            color: #fff;
            font-weight: 600;
        }

        .timing-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            line-height: 1.2;
        }
        .timing-tag.overdue {
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fecaca;
        }
        .timing-tag.today {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
        }
        .timing-tag.upcoming {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        .timing-tag.delivered {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .timing-tag.none {
            background: #f8fafc;
            color: #94a3b8;
            border: 1px solid #e2e8f0;
        }

        .btn-call-link, .btn-wa-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            font-size: 11px;
            text-decoration: none;
            transition: all 0.12s ease;
        }
        .btn-call-link {
            background: rgba(14, 165, 233, 0.12);
            color: #0284c7;
        }
        .btn-call-link:hover {
            background: #0284c7;
            color: #fff;
        }
        .btn-wa-link {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
        }
        .btn-wa-link:hover {
            background: #059669;
            color: #fff;
        }

        .quick-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .quick-action-btn.btn-ready {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .quick-action-btn.btn-ready:hover {
            background: #fde68a;
            color: #92400e;
        }
        .quick-action-btn.btn-lab {
            background: #f3e8ff;
            color: #7e22ce;
            border: 1px solid #e9d5ff;
        }
        .quick-action-btn.btn-lab:hover {
            background: #e9d5ff;
            color: #6b21a8;
        }
        .quick-action-btn.btn-deliver {
            background: #d1fae5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .quick-action-btn.btn-deliver:hover {
            background: #a7f3d0;
            color: #065f46;
        }

        .modal-order-summary {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .modal-order-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            text-align: center;
        }
        .modal-order-grid .lbl {
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 2px;
        }
        .modal-order-grid .val {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .modal-order-grid .val.due-red {
            color: #ef4444;
        }
        .modal-order-grid .val.paid-green {
            color: #10b981;
        }

        .time-preset-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .time-chip {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 500;
            color: #334155;
            cursor: pointer;
            transition: all 0.12s ease;
        }
        .time-chip:hover {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
        }

        .settle-due-card {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 8px;
            padding: 12px;
            margin-top: 14px;
        }
        .sms-alert-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 14px;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-clipboard-check" style="color: var(--accent-500); margin-right: 8px;"></i>Report &amp; Delivery Hub</h1>
            <p>Track hearing assessment reports, lab fabrication, ready-for-fitting devices, and collect due balance at handover</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/sales/due-list') }}" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-clock mr-1"></i>Due List
            </a>
            <a href="{{ url('admin/sale') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list mr-1"></i>All Sales
            </a>
            @can('permission','add_sale.create')
                <a href="{{ url('admin/sale/create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>New Sale
                </a>
            @endcan
        </div>
    </div>

    {{-- Summary / KPI Cards --}}
    @php
        $st = $stats ?? [];
        $currTab = $tab ?? 'services';
        $currStatus = $status ?? 'all';
    @endphp
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/fulfillment?tab=services&status=ready_for_delivery'),
            'color' => 'amber', 'icon' => 'fas fa-file-medical-alt', 'id' => 'kpi-reports-ready',
            'label' => 'Reports Ready for Pickup', 'value' => (string) ($st['service_ready'] ?? 0),
            'sub' => '<b>' . ($st['service_ordered'] ?? 0) . '</b> booked & awaiting test report',
            'active' => $currTab === 'services' && $currStatus === 'ready_for_delivery',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/fulfillment?tab=products&status=sent_to_lab'),
            'color' => 'purple', 'icon' => 'fas fa-flask', 'id' => 'kpi-products-lab',
            'label' => 'Sent to Lab / Fabrication', 'value' => (string) ($st['product_lab'] ?? 0),
            'sub' => 'Ear molds & custom shells in lab',
            'active' => $currTab === 'products' && $currStatus === 'sent_to_lab',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/fulfillment?tab=products&status=ready_for_delivery'),
            'color' => 'blue', 'icon' => 'fas fa-user-check', 'id' => 'kpi-products-ready',
            'label' => 'Ready for Fitting & Trial', 'value' => (string) ($st['product_ready'] ?? 0),
            'sub' => '<b>' . ($st['product_ordered'] ?? 0) . '</b> new orders placed',
            'active' => $currTab === 'products' && $currStatus === 'ready_for_delivery',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/fulfillment?status=delivered'),
            'color' => 'green', 'icon' => 'fas fa-check-double', 'id' => 'kpi-delivered-today',
            'label' => 'Delivered Today', 'value' => (string) ($st['delivered_today'] ?? 0),
            'sub' => 'Handed over to patients today',
            'active' => $currStatus === 'delivered',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/fulfillment?tab=all&status=all'),
            'color' => 'slate', 'icon' => 'fas fa-boxes', 'id' => 'kpi-total-pending',
            'label' => 'Active In-Progress', 'value' => (string) ($st['total_pending'] ?? 0),
            'sub' => 'Reports & device orders in pipeline',
            'active' => $currTab === 'all' && $currStatus === 'all',
        ])
    </div>

    {{-- Main Filter Tabs (Track A vs Track B) --}}
    <div class="hub-tabs crm-animate-in">
        <a href="{{ url('admin/sales/fulfillment?tab=services&status=' . ($currTab === 'services' ? $currStatus : 'all')) }}" 
           class="hub-tab-btn {{ $currTab === 'services' ? 'active' : '' }}">
            <i class="fas fa-stethoscope"></i>
            Hearing Assessment (Reports)
            <span class="badge-pill-count">{{ $st['service_total'] ?? 0 }}</span>
        </a>
        <a href="{{ url('admin/sales/fulfillment?tab=products&status=' . ($currTab === 'products' ? $currStatus : 'all')) }}" 
           class="hub-tab-btn {{ $currTab === 'products' ? 'active' : '' }}">
            <i class="fas fa-headset"></i>
            Hearing Aids &amp; Lab Orders
            <span class="badge-pill-count">{{ $st['product_total'] ?? 0 }}</span>
        </a>
        <a href="{{ url('admin/sales/fulfillment?tab=all&status=' . ($currTab === 'all' ? $currStatus : 'all')) }}" 
           class="hub-tab-btn {{ $currTab === 'all' ? 'active' : '' }}">
            <i class="fas fa-layer-group"></i>
            All Deliveries
            <span class="badge-pill-count">{{ ($st['service_total'] ?? 0) + ($st['product_total'] ?? 0) }}</span>
        </a>
    </div>

    {{-- Sub Status Pills for quick 1-click filtering --}}
    <div class="status-pill-list crm-animate-in">
        <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-right: 6px; letter-spacing: 0.5px;">Status:</span>
        <a href="{{ url('admin/sales/fulfillment?tab=' . $currTab . '&status=all') }}" class="status-sub-pill {{ $currStatus === 'all' ? 'active' : '' }}">
            All
        </a>
        @if ($currTab === 'services')
            <a href="{{ url('admin/sales/fulfillment?tab=services&status=ordered') }}" class="status-sub-pill {{ $currStatus === 'ordered' ? 'active' : '' }}">
                <i class="fas fa-clock" style="color:#3b82f6;"></i> Assessment Booked ({{ $st['service_ordered'] ?? 0 }})
            </a>
            <a href="{{ url('admin/sales/fulfillment?tab=services&status=ready_for_delivery') }}" class="status-sub-pill {{ $currStatus === 'ready_for_delivery' ? 'active' : '' }}">
                <i class="fas fa-bell" style="color:#f59e0b;"></i> Report Ready ({{ $st['service_ready'] ?? 0 }})
            </a>
            <a href="{{ url('admin/sales/fulfillment?tab=services&status=delivered') }}" class="status-sub-pill {{ $currStatus === 'delivered' ? 'active' : '' }}">
                <i class="fas fa-check-circle" style="color:#10b981;"></i> Report Delivered ({{ $st['service_delivered'] ?? 0 }})
            </a>
        @elseif ($currTab === 'products')
            <a href="{{ url('admin/sales/fulfillment?tab=products&status=ordered') }}" class="status-sub-pill {{ $currStatus === 'ordered' ? 'active' : '' }}">
                <i class="fas fa-clock" style="color:#3b82f6;"></i> Order Placed ({{ $st['product_ordered'] ?? 0 }})
            </a>
            <a href="{{ url('admin/sales/fulfillment?tab=products&status=sent_to_lab') }}" class="status-sub-pill {{ $currStatus === 'sent_to_lab' ? 'active' : '' }}">
                <i class="fas fa-flask" style="color:#8b5cf6;"></i> Sent to Lab ({{ $st['product_lab'] ?? 0 }})
            </a>
            <a href="{{ url('admin/sales/fulfillment?tab=products&status=ready_for_delivery') }}" class="status-sub-pill {{ $currStatus === 'ready_for_delivery' ? 'active' : '' }}">
                <i class="fas fa-bell" style="color:#f59e0b;"></i> Ready for Fitting ({{ $st['product_ready'] ?? 0 }})
            </a>
            <a href="{{ url('admin/sales/fulfillment?tab=products&status=delivered') }}" class="status-sub-pill {{ $currStatus === 'delivered' ? 'active' : '' }}">
                <i class="fas fa-check-circle" style="color:#10b981;"></i> Delivered &amp; Fitted ({{ $st['product_delivered'] ?? 0 }})
            </a>
        @else
            <a href="{{ url('admin/sales/fulfillment?tab=all&status=ordered') }}" class="status-sub-pill {{ $currStatus === 'ordered' ? 'active' : '' }}">Booked / Ordered</a>
            <a href="{{ url('admin/sales/fulfillment?tab=all&status=sent_to_lab') }}" class="status-sub-pill {{ $currStatus === 'sent_to_lab' ? 'active' : '' }}">In Lab</a>
            <a href="{{ url('admin/sales/fulfillment?tab=all&status=ready_for_delivery') }}" class="status-sub-pill {{ $currStatus === 'ready_for_delivery' ? 'active' : '' }}">Ready</a>
            <a href="{{ url('admin/sales/fulfillment?tab=all&status=delivered') }}" class="status-sub-pill {{ $currStatus === 'delivered' ? 'active' : '' }}">Delivered</a>
        @endif
    </div>

    {{-- Fast Barcode Scanner Station --}}
    <div class="card mb-3 border-0 shadow-sm crm-animate-in delay-1" style="border-radius: 12px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; overflow: hidden;">
        <div style="padding: 12px 18px;">
            <div class="row align-items-center">
                <div class="col-md-5 mb-2 mb-md-0">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.4); display: flex; align-items: center; justify-content: center; font-size: 16px; color: #60a5fa;">
                            <i class="fas fa-barcode"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 13.5px; letter-spacing: 0.3px; color: #fff;">Barcode Fast Scanner Station</div>
                            <div style="font-size: 11px; color: #94a3b8;">
                                <i class="fas fa-magic mr-1 text-info"></i>Point scanner gun at printed invoice or type # and press Enter
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); border-right: none; color: #38bdf8;">
                                <i class="fas fa-qrcode"></i>
                            </span>
                        </div>
                        <input type="text" 
                               id="hub_barcode_input" 
                               class="form-control barcode-scan-input" 
                               placeholder="📷 Scan invoice barcode or type invoice # / S/N (Press Enter)..." 
                               autocomplete="off"
                               style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); color: #fff; font-weight: 600; font-size: 13px;">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" id="btn_hub_barcode_scan" style="background: #3b82f6; border: none; font-weight: 600; font-size: 12.5px; padding: 0 16px;">
                                <i class="fas fa-search mr-1"></i>Scan &amp; Update
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['q','branch','start_date','end_date']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i>
            <span>Search &amp; Date Range Filters</span>
            <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['q','branch','start_date','end_date']) ? '' : 'display:none;' }}">
            <form method="GET" action="{{ url('admin/sales/fulfillment') }}">
                <input type="hidden" name="tab" value="{{ $currTab }}">
                <input type="hidden" name="status" value="{{ $currStatus }}">
                <div class="filter-row">
                    @if (!empty($allBranches))
                        <div>
                            <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Branch</label>
                            <select name="branch" class="form-control" id="filter-branch">
                                <option value="all" @selected(($branchFilter ?? 'all') === 'all')>All Branches</option>
                                <option value="hq" @selected(($branchFilter ?? '') === 'hq')>Head Office</option>
                                @foreach ($branches as $bid => $bname)
                                    <option value="{{ $bid }}" @selected((string) ($branchFilter ?? '') === (string) $bid)>{{ $bname }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div style="flex: 2; min-width: 240px;">
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Search</label>
                        <input type="text" name="q" class="form-control" placeholder="Invoice #, Patient Name, Phone, UID, Lab, Model…" value="{{ request('q') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">From Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">To Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit" title="Apply filter">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <a href="{{ url('admin/sales/fulfillment?tab=' . $currTab) }}" class="btn btn-secondary btn-sm" title="Clear filters">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Main Fulfillment Table --}}
    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-stream" style="color: var(--page-text-muted);"></i>
                @if ($currTab === 'services')
                    Hearing Assessment &amp; Service Deliveries
                @elseif ($currTab === 'products')
                    Hearing Aid &amp; Lab Fabrication Orders
                @else
                    All Deliveries &amp; Fulfillment
                @endif
                @if(isset($sales) && method_exists($sales, 'total'))
                    <span class="table-count">{{ $sales->total() }}</span>
                @endif
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">#</th>
                        <th style="min-width: 140px;">Invoice &amp; Date</th>
                        <th style="min-width: 200px;">Patient</th>
                        <th style="min-width: 230px;">
                            @if ($currTab === 'services')
                                Assessment / Service Items
                            @elseif ($currTab === 'products')
                                Products &amp; Lab
                            @else
                                Items &amp; Details
                            @endif
                        </th>
                        <th style="min-width: 170px;">Promised Delivery / Report</th>
                        <th style="min-width: 130px;">Financials</th>
                        <th style="min-width: 140px;">Fulfillment Status</th>
                        <th style="min-width: 160px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $key => $sale)
                        @php
                            $isService = $sale->isServiceSale();
                            $statusEnum = $sale->deliveryStatusEnum();
                            $due = round($sale->total_amount - $sale->paid, 2);
                            $phone = $sale->customer?->lead?->phone_number ?? $sale->customer?->phone ?? '';
                            $leadName = $sale->customer?->lead?->full_name ?? $sale->customer?->name ?? 'Walk-in Customer';
                            $waPhone = preg_replace('/[^0-9]/', '', $phone);
                            if (strlen($waPhone) === 11 && str_starts_with($waPhone, '01')) {
                                $waPhone = '88' . $waPhone;
                            }

                            // Calculate timing tag
                            $timingClass = 'none';
                            $timingText = 'Not scheduled';
                            if ($statusEnum === \App\Enums\DeliveryStatus::DELIVERED) {
                                $timingClass = 'delivered';
                                $timingText = 'Delivered ' . ($sale->delivered_at ? $sale->delivered_at->format('d M, h:i A') : '—');
                            } elseif ($sale->delivery_date_time) {
                                $now = now();
                                if ($sale->delivery_date_time->lt($now)) {
                                    $timingClass = 'overdue';
                                    $timingText = 'Overdue by ' . $sale->delivery_date_time->diffForHumans($now, true);
                                } elseif ($sale->delivery_date_time->isToday()) {
                                    $timingClass = 'today';
                                    $timingText = 'Due today at ' . $sale->delivery_date_time->format('h:i A');
                                } else {
                                    $timingClass = 'upcoming';
                                    $timingText = 'Due ' . $sale->delivery_date_time->format('d M, h:i A');
                                }
                            }
                        @endphp
                        <tr id="sale-row-{{ $sale->id }}">
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ $sales->firstItem() + $key }}</td>
                            
                            {{-- Invoice & Date --}}
                            <td>
                                <a href="{{ url('admin/sale/' . $sale->id) }}" style="font-weight: 700; color: var(--accent-600); font-size: 13px;">
                                    #{{ $sale->sale_invoice_no }}
                                </a>
                                <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                                    <i class="far fa-calendar-alt mr-1"></i>{{ $sale->date?->format('d M Y') }}
                                </div>
                                @if (!empty($allBranches) && $sale->vendor)
                                    <div style="font-size: 11px; color: #475569; margin-top: 2px;">
                                        <i class="fas fa-store mr-1"></i>{{ $sale->vendor->name }}
                                    </div>
                                @endif
                            </td>

                            {{-- Patient Details --}}
                            <td>
                                <div class="crm-name-cell">
                                    <div class="crm-avatar">{{ strtoupper(substr($leadName, 0, 1)) }}</div>
                                    <div class="crm-name-text">
                                        <span class="name" style="font-weight: 600; color: #0f172a;">{{ $leadName }}</span>
                                        @if ($sale->customer?->customer_uid)
                                            <span style="font-size: 10.5px; color: #64748b;">UID: {{ $sale->customer->customer_uid }}</span>
                                        @endif
                                        @if ($phone)
                                            <div style="display:flex; align-items:center; gap:6px; margin-top:3px;">
                                                <span style="font-size: 11.5px; color: #475569;">{{ $phone }}</span>
                                                <a href="tel:{{ $phone }}" class="btn-call-link" title="Call Patient"><i class="fas fa-phone-alt"></i></a>
                                                <a href="https://wa.me/{{ $waPhone }}" target="_blank" class="btn-wa-link" title="WhatsApp Message"><i class="fab fa-whatsapp"></i></a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Items & Details --}}
                            <td>
                                @if ($sale->serviceDetails->isNotEmpty())
                                    <div style="margin-bottom: 4px;">
                                        @foreach ($sale->serviceDetails as $sd)
                                            <span style="display:inline-block; background:#eef2ff; color:#4338ca; border-radius:4px; padding:2px 6px; font-size:11px; font-weight:600; margin-bottom:2px;">
                                                <i class="fas fa-stethoscope mr-1"></i>{{ $sd->serviceType?->service_type_name ?? 'Service' }} (x{{ $sd->qty }})
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($sale->saleDetails->isNotEmpty())
                                    <div>
                                        @foreach ($sale->saleDetails as $pd)
                                            <div style="font-size: 11.5px; color: #1e293b; font-weight: 500;">
                                                <i class="fas fa-tag mr-1" style="color:#64748b;"></i>
                                                {{ trim(($pd->product?->category?->category_name ?? '') . ' - ' . ($pd->product?->model ?? 'Product'), ' -') }}
                                                @if (!empty($pd->serial_no))
                                                    <small style="color:#64748b;">[S/N: {{ is_array($pd->serial_no) ? implode(', ', $pd->serial_no) : $pd->serial_no }}]</small>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($sale->lab_name)
                                    <div style="margin-top: 4px;">
                                        <span style="display:inline-flex; align-items:center; gap:4px; background:#f5f3ff; color:#7c3aed; border:1px solid #ddd6fe; border-radius:4px; padding:2px 6px; font-size:10.5px; font-weight:600;">
                                            <i class="fas fa-flask"></i> Lab: {{ $sale->lab_name }}
                                        </span>
                                    </div>
                                @endif
                            </td>

                            {{-- Delivery Time & Countdown --}}
                            <td>
                                @if ($sale->delivery_date_time)
                                    <div style="font-weight: 600; font-size: 12px; color: #0f172a;">
                                        <i class="far fa-clock mr-1" style="color: var(--accent-500);"></i>{{ $sale->delivery_date_time->format('d M Y, h:i A') }}
                                    </div>
                                @endif
                                <div style="margin-top: 3px;">
                                    <span class="timing-tag {{ $timingClass }}">
                                        @if ($timingClass === 'overdue')
                                            <i class="fas fa-exclamation-triangle"></i>
                                        @elseif ($timingClass === 'today')
                                            <i class="fas fa-bell"></i>
                                        @elseif ($timingClass === 'delivered')
                                            <i class="fas fa-check-circle"></i>
                                        @else
                                            <i class="far fa-clock"></i>
                                        @endif
                                        {{ $timingText }}
                                    </span>
                                </div>
                            </td>

                            {{-- Financials (Paid & Due Balance) --}}
                            <td>
                                <div style="font-size: 12px; font-weight: 600;">৳{{ number_format($sale->total_amount, 2) }}</div>
                                <div style="font-size: 11px; color: #10b981; font-weight: 500;">Paid: ৳{{ number_format($sale->paid, 2) }}</div>
                                @if ($due > 0.009)
                                    <span class="crm-status-badge badge-status-lost" style="font-size: 11px; padding: 2px 7px; margin-top: 3px;">
                                        <span class="dot"></span> ৳{{ number_format($due, 2) }} Due
                                    </span>
                                    @if ($sale->due_date)
                                        <div style="font-size: 10px; color: #b91c1c; font-weight: 500; margin-top: 2px;">
                                            Promise: {{ $sale->due_date->format('d M') }}
                                        </div>
                                    @endif
                                @else
                                    <span class="crm-status-badge badge-status-won" style="font-size: 10.5px; padding: 2px 6px; margin-top: 3px;">
                                        <span class="dot"></span> Paid
                                    </span>
                                @endif
                            </td>

                            {{-- Interactive Status Dropdown --}}
                            <td>
                                <div class="dropdown d-inline-block">
                                    <button class="btn btn-xs dropdown-toggle status-btn-live-{{ $sale->id }}" 
                                            type="button" 
                                            data-toggle="dropdown" 
                                            aria-haspopup="true" 
                                            aria-expanded="false" 
                                            style="display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:9999px; font-size:11.5px; font-weight:700; background:{{ $statusEnum->badgeBg() }}; color:{{ $statusEnum->badgeColor() }}; border:1px solid {{ $statusEnum->badgeColor() }}40; cursor:pointer;" 
                                            title="Click to quick change status">
                                        <i class="fas {{ $statusEnum->icon() }}"></i>
                                        <span class="status-lbl">{{ $statusEnum->label($isService) }}</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right shadow-sm p-1" style="border-radius:8px; font-size:12px; min-width:180px;">
                                        <div class="dropdown-header text-uppercase font-weight-bold py-1 px-2 text-muted" style="font-size:10px;">Quick Change Status</div>
                                        @foreach (\App\Enums\DeliveryStatus::casesFor($isService) as $stCase)
                                            <a class="dropdown-item py-1 px-2 d-flex align-items-center quick-status-select {{ $sale->delivery_status === $stCase->value ? 'active font-weight-bold' : '' }}" 
                                               href="#" 
                                               data-id="{{ $sale->id }}" 
                                               data-status="{{ $stCase->value }}"
                                               data-label="{{ $stCase->label($isService) }}"
                                               data-color="{{ $stCase->badgeColor() }}"
                                               data-bg="{{ $stCase->badgeBg() }}"
                                               data-icon="{{ $stCase->icon() }}"
                                               data-due="{{ $due }}">
                                                <span class="mr-2" style="width:8px; height:8px; border-radius:50%; background:{{ $stCase->badgeColor() }}; display:inline-block;"></span>
                                                {{ $stCase->label($isService) }}
                                                @if ($sale->delivery_status === $stCase->value)
                                                    <i class="fas fa-check ml-auto text-success" style="font-size:10px;"></i>
                                                @endif
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                                @if ($sale->delivered_at && $sale->deliveredBy)
                                    <div class="delivered-by-live-{{ $sale->id }}" style="font-size: 10px; color: #64748b; margin-top: 3px;">
                                        By {{ $sale->deliveredBy->name }}
                                    </div>
                                @else
                                    <div class="delivered-by-live-{{ $sale->id }}" style="font-size: 10px; color: #64748b; margin-top: 3px; display:none;"></div>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td style="text-align: center;">
                                <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                    <button type="button" 
                                            class="btn btn-outline-primary btn-sm btn-update-modal" 
                                            style="width: 100%; font-size: 11.5px; padding: 4px 8px; font-weight: 600;"
                                            data-id="{{ $sale->id }}"
                                            data-invoice="{{ $sale->sale_invoice_no }}"
                                            data-customer="{{ $leadName }}"
                                            data-phone="{{ $phone }}"
                                            data-total="{{ number_format($sale->total_amount, 2, '.', '') }}"
                                            data-paid="{{ number_format($sale->paid, 2, '.', '') }}"
                                            data-due="{{ number_format($due, 2, '.', '') }}"
                                            data-is-service="{{ $isService ? '1' : '0' }}"
                                            data-status="{{ $sale->delivery_status }}"
                                            data-delivery-time="{{ $sale->delivery_date_time ? $sale->delivery_date_time->format('Y-m-d\TH:i') : '' }}"
                                            data-lab-name="{{ $sale->lab_name ?? '' }}">
                                        <i class="fas fa-edit mr-1"></i>Update Status
                                    </button>

                                    {{-- Contextual 1-click Fast Action --}}
                                    @if ($statusEnum === \App\Enums\DeliveryStatus::ORDERED)
                                        @if ($isService)
                                            <button type="button" class="quick-action-btn btn-ready btn-quick-step" data-id="{{ $sale->id }}" data-status="ready_for_delivery" title="Mark assessment report as ready for patient collection">
                                                <i class="fas fa-bell"></i> Mark Ready
                                            </button>
                                        @else
                                            <button type="button" class="quick-action-btn btn-lab btn-quick-step" data-id="{{ $sale->id }}" data-status="sent_to_lab" title="Send ear mold/device to lab">
                                                <i class="fas fa-flask"></i> Send to Lab
                                            </button>
                                        @endif
                                    @elseif ($statusEnum === \App\Enums\DeliveryStatus::SENT_TO_LAB)
                                        <button type="button" class="quick-action-btn btn-ready btn-quick-step" data-id="{{ $sale->id }}" data-status="ready_for_delivery" title="Mark ready for fitting">
                                            <i class="fas fa-user-check"></i> Ready for Fitting
                                        </button>
                                    @elseif ($statusEnum === \App\Enums\DeliveryStatus::READY_FOR_DELIVERY)
                                        <button type="button" class="quick-action-btn btn-deliver btn-quick-step" data-id="{{ $sale->id }}" data-status="delivered" data-due="{{ $due }}" title="Mark as delivered & collect due">
                                            <i class="fas fa-check-circle"></i> Handover / Deliver
                                        </button>
                                    @endif

                                    <div style="display: flex; gap: 4px; margin-top: 2px;">
                                        <a href="{{ route('sale.pos-print', $sale->id) }}" target="_blank" class="btn btn-outline-info btn-xs" style="padding: 2px 6px; font-size: 11px;" title="Print POS Thermal Receipt">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                        <a href="{{ url('admin/sale/' . $sale->id) }}" class="btn btn-outline-secondary btn-xs" style="padding: 2px 6px; font-size: 11px;" title="View Full Invoice">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px 20px;">
                                <div style="color: #94a3b8; font-size: 32px; margin-bottom: 8px;"><i class="fas fa-inbox"></i></div>
                                <div style="font-weight: 600; color: #475569; font-size: 15px;">No delivery records found</div>
                                <div style="font-size: 12.5px; color: #94a3b8; margin-top: 4px;">Try selecting another tab, clearing filters, or adding a new sale.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($sales) && method_exists($sales, 'hasPages') && $sales->hasPages())
            <div style="padding: 16px 20px; border-top: 1px solid var(--gray-200, #e2e8f0);">
                {{ $sales->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Interactive Status & Fulfillment Modal --}}
<div class="modal fade" id="updateStatusModal" tabindex="-1" role="dialog" aria-labelledby="updateStatusModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 580px;">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); color: #fff; border-top-left-radius: 12px; border-top-right-radius: 12px; padding: 16px 20px;">
                <h5 class="modal-title" id="updateStatusModalTitle" style="font-size: 16px; font-weight: 700; color: #fff;">
                    <i class="fas fa-clipboard-check mr-2"></i>Update Report &amp; Delivery Status
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <form id="formUpdateStatus" method="POST" action="">
                @csrf
                <input type="hidden" id="modal_sale_id" name="sale_id" value="">
                
                <div class="modal-body" style="padding: 20px;">
                    
                    {{-- Financials & Order Preview Card --}}
                    <div class="modal-order-summary">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div>
                                <span style="font-size:11px; text-transform:uppercase; color:#64748b; font-weight:700;">Invoice</span>
                                <b id="modal_invoice_no" style="font-size:14px; color:#4f46e5; margin-left:4px;">#—</b>
                            </div>
                            <div>
                                <span style="font-size:12px; font-weight:600; color:#0f172a;" id="modal_customer_name">—</span>
                                <small style="color:#64748b;" id="modal_customer_phone">(—)</small>
                            </div>
                        </div>
                        <div class="modal-order-grid">
                            <div>
                                <div class="lbl">Total Bill</div>
                                <div class="val" id="modal_total_val">৳0.00</div>
                            </div>
                            <div>
                                <div class="lbl">Paid</div>
                                <div class="val paid-green" id="modal_paid_val">৳0.00</div>
                            </div>
                            <div>
                                <div class="lbl">Current Due</div>
                                <div class="val due-red" id="modal_due_val">৳0.00</div>
                            </div>
                            <div>
                                <div class="lbl">Sale Track</div>
                                <div class="val" id="modal_track_type" style="font-size:12px; padding-top:2px;">—</div>
                            </div>
                        </div>
                    </div>

                    {{-- Delivery Status Selection --}}
                    <div class="form-group">
                        <label for="modal_delivery_status" style="font-size: 13px; font-weight: 700; color: #1e293b;">
                            Fulfillment Status <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" id="modal_delivery_status" name="delivery_status" required style="font-size: 13.5px; font-weight: 600;">
                            {{-- Dynamically populated based on service vs product --}}
                        </select>
                    </div>

                    {{-- Promised Date / Time Picker --}}
                    <div class="form-group">
                        <label for="modal_delivery_date_time" style="font-size: 13px; font-weight: 700; color: #1e293b; display: flex; justify-content: space-between;">
                            <span id="lbl_promised_time">Promised Delivery / Report Date &amp; Time</span>
                            <small class="text-muted">Patient commitment</small>
                        </label>
                        <input type="datetime-local" class="form-control" id="modal_delivery_date_time" name="delivery_date_time">
                        <div class="time-preset-chips">
                            <span class="time-chip" onclick="setTimePreset(1)">+1 Hour</span>
                            <span class="time-chip" onclick="setTimePreset(2)">+2 Hours</span>
                            <span class="time-chip" onclick="setTimePreset(3)">+3 Hours</span>
                            <span class="time-chip" onclick="setTimePreset('today_5pm')">Today 5:00 PM</span>
                            <span class="time-chip" onclick="setTimePreset('tomorrow_11am')">Tomorrow 11:00 AM</span>
                            <span class="time-chip" onclick="setTimePreset('tomorrow_4pm')">Tomorrow 4:00 PM</span>
                            <span class="time-chip" onclick="clearTimePreset()" style="color:#ef4444;"><i class="fas fa-times mr-1"></i>Clear</span>
                        </div>
                    </div>

                    {{-- Lab Name (Applicable for Product Orders) --}}
                    <div class="form-group" id="wrap_lab_name" style="display: none;">
                        <label for="modal_lab_name" style="font-size: 13px; font-weight: 700; color: #1e293b;">
                            <i class="fas fa-flask mr-1" style="color: #7c3aed;"></i>Laboratory / Ear Mold Fabricator
                        </label>
                        <input type="text" class="form-control" id="modal_lab_name" name="lab_name" placeholder="e.g. Center Lab, Signia Ear Mold Lab, Shell Fabrication Lab" list="lab_suggestions">
                        <datalist id="lab_suggestions">
                            <option value="In-house Lab">
                            <option value="Signia Custom Mold Lab">
                            <option value="Phonak Shell Fabrication">
                            <option value="Siemens Ear Mold Department">
                            <option value="Universal Hearing Lab">
                        </datalist>
                    </div>

                    {{-- Inline Due Payment Collection --}}
                    <div class="settle-due-card" id="wrap_due_collection" style="display: none;">
                        <div class="custom-control custom-checkbox" style="margin-bottom: 8px;">
                            <input type="checkbox" class="custom-control-input" id="collect_due_chk" name="collect_due" value="1">
                            <label class="custom-control-label" for="collect_due_chk" style="font-weight: 700; color: #b91c1c; font-size: 13px; cursor: pointer;">
                                <i class="fas fa-hand-holding-usd mr-1"></i>Collect Due Balance Now (<span id="lbl_chk_due_amt">৳0.00</span>)
                            </label>
                        </div>
                        <div id="due_payment_fields" style="display: none; padding-top: 6px;">
                            <div class="row">
                                <div class="col-md-6 form-group mb-2">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569;">Amount (৳) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="paid_amount_input" name="paid_amount" placeholder="0.00">
                                </div>
                                <div class="col-md-6 form-group mb-2">
                                    <label style="font-size: 11px; font-weight: 700; color: #475569;">Payment Date</label>
                                    <input type="date" class="form-control" name="payment_date" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label style="font-size: 11px; font-weight: 700; color: #475569;">Payment Note / Details</label>
                                <input type="text" class="form-control" name="payment_details" placeholder="Collected at time of handover / fitting">
                            </div>
                        </div>
                    </div>

                    {{-- Patient SMS Alert --}}
                    <div class="sms-alert-card" id="wrap_sms_alert">
                        <div class="custom-control custom-checkbox" style="margin-bottom: 6px;">
                            <input type="checkbox" class="custom-control-input" id="send_sms_chk" name="send_sms" value="1">
                            <label class="custom-control-label" for="send_sms_chk" style="font-weight: 700; color: #15803d; font-size: 13px; cursor: pointer;">
                                <i class="fas fa-sms mr-1"></i>Send SMS Alert to Patient (<span id="lbl_sms_phone">—</span>)
                            </label>
                        </div>
                        <div id="sms_custom_wrap" style="display: none; padding-top: 4px;">
                            <textarea class="form-control" id="custom_sms_message" name="custom_message" rows="2" style="font-size: 12px;" placeholder="Message preview…"></textarea>
                            <small class="text-muted" style="font-size: 11px;"><i class="fas fa-info-circle mr-1"></i>Message is pre-filled automatically based on the status. You may customize it.</small>
                        </div>
                    </div>

                </div>

                <div class="modal-footer" style="padding: 14px 20px; background: #f8fafc; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn_save_status" style="font-weight: 600; padding: 6px 18px;">
                        <i class="fas fa-check-circle mr-1"></i>Save &amp; Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Barcode Fast Scanner Modal & Global Hardware Listener --}}
@include('admin.sales.partials.barcode_scan_modal')

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var serviceOptions = [
        { value: 'ordered', label: 'Assessment Booked (Awaiting Test)' },
        { value: 'ready_for_delivery', label: 'Report Ready (For Patient Collection)' },
        { value: 'delivered', label: 'Report Delivered' },
        { value: 'cancelled', label: 'Cancelled' }
    ];

    var productOptions = [
        { value: 'ordered', label: 'Order Placed (Booked)' },
        { value: 'sent_to_lab', label: 'Sent to Lab (Ear Mold / Custom Fabrication)' },
        { value: 'ready_for_delivery', label: 'Ready for Fitting (In Center)' },
        { value: 'delivered', label: 'Delivered & Fitted' },
        { value: 'cancelled', label: 'Cancelled' }
    ];

    // Open Update Modal from row button
    $(document).on('click', '.btn-update-modal', function() {
        var btn = $(this);
        var id = btn.data('id');
        var invoice = btn.data('invoice');
        var customer = btn.data('customer');
        var phone = btn.data('phone') || 'No phone';
        var total = parseFloat(btn.data('total')) || 0;
        var paid = parseFloat(btn.data('paid')) || 0;
        var due = parseFloat(btn.data('due')) || 0;
        var isService = btn.data('is-service') == '1';
        var status = btn.data('status');
        var deliveryTime = btn.data('delivery-time') || '';
        var labName = btn.data('lab-name') || '';

        $('#modal_sale_id').val(id);
        $('#formUpdateStatus').attr('action', '{{ url("admin/sales") }}/' + id + '/update-delivery-status');
        $('#modal_invoice_no').text('#' + invoice);
        $('#modal_customer_name').text(customer);
        $('#modal_customer_phone').text(phone);
        $('#modal_total_val').text('৳' + total.toFixed(2));
        $('#modal_paid_val').text('৳' + paid.toFixed(2));
        $('#modal_due_val').text('৳' + due.toFixed(2));
        $('#modal_track_type').text(isService ? 'Assessment' : 'Product / Aid');
        $('#modal_delivery_date_time').val(deliveryTime);
        $('#modal_lab_name').val(labName);

        // Populate Status Select
        var select = $('#modal_delivery_status');
        select.empty();
        var options = isService ? serviceOptions : productOptions;
        $.each(options, function(i, opt) {
            var selected = (opt.value === status) ? 'selected' : '';
            select.append('<option value="' + opt.value + '" ' + selected + '>' + opt.label + '</option>');
        });

        // Toggle Lab field
        if (!isService) {
            $('#wrap_lab_name').show();
            $('#lbl_promised_time').text('Promised Fitting / Delivery Date & Time');
        } else {
            $('#wrap_lab_name').hide();
            $('#lbl_promised_time').text('Promised Report Date & Time');
        }

        // Setup Due Collection
        if (due > 0.009) {
            $('#wrap_due_collection').show();
            $('#lbl_chk_due_amt').text('৳' + due.toFixed(2));
            $('#paid_amount_input').val(due.toFixed(2));
            $('#collect_due_chk').prop('checked', false);
            $('#due_payment_fields').hide();
        } else {
            $('#wrap_due_collection').hide();
            $('#collect_due_chk').prop('checked', false);
            $('#due_payment_fields').hide();
        }

        // Setup SMS Preview
        $('#lbl_sms_phone').text(phone);
        if (phone && phone.length > 5) {
            $('#wrap_sms_alert').show();
            $('#send_sms_chk').prop('checked', (status === 'ready_for_delivery'));
            updateSmsPreview(isService, select.val(), customer, invoice);
            if (status === 'ready_for_delivery') {
                $('#sms_custom_wrap').show();
            } else {
                $('#sms_custom_wrap').hide();
            }
        } else {
            $('#wrap_sms_alert').hide();
            $('#send_sms_chk').prop('checked', false);
        }

        $('#updateStatusModal').modal('show');
    });

    // When status changes in modal
    $('#modal_delivery_status').on('change', function() {
        var status = $(this).val();
        var isService = $('#modal_track_type').text().indexOf('Assessment') !== -1;
        var customer = $('#modal_customer_name').text();
        var invoice = $('#modal_invoice_no').text().replace('#', '');
        var due = parseFloat($('#modal_due_val').text().replace('৳', '')) || 0;

        // Auto check SMS if moving to ready_for_delivery
        if (status === 'ready_for_delivery') {
            $('#send_sms_chk').prop('checked', true);
            $('#sms_custom_wrap').show();
        } else if (status === 'delivered' && due > 0.009) {
            // Suggest collecting due
            $('#collect_due_chk').prop('checked', true);
            $('#due_payment_fields').show();
        }

        updateSmsPreview(isService, status, customer, invoice);
    });

    // Toggle due collection inputs
    $('#collect_due_chk').on('change', function() {
        if ($(this).is(':checked')) {
            $('#due_payment_fields').slideDown(150);
        } else {
            $('#due_payment_fields').slideUp(150);
        }
    });

    // Toggle custom SMS input
    $('#send_sms_chk').on('change', function() {
        if ($(this).is(':checked')) {
            $('#sms_custom_wrap').slideDown(150);
        } else {
            $('#sms_custom_wrap').slideUp(150);
        }
    });

    function updateSmsPreview(isService, status, customer, invoice) {
        var msg = "";
        var company = "{{ \App\Models\Setting::first()?->company_name ?? 'Our Center' }}";
        if (isService) {
            if (status === 'ready_for_delivery') {
                msg = "Dear " + customer + ", your hearing assessment report (Inv #" + invoice + ") is ready for collection at " + company + ". Thank you.";
            } else if (status === 'delivered') {
                msg = "Dear " + customer + ", thank you for collecting your assessment report from " + company + ". Wishing you good hearing health.";
            } else {
                msg = "Dear " + customer + ", your hearing assessment is booked at " + company + ". Inv #" + invoice + ".";
            }
        } else {
            if (status === 'sent_to_lab') {
                msg = "Dear " + customer + ", your order (Inv #" + invoice + ") has been sent to our lab for fabrication.";
            } else if (status === 'ready_for_delivery') {
                msg = "Dear " + customer + ", your hearing device (Inv #" + invoice + ") is ready for trial & fitting at " + company + ". Please visit us soon.";
            } else if (status === 'delivered') {
                msg = "Dear " + customer + ", thank you for choosing " + company + "! Your hearing device has been fitted and delivered.";
            } else {
                msg = "Dear " + customer + ", your order (Inv #" + invoice + ") has been confirmed at " + company + ".";
            }
        }
        $('#custom_sms_message').val(msg);
    }

    // Quick 1-click step buttons in table
    $(document).on('click', '.btn-quick-step', function(e) {
        e.preventDefault();
        var btn = $(this);
        var id = btn.data('id');
        var targetStatus = btn.data('status');
        var due = parseFloat(btn.data('due')) || 0;

        // If target is delivered and there's due balance, open the modal instead so staff can collect due
        if (targetStatus === 'delivered' && due > 0.009) {
            $('#sale-row-' + id + ' .btn-update-modal').trigger('click');
            $('#modal_delivery_status').val('delivered').trigger('change');
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '{{ url("admin/sales") }}/' + id + '/update-delivery-status',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                delivery_status: targetStatus,
                send_sms: (targetStatus === 'ready_for_delivery' ? 1 : 0)
            },
            success: function(res) {
                if (res.success) {
                    if (window.toastr) {
                        toastr.success(res.message);
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    alert(res.message || 'Update failed');
                    btn.prop('disabled', false);
                }
            },
            error: function(xhr) {
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Server error';
                alert(err);
                btn.prop('disabled', false);
            }
        });
    });

    // 1-Click Status Dropdown Select
    $(document).on('click', '.quick-status-select', function(e) {
        e.preventDefault();
        var item = $(this);
        var id = item.data('id');
        var targetStatus = item.data('status');
        var due = parseFloat(item.data('due')) || 0;

        // If target is delivered and there's a due balance, open modal to settle due
        if (targetStatus === 'delivered' && due > 0.009) {
            $('#sale-row-' + id + ' .btn-update-modal').trigger('click');
            $('#modal_delivery_status').val('delivered').trigger('change');
            return;
        }

        var btn = $('.status-btn-live-' + id);
        var origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Updating…');

        $.ajax({
            url: '{{ url("admin/sales") }}/' + id + '/update-delivery-status',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                delivery_status: targetStatus,
                send_sms: (targetStatus === 'ready_for_delivery' ? 1 : 0)
            },
            success: function(res) {
                btn.prop('disabled', false);
                if (res.success) {
                    btn.css({
                        'background': res.badge_bg,
                        'color': res.badge_color,
                        'border-color': res.badge_color + '40'
                    }).html('<i class="fas fa-check-circle mr-1"></i><span class="status-lbl">' + res.delivery_label + '</span>');
                    
                    if (res.delivered_by) {
                        $('.delivered-by-live-' + id).text('By ' + res.delivered_by).show();
                    } else {
                        $('.delivered-by-live-' + id).hide();
                    }

                    // Update active item in dropdown
                    item.closest('.dropdown-menu').find('.quick-status-select').removeClass('active font-weight-bold').find('.fa-check').remove();
                    item.addClass('active font-weight-bold').append('<i class="fas fa-check ml-auto text-success" style="font-size:10px;"></i>');

                    if (window.toastr) {
                        toastr.success(res.message);
                    }
                } else {
                    btn.html(origHtml);
                    alert(res.message || 'Update failed');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(origHtml);
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Server error';
                alert(err);
            }
        });
    });

    // AJAX form submit for modal
    $('#formUpdateStatus').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var btn = $('#btn_save_status');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving…');

        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: form.serialize(),
            success: function(res) {
                if (res.success) {
                    $('#updateStatusModal').modal('hide');
                    if (window.toastr) {
                        toastr.success(res.message);
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    alert(res.message || 'Update failed');
                    btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i>Save &amp; Update');
                }
            },
            error: function(xhr) {
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Submission failed';
                alert(err);
                btn.prop('disabled', false).html('<i class="fas fa-check-circle mr-1"></i>Save &amp; Update');
            }
        });
    });
});

// Time preset helpers
function padZero(n) { return n < 10 ? '0' + n : n; }

function formatDateTimeLocal(d) {
    return d.getFullYear() + '-' + padZero(d.getMonth() + 1) + '-' + padZero(d.getDate()) + 'T' + padZero(d.getHours()) + ':' + padZero(d.getMinutes());
}

function setTimePreset(preset) {
    var now = new Date();
    if (typeof preset === 'number') {
        now.setHours(now.getHours() + preset);
    } else if (preset === 'today_5pm') {
        now.setHours(17, 0, 0, 0);
    } else if (preset === 'tomorrow_11am') {
        now.setDate(now.getDate() + 1);
        now.setHours(11, 0, 0, 0);
    } else if (preset === 'tomorrow_4pm') {
        now.setDate(now.getDate() + 1);
        now.setHours(16, 0, 0, 0);
    }
    document.getElementById('modal_delivery_date_time').value = formatDateTimeLocal(now);
}

function clearTimePreset() {
    document.getElementById('modal_delivery_date_time').value = '';
}
</script>
@endpush
