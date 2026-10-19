@extends('admin.layouts.master')
@section('page_title', 'Master Financial Ledger')
@section('breadcrumb_parent', 'Accounts')
@section('breadcrumb_parent_url', url('admin/report/ledger'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        /* ── Modern KPI Cards ── */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .kpi {
            --c: var(--accent-500, #6366f1);
            --bg: rgba(99,102,241,.07);
            --bd: rgba(99,102,241,.18);
            --tx: var(--accent-700, #4338ca);
            position: relative;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 18px;
            border-radius: 14px;
            background: var(--bg);
            border: 1px solid var(--bd);
            text-decoration: none !important;
            color: var(--page-text, #1e293b);
            overflow: hidden;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .kpi:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px -12px var(--c);
            color: var(--page-text, #1e293b);
        }
        .kpi::after {
            content: '';
            position: absolute;
            right: -28px;
            top: -28px;
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: var(--c);
            opacity: .08;
        }
        .kpi .k-ic {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
            background: var(--c);
            color: #fff;
            box-shadow: 0 6px 14px -6px var(--c);
        }
        .kpi .k-lbl {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--tx);
            opacity: .9;
        }
        .kpi .k-val {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
            margin-top: 3px;
            color: var(--page-text, #0f172a);
        }
        .kpi .k-sub {
            font-size: 11.5px;
            color: var(--page-text-secondary, #64748b);
            margin-top: 3px;
        }
        .kpi .k-sub b {
            color: var(--tx);
        }
        .kpi.k-green  { --c:#10b981; --bg:rgba(16,185,129,.07); --bd:rgba(16,185,129,.22); --tx:#047857; }
        .kpi.k-rose   { --c:#f43f5e; --bg:rgba(244,63,94,.07); --bd:rgba(244,63,94,.22); --tx:#be123c; }
        .kpi.k-indigo { --c:#6366f1; --bg:rgba(99,102,241,.07); --bd:rgba(99,102,241,.22); --tx:#4338ca; }
        .kpi.k-blue   { --c:#0284c7; --bg:rgba(2,132,199,.07); --bd:rgba(2,132,199,.22); --tx:#0369a1; }

        /* ── Filter Card & Date Chips ── */
        .filter-card {
            background: #ffffff;
            border: 1px solid var(--gray-200, #e2e8f0);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.04);
        }
        .date-chips {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 14px;
            padding-bottom: 12px;
            border-bottom: 1px dashed var(--gray-200, #e2e8f0);
        }
        .date-chip {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            background: var(--gray-100, #f1f5f9);
            color: var(--gray-600, #475569);
            border: 1px solid var(--gray-200, #e2e8f0);
            cursor: pointer;
            transition: all 0.16s ease;
            user-select: none;
        }
        .date-chip:hover {
            background: var(--gray-200, #e2e8f0);
            color: var(--gray-900, #0f172a);
        }
        .date-chip.is-active {
            background: var(--accent-600, #4f46e5);
            color: #ffffff;
            border-color: var(--accent-600, #4f46e5);
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);
        }

        /* ── Ledger View Tabs ── */
        .ledger-nav-tabs {
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
            flex-wrap: wrap;
        }
        .ledger-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--gray-600, #475569);
            background: var(--gray-100, #f1f5f9);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.16s ease;
        }
        .ledger-tab-btn:hover {
            background: var(--gray-200, #e2e8f0);
            color: var(--gray-900, #0f172a);
        }
        .ledger-tab-btn.active {
            background: #ffffff;
            color: var(--accent-600, #4f46e5);
            border-color: var(--gray-200, #e2e8f0);
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
        }
        .ledger-tab-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 999px;
            background: rgba(0,0,0,0.06);
        }
        .ledger-tab-btn.active .ledger-tab-badge {
            background: var(--accent-50, #eef2ff);
            color: var(--accent-700, #4338ca);
        }

        /* ── Table & Badges ── */
        .ledger-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
        }
        .ledger-pill.inflow {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .ledger-pill.outflow {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }
        .amount-inflow {
            color: #059669;
            font-weight: 700;
        }
        .amount-outflow {
            color: #e11d48;
            font-weight: 700;
        }
        .amount-balance {
            font-weight: 700;
            color: #0f172a;
        }
        .amount-balance.deficit {
            color: #dc2626;
        }

        /* ── Search Input ── */
        .search-wrapper {
            position: relative;
            min-width: 220px;
        }
        .search-wrapper i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400, #94a3b8);
            font-size: 12px;
        }
        .search-wrapper input {
            padding-left: 30px !important;
            border-radius: 8px;
            font-size: 12.5px;
        }

        /* ── Printable Styles ── */
        @media print {
            body * {
                visibility: hidden;
            }
            #print-area, #print-area * {
                visibility: visible;
            }
            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: #ffffff;
                padding: 20px;
                color: #000000;
            }
            .no-print {
                display: none !important;
            }
            .kpi-row {
                display: flex !important;
                flex-wrap: wrap !important;
            }
            .kpi {
                border: 1px solid #ccc !important;
                background: #f8fafc !important;
            }
        }
    </style>
@endpush

@section('content')
@php
    $incomesList = isset($incomes) ? collect($incomes) : collect();
    $expensesList = isset($expenses) ? collect($expenses) : collect();
    $totalIncome = (float) $incomesList->sum('income_amount');
    $totalExpense = (float) $expensesList->sum('expense_amount');
    $netBalance = $totalIncome - $totalExpense;
    $totalTransactions = $incomesList->count() + $expensesList->count();

    // Combined Chronological Dataset with dynamic running balance
    $combined = collect();
    foreach ($incomesList as $item) {
        $combined->push((object)[
            'id' => $item->id,
            'date' => $item->date,
            'amount_type' => 'Income',
            'invoice_no' => $item->invoice_no,
            'head' => $item->head->head_name ?? null,
            'details' => $item->details,
            'type' => $item->type,
            'ref_invoice_no' => $item->ref_invoice_no,
            'inflow' => (float)$item->income_amount,
            'outflow' => 0.0,
            'vendor' => $item->vendor->name ?? null,
            'created_at' => $item->created_at,
        ]);
    }
    foreach ($expensesList as $item) {
        $combined->push((object)[
            'id' => $item->id,
            'date' => $item->date,
            'amount_type' => 'Expense',
            'invoice_no' => $item->invoice_no,
            'head' => $item->head->head_name ?? null,
            'details' => $item->details,
            'type' => $item->type,
            'ref_invoice_no' => $item->ref_invoice_no,
            'inflow' => 0.0,
            'outflow' => (float)$item->expense_amount,
            'vendor' => $item->vendor->name ?? null,
            'created_at' => $item->created_at,
        ]);
    }

    // Sort chronologically ascending to compute progressive running balances
    $sortedAsc = $combined->sortBy([['date', 'asc'], ['id', 'asc']]);
    $running = 0.0;
    $withBalances = collect();
    foreach ($sortedAsc as $entry) {
        $running += ($entry->inflow - $entry->outflow);
        $entry->running_balance = $running;
        $withBalances->push($entry);
    }
    // Display order: newest first
    $chronologicalList = $withBalances->sortByDesc(fn($x) => $x->date . '_' . str_pad($x->id, 10, '0', STR_PAD_LEFT));
@endphp

<div class="container-fluid" style="padding: 0 24px;">

    {{-- Modern Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-book-open" style="color: var(--accent-600); margin-right: 8px;"></i>Master Financial Ledger</h1>
            <p>Consolidated accounting timeline, cash flow tracking, and branch ledger balances</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-outline-secondary btn-sm print-btn">
                <i class="fas fa-print mr-1"></i>Print Statement
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-export-csv">
                <i class="fas fa-file-csv mr-1"></i>Export CSV
            </button>
        </div>
    </div>

    {{-- Universal Finance Quick-Action Bar --}}
    @include('admin.partials.finance-quick-actions')

    {{-- Rich Tinted KPI Cards --}}
    <div class="kpi-row crm-animate-in delay-1">
        {{-- Total Inflow --}}
        <div class="kpi k-green">
            <div class="k-ic"><i class="fas fa-arrow-down"></i></div>
            <div>
                <div class="k-lbl">Total Inflow (Income)</div>
                <div class="k-val">৳{{ number_format($totalIncome, 2) }}</div>
                <div class="k-sub"><b>{{ $incomesList->count() }}</b> collection entries</div>
            </div>
        </div>

        {{-- Total Outflow --}}
        <div class="kpi k-rose">
            <div class="k-ic"><i class="fas fa-arrow-up"></i></div>
            <div>
                <div class="k-lbl">Total Outflow (Expense)</div>
                <div class="k-val">৳{{ number_format($totalExpense, 2) }}</div>
                <div class="k-sub"><b>{{ $expensesList->count() }}</b> expense vouchers</div>
            </div>
        </div>

        {{-- Net Cash Position --}}
        <div class="kpi {{ $netBalance >= 0 ? 'k-indigo' : 'k-rose' }}">
            <div class="k-ic"><i class="fas fa-scale-balanced"></i></div>
            <div>
                <div class="k-lbl">Net Cash Position</div>
                <div class="k-val">৳{{ number_format($netBalance, 2) }}</div>
                <div class="k-sub">
                    <b>{{ $netBalance >= 0 ? 'Surplus' : 'Deficit' }}</b> for selected period
                </div>
            </div>
        </div>

        {{-- Transactions Count --}}
        <div class="kpi k-blue">
            <div class="k-ic"><i class="fas fa-exchange-alt"></i></div>
            <div>
                <div class="k-lbl">Total Transactions</div>
                <div class="k-val">{{ $totalTransactions }}</div>
                <div class="k-sub">From <b>{{ $startDate }}</b> to <b>{{ $endDate }}</b></div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="filter-card crm-animate-in delay-2">
        {{-- Quick Date Range Chips --}}
        <div class="date-chips">
            <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--gray-500); margin-right: 6px;">Quick Presets:</span>
            <span class="date-chip" data-range="today">Today</span>
            <span class="date-chip" data-range="yesterday">Yesterday</span>
            <span class="date-chip" data-range="this_week">This Week</span>
            <span class="date-chip" data-range="this_month">This Month</span>
            <span class="date-chip" data-range="last_month">Last Month</span>
            <span class="date-chip" data-range="this_year">This Year</span>
        </div>

        <form class="form" id="ledger-filter-form" action="{{ route('report.ledger') }}" method="post">
            @csrf
            <div class="row align-items-end">
                <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                    <div class="form-group mb-0">
                        <label style="font-size: 11.5px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">Transaction Type</label>
                        <select name="type" class="form-control form-control-sm select" id="type" required>
                            <option value="">Select Type</option>
                            @foreach ($ledgerFilterTypes as $item)
                                <option value="{{ $item }}" @selected(($type ?? '') == $item || (($type ?? '') == '' && $item == 'Both'))>
                                    {{ $item === 'Both' ? 'Both (All Transactions)' : $item }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-lg-3 col-md-3 col-sm-6 mb-2">
                    <div class="form-group mb-0">
                        <label style="font-size: 11.5px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">From Date</label>
                        <input type="date" autocomplete="off" name="start_date" id="start_date"
                               value="{{ $startDate ?? '' }}" class="form-control form-control-sm" required>
                    </div>
                </div>

                <div class="col-lg-3 col-md-3 col-sm-6 mb-2">
                    <div class="form-group mb-0">
                        <label style="font-size: 11.5px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">To Date</label>
                        <input type="date" autocomplete="off" name="end_date" id="end_date"
                               value="{{ $endDate ?? '' }}" class="form-control form-control-sm" required>
                    </div>
                </div>

                @if(!empty($branches))
                <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                    <div class="form-group mb-0">
                        <label style="font-size: 11.5px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">Branch</label>
                        <select name="branch" id="branch" class="form-control form-control-sm select">
                            <option value="all">All branches</option>
                            <option value="hq" @selected(($branch ?? '') === 'hq')>Head Office</option>
                            @foreach ($branches as $branchId => $branchName)
                                <option value="{{ $branchId }}" @selected((string) ($branch ?? '') === (string) $branchId)>{{ $branchName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif

                <div class="col-lg-2 col-md-3 col-sm-6 mb-2 d-flex" style="gap: 8px;">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill" style="font-weight: 600;">
                        <i class="fas fa-filter mr-1"></i>Apply Filter
                    </button>
                    <a href="{{ url('admin/report/ledger') }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Main Ledger Table Card --}}
    <div class="crm-table-card crm-animate-in delay-3">
        {{-- Card Header with Tabs & Live Search --}}
        <div class="crm-table-header d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--gray-200);">
            {{-- Tabs --}}
            <div class="ledger-nav-tabs">
                <button type="button" class="ledger-tab-btn active" data-target="tab-all">
                    <i class="fas fa-list-ul"></i>
                    <span>All Transactions</span>
                    <span class="ledger-tab-badge">{{ $totalTransactions }}</span>
                </button>
                <button type="button" class="ledger-tab-btn" data-target="tab-incomes">
                    <i class="fas fa-arrow-down text-success"></i>
                    <span>Inflows Only</span>
                    <span class="ledger-tab-badge" style="background:#ecfdf5; color:#047857;">{{ $incomesList->count() }}</span>
                </button>
                <button type="button" class="ledger-tab-btn" data-target="tab-expenses">
                    <i class="fas fa-arrow-up text-danger"></i>
                    <span>Outflows Only</span>
                    <span class="ledger-tab-badge" style="background:#fff1f2; color:#be123c;">{{ $expensesList->count() }}</span>
                </button>
                <button type="button" class="ledger-tab-btn" data-target="tab-split">
                    <i class="fas fa-columns text-primary"></i>
                    <span>Split Dual View</span>
                </button>
            </div>

            {{-- Live Search & Row Counter --}}
            <div class="d-flex align-items-center" style="gap: 10px;">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="ledger-live-search" class="form-control form-control-sm" placeholder="Live filter voucher, memo, details...">
                </div>
                <span id="filtered-count-badge" class="badge badge-light" style="font-size: 11px; padding: 6px 10px; color: var(--gray-600);">
                    {{ $totalTransactions }} records
                </span>
            </div>
        </div>

        {{-- Tab 1: Chronological Interleaved Timeline (Default) --}}
        <div class="ledger-tab-pane" id="tab-all" style="display: block;">
            <div style="overflow-x: auto;">
                <table class="crm-table" id="table-chronological">
                    <thead>
                        <tr>
                            <th style="width: 45px;">#</th>
                            <th style="width: 100px;">Date</th>
                            <th style="width: 100px;">Type</th>
                            <th style="width: 130px;">Voucher / Inv #</th>
                            <th>Description & Head</th>
                            @if(!empty($branches))
                                <th style="width: 120px;">Branch</th>
                            @endif
                            <th style="width: 120px; text-align: right;">Inflow (Cr)</th>
                            <th style="width: 120px; text-align: right;">Outflow (Dr)</th>
                            <th style="width: 130px; text-align: right;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($chronologicalList as $index => $row)
                            <tr class="ledger-row" data-search="{{ strtolower($row->invoice_no . ' ' . $row->details . ' ' . $row->head . ' ' . $row->type . ' ' . $row->ref_invoice_no . ' ' . $row->amount_type) }}">
                                <td style="color: var(--gray-400); font-size: 11.5px;">{{ $index + 1 }}</td>
                                <td style="font-size: 12.5px; font-weight: 500;">
                                    {{ $row->date }}
                                </td>
                                <td>
                                    @if($row->amount_type === 'Income')
                                        <span class="ledger-pill inflow"><i class="fas fa-arrow-down"></i> Inflow</span>
                                    @else
                                        <span class="ledger-pill outflow"><i class="fas fa-arrow-up"></i> Outflow</span>
                                    @endif
                                </td>
                                <td>
                                    <span style="font-family: monospace; font-weight: 700; color: var(--accent-700); font-size: 12.5px;">
                                        {{ $row->invoice_no }}
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <span style="font-weight: 600; color: var(--gray-800); font-size: 13px;">{{ $row->details ?: 'General Ledger Entry' }}</span>
                                        @if($row->head)
                                            <span class="badge badge-light ml-1" style="font-size: 10.5px; border: 1px solid var(--gray-200);">{{ $row->head }}</span>
                                        @endif
                                    </div>
                                    @if($row->ref_invoice_no)
                                        <div style="font-size: 11.5px; color: var(--gray-500); margin-top: 2px;">
                                            <i class="fas fa-link mr-1"></i>Ref: {{ $row->type ? $row->type . ' - ' : '' }}Invoice #{{ $row->ref_invoice_no }}
                                        </div>
                                    @endif
                                </td>
                                @if(!empty($branches))
                                    <td style="font-size: 12px; color: var(--gray-600);">
                                        {{ $row->vendor ?: 'Head Office' }}
                                    </td>
                                @endif
                                <td style="text-align: right;">
                                    @if($row->inflow > 0)
                                        <span class="amount-inflow">+৳{{ number_format($row->inflow, 2) }}</span>
                                    @else
                                        <span style="color: var(--gray-300);">-</span>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    @if($row->outflow > 0)
                                        <span class="amount-outflow">-৳{{ number_format($row->outflow, 2) }}</span>
                                    @else
                                        <span style="color: var(--gray-300);">-</span>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    <span class="amount-balance {{ $row->running_balance < 0 ? 'deficit' : '' }}">
                                        ৳{{ number_format($row->running_balance, 2) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ !empty($branches) ? 9 : 8 }}" class="text-center py-5" style="color: var(--gray-400);">
                                    <i class="fas fa-receipt fa-3x mb-3" style="opacity: 0.3;"></i>
                                    <p style="font-size: 14px; font-weight: 500;">No ledger entries found for the selected criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($totalTransactions > 0)
                    <tfoot>
                        <tr style="background: var(--gray-50); font-weight: 700; border-top: 2px solid var(--gray-300);">
                            <td colspan="{{ !empty($branches) ? 6 : 5 }}" class="text-right" style="font-size: 13px;">Summary Totals:</td>
                            <td style="text-align: right;" class="amount-inflow">+৳{{ number_format($totalIncome, 2) }}</td>
                            <td style="text-align: right;" class="amount-outflow">-৳{{ number_format($totalExpense, 2) }}</td>
                            <td style="text-align: right;" class="amount-balance {{ $netBalance < 0 ? 'deficit' : '' }}">
                                ৳{{ number_format($netBalance, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Tab 2: Inflows Only --}}
        <div class="ledger-tab-pane" id="tab-incomes" style="display: none;">
            <div style="overflow-x: auto;">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th style="width: 45px;">#</th>
                            <th style="width: 100px;">Date</th>
                            <th style="width: 140px;">Invoice / Voucher</th>
                            <th>Description</th>
                            @if(!empty($branches))
                                <th style="width: 120px;">Branch</th>
                            @endif
                            <th style="width: 140px; text-align: right;">Inflow Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incomesList as $idx => $val)
                            <tr class="ledger-row" data-search="{{ strtolower($val->invoice_no . ' ' . $val->details . ' ' . $val->ref_invoice_no) }}">
                                <td style="color: var(--gray-400); font-size: 11.5px;">{{ $idx + 1 }}</td>
                                <td style="font-size: 12.5px;">{{ $val->date }}</td>
                                <td style="font-family: monospace; font-weight: 700; color: var(--accent-700);">{{ $val->invoice_no }}</td>
                                <td>
                                    <span style="font-weight: 600; color: var(--gray-800);">{{ $val->details }}</span>
                                    @if($val->ref_invoice_no)
                                        <span style="font-size: 11.5px; color: var(--gray-500); display: block;">
                                            ({{ $val->type }} - invoice no-{{ $val->ref_invoice_no }})
                                        </span>
                                    @endif
                                </td>
                                @if(!empty($branches))
                                    <td style="font-size: 12px; color: var(--gray-600);">{{ $val->vendor->name ?? 'Head Office' }}</td>
                                @endif
                                <td style="text-align: right;" class="amount-inflow">+৳{{ number_format($val->income_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ !empty($branches) ? 6 : 5 }}" class="text-center py-4 text-muted">No income records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($incomesList->count() > 0)
                    <tfoot>
                        <tr style="background: var(--gray-50); font-weight: 700;">
                            <td colspan="{{ !empty($branches) ? 5 : 4 }}" class="text-right">Total Inflow:</td>
                            <td style="text-align: right;" class="amount-inflow">+৳{{ number_format($totalIncome, 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Tab 3: Outflows Only --}}
        <div class="ledger-tab-pane" id="tab-expenses" style="display: none;">
            <div style="overflow-x: auto;">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th style="width: 45px;">#</th>
                            <th style="width: 100px;">Date</th>
                            <th style="width: 140px;">Invoice / Voucher</th>
                            <th>Description</th>
                            @if(!empty($branches))
                                <th style="width: 120px;">Branch</th>
                            @endif
                            <th style="width: 140px; text-align: right;">Expense Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expensesList as $idx => $val)
                            <tr class="ledger-row" data-search="{{ strtolower($val->invoice_no . ' ' . $val->details . ' ' . $val->ref_invoice_no) }}">
                                <td style="color: var(--gray-400); font-size: 11.5px;">{{ $idx + 1 }}</td>
                                <td style="font-size: 12.5px;">{{ $val->date }}</td>
                                <td style="font-family: monospace; font-weight: 700; color: var(--accent-700);">{{ $val->invoice_no }}</td>
                                <td>
                                    <span style="font-weight: 600; color: var(--gray-800);">{{ $val->details }}</span>
                                    @if($val->ref_invoice_no)
                                        <span style="font-size: 11.5px; color: var(--gray-500); display: block;">
                                            ({{ $val->type }} - invoice no-{{ $val->ref_invoice_no }})
                                        </span>
                                    @endif
                                </td>
                                @if(!empty($branches))
                                    <td style="font-size: 12px; color: var(--gray-600);">{{ $val->vendor->name ?? 'Head Office' }}</td>
                                @endif
                                <td style="text-align: right;" class="amount-outflow">-৳{{ number_format($val->expense_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ !empty($branches) ? 6 : 5 }}" class="text-center py-4 text-muted">No expense records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($expensesList->count() > 0)
                    <tfoot>
                        <tr style="background: var(--gray-50); font-weight: 700;">
                            <td colspan="{{ !empty($branches) ? 5 : 4 }}" class="text-right">Total Outflow:</td>
                            <td style="text-align: right;" class="amount-outflow">-৳{{ number_format($totalExpense, 2) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- Tab 4: Split Dual View (Traditional Columnar) --}}
        <div class="ledger-tab-pane" id="tab-split" style="display: none; padding: 16px;">
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <div style="border: 1px solid var(--gray-200); border-radius: 10px; overflow: hidden;">
                        <div style="background: #ecfdf5; border-bottom: 1px solid #a7f3d0; padding: 10px 14px; font-weight: 700; color: #047857; display: flex; justify-content: space-between;">
                            <span><i class="fas fa-arrow-down mr-1"></i> Incomes / Inflows</span>
                            <span>৳{{ number_format($totalIncome, 2) }}</span>
                        </div>
                        <div style="max-height: 480px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                                <thead style="background: var(--gray-50);">
                                    <tr>
                                        <th>Date</th>
                                        <th>Voucher</th>
                                        <th>Details</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($incomesList as $v)
                                        <tr>
                                            <td>{{ $v->date }}</td>
                                            <td style="font-family: monospace; font-weight: 600;">{{ $v->invoice_no }}</td>
                                            <td>{{ $v->details }}</td>
                                            <td class="text-right amount-inflow">৳{{ number_format($v->income_amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-3 text-muted">No entries</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 mb-3">
                    <div style="border: 1px solid var(--gray-200); border-radius: 10px; overflow: hidden;">
                        <div style="background: #fff1f2; border-bottom: 1px solid #fecdd3; padding: 10px 14px; font-weight: 700; color: #be123c; display: flex; justify-content: space-between;">
                            <span><i class="fas fa-arrow-up mr-1"></i> Expenses / Outflows</span>
                            <span>৳{{ number_format($totalExpense, 2) }}</span>
                        </div>
                        <div style="max-height: 480px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0" style="font-size: 12px;">
                                <thead style="background: var(--gray-50);">
                                    <tr>
                                        <th>Date</th>
                                        <th>Voucher</th>
                                        <th>Details</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($expensesList as $v)
                                        <tr>
                                            <td>{{ $v->date }}</td>
                                            <td style="font-family: monospace; font-weight: 600;">{{ $v->invoice_no }}</td>
                                            <td>{{ $v->details }}</td>
                                            <td class="text-right amount-outflow">৳{{ number_format($v->expense_amount, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-3 text-muted">No entries</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Dedicated Print Area (Compatible with custom.js .print-btn) --}}
    <div id="print-area" style="display: none;">
        <div style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 22px; font-weight: 800; text-transform: uppercase;">{{ $setting->company_name ?? 'Hearing Store CRM' }}</h2>
            <p style="margin: 3px 0; font-size: 14px; font-weight: 600;">Official Master Financial Ledger Statement</p>
            <p style="margin: 0; font-size: 12px; color: #64748b;">
                Reporting Period: <strong>{{ $startDate }}</strong> to <strong>{{ $endDate }}</strong> | Generated: {{ now()->format('d M Y, h:i A') }}
                @if(!empty($branch) && $branch !== 'all')
                    | Branch: <strong>{{ $branches[$branch] ?? $branch }}</strong>
                @endif
            </p>
        </div>

        {{-- Print Summary Grid --}}
        <table style="width: 100%; margin-bottom: 20px; border: 1px solid #cbd5e1; border-collapse: collapse;">
            <tr style="background: #f1f5f9;">
                <th style="padding: 8px 12px; border: 1px solid #cbd5e1; text-align: left; font-size: 12px;">Total Inflow (Income)</th>
                <th style="padding: 8px 12px; border: 1px solid #cbd5e1; text-align: left; font-size: 12px;">Total Outflow (Expense)</th>
                <th style="padding: 8px 12px; border: 1px solid #cbd5e1; text-align: left; font-size: 12px;">Net Closing Balance</th>
                <th style="padding: 8px 12px; border: 1px solid #cbd5e1; text-align: left; font-size: 12px;">Transactions Count</th>
            </tr>
            <tr>
                <td style="padding: 8px 12px; border: 1px solid #cbd5e1; font-size: 14px; font-weight: 700; color: #047857;">
                    ৳{{ number_format($totalIncome, 2) }}
                </td>
                <td style="padding: 8px 12px; border: 1px solid #cbd5e1; font-size: 14px; font-weight: 700; color: #be123c;">
                    ৳{{ number_format($totalExpense, 2) }}
                </td>
                <td style="padding: 8px 12px; border: 1px solid #cbd5e1; font-size: 14px; font-weight: 800; color: {{ $netBalance >= 0 ? '#4338ca' : '#be123c' }};">
                    ৳{{ number_format($netBalance, 2) }} ({{ $netBalance >= 0 ? 'Surplus' : 'Deficit' }})
                </td>
                <td style="padding: 8px 12px; border: 1px solid #cbd5e1; font-size: 14px; font-weight: 700;">
                    {{ $totalTransactions }}
                </td>
            </tr>
        </table>

        {{-- Print Table --}}
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <thead>
                <tr style="background: #e2e8f0;">
                    <th style="border: 1px solid #cbd5e1; padding: 6px;">#</th>
                    <th style="border: 1px solid #cbd5e1; padding: 6px;">Date</th>
                    <th style="border: 1px solid #cbd5e1; padding: 6px;">Voucher No</th>
                    <th style="border: 1px solid #cbd5e1; padding: 6px;">Particulars / Details</th>
                    <th style="border: 1px solid #cbd5e1; padding: 6px; text-align: right;">Inflow (Cr)</th>
                    <th style="border: 1px solid #cbd5e1; padding: 6px; text-align: right;">Outflow (Dr)</th>
                    <th style="border: 1px solid #cbd5e1; padding: 6px; text-align: right;">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chronologicalList as $pIdx => $pRow)
                    <tr>
                        <td style="border: 1px solid #e2e8f0; padding: 5px; text-align: center;">{{ $pIdx + 1 }}</td>
                        <td style="border: 1px solid #e2e8f0; padding: 5px;">{{ $pRow->date }}</td>
                        <td style="border: 1px solid #e2e8f0; padding: 5px; font-family: monospace;">{{ $pRow->invoice_no }}</td>
                        <td style="border: 1px solid #e2e8f0; padding: 5px;">
                            {{ $pRow->details }}
                            @if($pRow->ref_invoice_no)
                                <small>({{ $pRow->type }} #{{ $pRow->ref_invoice_no }})</small>
                            @endif
                        </td>
                        <td style="border: 1px solid #e2e8f0; padding: 5px; text-align: right; color: #047857;">
                            {{ $pRow->inflow > 0 ? '৳' . number_format($pRow->inflow, 2) : '-' }}
                        </td>
                        <td style="border: 1px solid #e2e8f0; padding: 5px; text-align: right; color: #be123c;">
                            {{ $pRow->outflow > 0 ? '৳' . number_format($pRow->outflow, 2) : '-' }}
                        </td>
                        <td style="border: 1px solid #e2e8f0; padding: 5px; text-align: right; font-weight: 700;">
                            ৳{{ number_format($pRow->running_balance, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Signatures --}}
        <div style="display: flex; justify-content: space-between; margin-top: 60px; padding-top: 20px;">
            <div style="text-align: center; width: 180px; border-top: 1px dashed #000; padding-top: 5px; font-size: 11px;">
                Prepared By
            </div>
            <div style="text-align: center; width: 180px; border-top: 1px dashed #000; padding-top: 5px; font-size: 11px;">
                Accounts Department
            </div>
            <div style="text-align: center; width: 180px; border-top: 1px dashed #000; padding-top: 5px; font-size: 11px;">
                Authorized Signatory
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            // Select2 init
            $('.select').select2({ width: '100%' });

            // Tab Switching
            $('.ledger-tab-btn').on('click', function() {
                var target = $(this).data('target');
                $('.ledger-tab-btn').removeClass('active');
                $(this).addClass('active');

                $('.ledger-tab-pane').hide();
                $('#' + target).fadeIn(150);
            });

            // Live Search Filter
            $('#ledger-live-search').on('keyup', function() {
                var q = $(this).val().toLowerCase().trim();
                var visibleCount = 0;

                $('.ledger-tab-pane:visible .ledger-row').each(function() {
                    var searchData = $(this).data('search') || $(this).text().toLowerCase();
                    if (!q || searchData.indexOf(q) !== -1) {
                        $(this).show();
                        visibleCount++;
                    } else {
                        $(this).hide();
                    }
                });

                $('#filtered-count-badge').text(visibleCount + ' records');
            });

            // Quick Date Range Chips
            $('.date-chip').on('click', function() {
                $('.date-chip').removeClass('is-active');
                $(this).addClass('is-active');

                var range = $(this).data('range');
                var start = '', end = '';
                var now = new Date();

                function formatDate(d) {
                    var month = '' + (d.getMonth() + 1),
                        day = '' + d.getDate(),
                        year = d.getFullYear();
                    if (month.length < 2) month = '0' + month;
                    if (day.length < 2) day = '0' + day;
                    return [year, month, day].join('-');
                }

                if (range === 'today') {
                    start = formatDate(now);
                    end = formatDate(now);
                } else if (range === 'yesterday') {
                    var y = new Date();
                    y.setDate(y.getDate() - 1);
                    start = formatDate(y);
                    end = formatDate(y);
                } else if (range === 'this_week') {
                    var firstDay = new Date(now.setDate(now.getDate() - now.getDay()));
                    var lastDay = new Date();
                    start = formatDate(firstDay);
                    end = formatDate(lastDay);
                } else if (range === 'this_month') {
                    var first = new Date(now.getFullYear(), now.getMonth(), 1);
                    start = formatDate(first);
                    end = formatDate(new Date());
                } else if (range === 'last_month') {
                    var firstLast = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    var lastLast = new Date(now.getFullYear(), now.getMonth(), 0);
                    start = formatDate(firstLast);
                    end = formatDate(lastLast);
                } else if (range === 'this_year') {
                    var firstYear = new Date(now.getFullYear(), 0, 1);
                    start = formatDate(firstYear);
                    end = formatDate(new Date());
                }

                if (start && end) {
                    $('#start_date').val(start);
                    $('#end_date').val(end);
                    $('#ledger-filter-form').submit();
                }
            });

            // Highlight preset chip if dates match
            var curStart = $('#start_date').val();
            var curEnd = $('#end_date').val();
            var todayStr = new Date().toISOString().slice(0, 10);
            if (curStart === todayStr && curEnd === todayStr) {
                $('.date-chip[data-range="today"]').addClass('is-active');
            }

            // Export CSV
            $('#btn-export-csv').on('click', function() {
                var csv = [];
                var rows = document.querySelectorAll("#table-chronological tr");

                for (var i = 0; i < rows.length; i++) {
                    if (rows[i].offsetParent === null) continue; // Skip hidden rows
                    var row = [], cols = rows[i].querySelectorAll("td, th");
                    for (var j = 0; j < cols.length; j++) {
                        var cleanText = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/"/g, '""').trim();
                        row.push('"' + cleanText + '"');
                    }
                    csv.push(row.join(","));
                }

                var csvFile = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
                var downloadLink = document.createElement("a");
                downloadLink.download = "master-ledger-" + ($('#start_date').val() || 'export') + ".csv";
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.style.display = "none";
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
            });
        });
    </script>
@endpush
