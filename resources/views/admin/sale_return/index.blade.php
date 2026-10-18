@extends('admin.layouts.master')
@section('page_title', 'Sales Returns (Credit Notes)')
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/sale'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
<style>
.badge-due-reduction { background-color: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; font-weight: 600; padding: 4px 8px; border-radius: 4px; }
.badge-store-credit { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-weight: 600; padding: 4px 8px; border-radius: 4px; }
.badge-cash-refund { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 600; padding: 4px 8px; border-radius: 4px; }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-undo-alt" style="color: #6366f1; margin-right: 8px;"></i>Sales Returns (Credit Notes)</h1>
            <p>Manage patient hearing aid & product returns, restocking fees/deductions, dues reduction, and credit notes</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/sale') }}" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-file-invoice mr-1"></i>Sales List
            </a>
            @can('permission', 'sale_return.create')
                <a href="{{ route('sale-returns.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>New Return / Credit Note
                </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Summary Cards --}}
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'color' => 'indigo', 'icon' => 'fas fa-undo-alt', 'id' => 'kpi-return-total',
            'label' => 'Total Returns', 'value' => number_format($totalReturnsCount),
            'sub' => 'Patient returns processed',
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'rose', 'icon' => 'fas fa-money-bill-wave', 'id' => 'kpi-return-gross',
            'label' => 'Gross Returned Value', 'value' => '৳' . number_format($totalReturnGross, 2),
            'sub' => 'Total original item value',
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'amber', 'icon' => 'fas fa-percentage', 'id' => 'kpi-return-deduction',
            'label' => 'Deductions Retained', 'value' => '৳' . number_format($totalDeductions, 2),
            'sub' => 'Restocking & usage fees',
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'emerald', 'icon' => 'fas fa-receipt', 'id' => 'kpi-return-credit',
            'label' => 'Net Credit Issued', 'value' => '৳' . number_format($totalCreditIssued, 2),
            'sub' => 'Due reduction & store credit',
        ])
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <div class="crm-filter-body" style="display: block;">
            <form method="GET" action="{{ route('sale-returns.index') }}">
                <div class="filter-row">
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Customer</label>
                        <select class="form-control select" name="customer_id">
                            <option value="">All Customers</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ $c->customer_uid }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if (auth()->user()->canViewAllBranches())
                        <div>
                            <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Branch</label>
                            <select class="form-control select" name="branch">
                                <option value="">All Branches</option>
                                <option value="hq" {{ request('branch') === 'hq' ? 'selected' : '' }}>Head Office</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ request('branch') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Settlement Type</label>
                        <select class="form-control select" name="settlement_type">
                            <option value="">All Types</option>
                            <option value="due_reduction" {{ request('settlement_type') === 'due_reduction' ? 'selected' : '' }}>Due Reduction</option>
                            <option value="store_credit" {{ request('settlement_type') === 'store_credit' ? 'selected' : '' }}>Store Credit</option>
                            <option value="cash_refund" {{ request('settlement_type') === 'cash_refund' ? 'selected' : '' }}>Cash Refund</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">From Date</label>
                        <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}">
                    </div>

                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">To Date</label>
                        <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}">
                    </div>

                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Search</label>
                        <input type="text" class="form-control" name="search" placeholder="CN No, Invoice, Patient..." value="{{ request('search') }}">
                    </div>

                    <div class="filter-actions" style="margin-top: auto;">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search mr-1"></i>Filter</button>
                        <a href="{{ route('sale-returns.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times mr-1"></i>Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Data Table --}}
    <div class="card shadow-sm border-0 crm-animate-in delay-2" style="border-radius: 8px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="vertical-align: middle;">
                    <thead style="background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">
                        <tr>
                            <th style="padding: 12px 16px;">Date</th>
                            <th>Credit Note #</th>
                            <th>Invoice #</th>
                            <th>Customer / Patient</th>
                            @if(auth()->user()->canViewAllBranches())
                                <th>Branch</th>
                            @endif
                            <th>Gross Value</th>
                            <th>Deduction</th>
                            <th>Net Credit Note</th>
                            <th>Settlement</th>
                            <th style="text-align: right; padding-right: 16px;">Action</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 13px;">
                        @forelse ($saleReturns as $ret)
                            <tr>
                                <td style="padding: 12px 16px; font-weight: 500;">
                                    {{ $ret->date ? $ret->date->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    <span class="font-weight-bold" style="color: #6366f1;">
                                        <a href="{{ route('sale-returns.show', $ret->id) }}" style="color: inherit; text-decoration: underline;">
                                            {{ $ret->credit_note_no }}
                                        </a>
                                    </span>
                                </td>
                                <td>
                                    @if ($ret->sale_invoice_no)
                                        <a href="{{ route('sale.show', $ret->saleMaster?->id ?: $ret->sale_invoice_no) }}" target="_blank" style="color: #0284c7; font-weight: 500;">
                                            #{{ $ret->sale_invoice_no }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark">{{ $ret->customer?->lead?->full_name ?? ($ret->customer?->name ?? 'N/A') }}</div>
                                    <div class="text-muted" style="font-size: 11px;">
                                        <i class="fas fa-phone-alt mr-1"></i>{{ $ret->customer?->lead?->phone_number ?? ($ret->customer?->phone ?? '—') }}
                                        @if($ret->customer?->customer_uid)
                                            <span class="badge badge-light border ml-1">{{ $ret->customer->customer_uid }}</span>
                                        @endif
                                    </div>
                                </td>
                                @if(auth()->user()->canViewAllBranches())
                                    <td>
                                        <span class="badge badge-secondary" style="font-weight: 500;">
                                            {{ $ret->vendor?->name ?? 'Head Office' }}
                                        </span>
                                    </td>
                                @endif
                                <td style="font-weight: 500; color: #475569;">
                                    ৳{{ number_format($ret->return_subtotal, 2) }}
                                </td>
                                <td style="color: #d97706; font-weight: 500;">
                                    @if($ret->deduction_amount > 0)
                                        -৳{{ number_format($ret->deduction_amount, 2) }}
                                    @else
                                        <span class="text-muted">৳0.00</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-weight-bold" style="color: #15803d; font-size: 14px;">
                                        ৳{{ number_format($ret->credit_note_amount, 2) }}
                                    </span>
                                    @if($ret->due_reduced_amount > 0)
                                        <div style="font-size: 10px; color: #1e40af; font-weight: 500;">
                                            <i class="fas fa-arrow-down mr-1"></i>Due Cleared: ৳{{ number_format($ret->due_reduced_amount, 2) }}
                                        </div>
                                    @endif
                                    @if($ret->patient_refund_amount > 0)
                                        <div style="font-size: 10px; color: #b91c1c; font-weight: 500;">
                                            <i class="fas fa-hand-holding-usd mr-1"></i>Refunded: ৳{{ number_format($ret->patient_refund_amount, 2) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($ret->settlement_type === 'due_reduction')
                                        <span class="badge-due-reduction"><i class="fas fa-file-invoice mr-1"></i>Due Reduction</span>
                                    @elseif($ret->settlement_type === 'store_credit')
                                        <span class="badge-store-credit"><i class="fas fa-wallet mr-1"></i>Store Credit</span>
                                    @elseif($ret->settlement_type === 'cash_refund')
                                        <span class="badge-cash-refund"><i class="fas fa-hand-holding-usd mr-1"></i>Cash Refund</span>
                                    @else
                                        <span class="badge badge-light">{{ ucfirst($ret->settlement_type) }}</span>
                                    @endif
                                </td>
                                <td style="text-align: right; padding-right: 16px;">
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('sale-returns.show', $ret->id) }}" class="btn btn-info btn-sm mr-1" title="View & Print Credit Note Voucher">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @can('permission', 'sale_return.delete')
                                            <form action="{{ route('sale-returns.destroy', $ret->id) }}" method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to void Credit Note {{ $ret->credit_note_no }}? This will reverse the stock and ledger adjustments.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Void Return">
                                                    <i class="far fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->canViewAllBranches() ? 10 : 9 }}" class="text-center py-5 text-muted">
                                    <i class="fas fa-undo-alt fa-3x mb-3 text-secondary" style="opacity: 0.4;"></i>
                                    <p class="mb-0 font-weight-bold">No sales returns found</p>
                                    <p class="text-muted" style="font-size: 12px;">Process patient returns and generate credit notes when devices or products are returned.</p>
                                    @can('permission', 'sale_return.create')
                                        <a href="{{ route('sale-returns.create') }}" class="btn btn-primary btn-sm mt-2">
                                            <i class="fas fa-plus mr-1"></i>New Return / Credit Note
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($saleReturns->hasPages())
                <div class="p-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted" style="font-size: 13px;">
                        Showing {{ $saleReturns->firstItem() }} to {{ $saleReturns->lastItem() }} of {{ $saleReturns->total() }} returns
                    </span>
                    <div>
                        {{ $saleReturns->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
