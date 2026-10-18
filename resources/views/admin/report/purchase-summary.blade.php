@extends('admin.layouts.master')
@section('page_title', 'Purchase Report')
@section('breadcrumb_parent', 'Reports')
@section('breadcrumb_parent_url', url('admin/report/purchase-summary'))

@push('css')
    @include('admin.report.partials.report-styles')
@endpush

@php
    $money = fn ($v) => '৳' . number_format((float) $v, 2);
    $periodText = $start->format('d M Y') . ' — ' . $end->format('d M Y');
    $query = request()->except(['export', 'page']);
    $groups = ['invoice' => 'Invoice wise', 'supplier' => 'Supplier wise', 'day' => 'Day wise', 'month' => 'Month wise'];
    $due = (float) $totals->total - (float) $totals->paid;
    $supplierName = $supplierId ? optional($suppliers->firstWhere('id', $supplierId))->supplier_name : null;
    $branchName = $isVendor ? auth()->user()->name : ($vendorFilter === 'hq' ? 'Head Office' : (($vendorFilter && $vendorFilter !== 'all') ? optional($vendors->firstWhere('id', (int) $vendorFilter))->name : 'All branches'));
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-shopping-basket" style="color: var(--accent-500); margin-right: 8px;"></i>Purchase Report</h1>
            <p>Purchases by invoice, supplier, day or month for any time frame</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/report/purchase-summary') . '?' . http_build_query($query + ['export' => 'csv']) }}" class="btn btn-secondary btn-sm" id="btn-export-csv">
                <i class="fas fa-file-csv mr-1"></i>Export CSV
            </a>
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" id="btn-print"><i class="fas fa-print mr-1"></i>Print</button>
        </div>
    </div>

    <div class="rpt-filter crm-animate-in delay-1">
        <form method="GET" action="{{ url('admin/report/purchase-summary') }}">
            <div class="f-group wide">
                <label for="f-supplier">Supplier</label>
                <select name="supplier_id" id="f-supplier" class="select">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>{{ $s->supplier_name }}{{ $s->vendor_name ? ' — ' . $s->vendor_name : '' }}</option>
                    @endforeach
                </select>
            </div>
            @unless ($isVendor)
                <div class="f-group">
                    <label for="f-vendor">Branch</label>
                    <select name="vendor_id" id="f-vendor">
                        <option value="all">All branches</option>
                        <option value="hq" {{ $vendorFilter === 'hq' ? 'selected' : '' }}>Head Office</option>
                        @foreach ($vendors as $v)
                            <option value="{{ $v->id }}" {{ (string) $vendorFilter === (string) $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div class="f-group">
                <label for="f-group">Group by</label>
                <select name="group_by" id="f-group">
                    @foreach ($groups as $k => $label)
                        <option value="{{ $k }}" {{ $groupBy === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @include('admin.report.partials.period-filter')
            <div class="f-actions">
                <button type="submit" class="btn btn-primary btn-sm" id="btn-filter"><i class="fas fa-search mr-1"></i>Show</button>
                <a href="{{ url('admin/report/purchase-summary') }}" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="fas fa-undo"></i></a>
            </div>
        </form>
        <div class="period-chips">
            @foreach ($groups as $k => $label)
                <a href="{{ url('admin/report/purchase-summary') . '?' . http_build_query(array_merge($query, ['group_by' => $k])) }}" class="{{ $groupBy === $k ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
            <span style="width:1px;background:var(--card-border);margin:0 4px;"></span>
            @foreach (['today', 'this_month', 'last_month', 'this_quarter', 'this_year'] as $p)
                <a href="{{ url('admin/report/purchase-summary') . '?' . http_build_query(array_merge(\Illuminate\Support\Arr::except($query, ['start_date', 'end_date']), ['period' => $p])) }}" class="{{ $period === $p ? 'active' : '' }}">{{ $periods[$p] }}</a>
            @endforeach
        </div>
    </div>

    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', ['color' => 'indigo', 'icon' => 'fas fa-file-invoice', 'label' => 'Invoices', 'value' => number_format($totals->invoices), 'sub' => '<b>' . $totals->suppliers . '</b> suppliers · <b>' . number_format($totals->qty) . '</b> units'])
        @include('admin.partials.kpi-card', ['color' => 'orange', 'icon' => 'fas fa-shopping-basket', 'label' => 'Total Purchase', 'value' => $money($totals->total), 'sub' => 'Discount <b>' . $money($totals->discount) . '</b> · VAT <b>' . $money($totals->vat) . '</b>'])
        @include('admin.partials.kpi-card', ['color' => 'green', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Paid', 'value' => $money($totals->paid), 'sub' => '<b>' . ($totals->total > 0 ? round($totals->paid / $totals->total * 100) : 0) . '%</b> of purchases'])
        @include('admin.partials.kpi-card', ['color' => 'rose', 'icon' => 'fas fa-hourglass-half', 'label' => 'Due', 'value' => $money($due), 'sub' => 'unpaid on these invoices'])
        @include('admin.partials.kpi-card', ['color' => 'red', 'icon' => 'fas fa-chart-line-down', 'label' => 'Return Loss', 'value' => $money($totalReturnLoss ?? 0), 'sub' => 'disallowed difference'])
    </div>

    <div class="stmt crm-animate-in delay-2" id="print-area">
        @include('admin.report.partials.statement-header', [
            'title' => 'Purchase Report',
            'subtitle' => 'Period: <b>' . $periodText . '</b><br>' . e($groups[$groupBy]) . ' · ' . e($branchName ?? 'All branches') . ($supplierName ? ' · Supplier: <b>' . e($supplierName) . '</b>' : ''),
        ])

        <div style="overflow-x:auto;">
            <table class="stmt-table" id="tbl-purchase-report">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        @if ($groupBy === 'invoice')
                            <th>Date</th><th>Invoice</th><th>Supplier</th>@unless($isVendor)<th>Branch</th>@endunless
                            <th class="num">Qty</th>
                        @else
                            <th>{{ ['supplier' => 'Supplier', 'day' => 'Date', 'month' => 'Month'][$groupBy] }}</th>
                            <th class="num">Invoices</th><th class="num">Qty</th>
                        @endif
                        <th class="num">Discount</th>
                        <th class="num">VAT</th>
                        <th class="num">Total</th>
                        <th class="num">Paid</th>
                        <th class="num">Due</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $i => $r)
                        @php $rowDue = (float) $r->total - (float) $r->paid; @endphp
                        <tr>
                            <td class="muted">{{ $i + 1 }}</td>
                            @if ($groupBy === 'invoice')
                                <td class="muted">{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td>
                                <td><a href="{{ url('admin/purchase/' . $r->id) }}" target="_blank">{{ $r->purchase_invoice_no }}</a></td>
                                <td>{{ $r->supplier_name ?? '—' }}</td>
                                @unless($isVendor)<td class="muted">{{ $r->vendor_name ?? 'Head Office' }}</td>@endunless
                                <td class="num">{{ number_format($r->qty) }}</td>
                            @else
                                <td>
                                    @if ($groupBy === 'supplier')
                                        <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(['supplier_id' => $r->supplier_id, 'period' => 'custom', 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()]) }}">{{ $r->label ?? '—' }}</a>
                                        <div class="muted" style="font-size:11px;">Last purchase {{ \Carbon\Carbon::parse($r->last_date)->format('d M Y') }}</div>
                                    @elseif ($groupBy === 'day')
                                        {{ \Carbon\Carbon::parse($r->label)->format('D, d M Y') }}
                                    @else
                                        {{ \Carbon\Carbon::createFromFormat('Y-m', $r->label)->format('F Y') }}
                                    @endif
                                </td>
                                <td class="num">{{ number_format($r->invoices) }}</td>
                                <td class="num">{{ number_format($r->qty) }}</td>
                            @endif
                            <td class="num muted">{{ $r->discount > 0 ? $money($r->discount) : '—' }}</td>
                            <td class="num muted">{{ $r->vat > 0 ? $money($r->vat) : '—' }}</td>
                            <td class="num"><b>{{ $money($r->total) }}</b></td>
                            <td class="num dr">{{ $money($r->paid) }}</td>
                            <td class="num {{ $rowDue > 0.009 ? 'cr' : 'muted' }}">{{ $rowDue > 0.009 ? $money($rowDue) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="stmt-empty"><i class="far fa-folder-open"></i>No purchases found for this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            @if ($groupBy === 'invoice')
                                <td colspan="{{ $isVendor ? 4 : 5 }}">Total · {{ number_format($totals->invoices) }} invoices</td>
                                <td class="num">{{ number_format($totals->qty) }}</td>
                            @else
                                <td colspan="2">Total</td>
                                <td class="num">{{ number_format($totals->invoices) }}</td>
                                <td class="num">{{ number_format($totals->qty) }}</td>
                            @endif
                            <td class="num">{{ $money($totals->discount) }}</td>
                            <td class="num">{{ $money($totals->vat) }}</td>
                            <td class="num">{{ $money($totals->total) }}</td>
                            <td class="num">{{ $money($totals->paid) }}</td>
                            <td class="num">{{ $money($due) }}</td>
                        </tr>
                    </tfoot>
                @endif
        </div>

        {{-- Purchase Return Loss Breakdown (Per Supplier & Per Month) --}}
        <div class="mt-4 pt-3 border-top">
            <h5 class="font-weight-bold mb-3" style="font-size: 15px; color: #991b1b;">
                <i class="fas fa-exclamation-circle mr-2 text-danger"></i>Purchase Return Loss Breakdown
            </h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px;">
                        <h6 style="font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 12px; text-transform: uppercase;">
                            <i class="fas fa-building mr-1 text-primary"></i>Loss by Supplier
                        </h6>
                        <div style="overflow-x: auto;">
                            <table class="table table-sm table-bordered mb-0" style="font-size: 12px;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th>Supplier</th>
                                        <th style="text-align: center; width: 60px;">Returns</th>
                                        <th style="text-align: right; width: 90px;">Return Val</th>
                                        <th style="text-align: right; width: 90px;">Accepted</th>
                                        <th style="text-align: right; width: 90px; color: #b91c1c;">Loss</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($returnLossBySupplier as $ls)
                                        <tr>
                                            <td><strong>{{ $ls->supplier_name }}</strong></td>
                                            <td style="text-align: center;">{{ $ls->return_count }}</td>
                                            <td style="text-align: right;">৳{{ number_format($ls->total_return_val, 2) }}</td>
                                            <td style="text-align: right; color: #166534;">৳{{ number_format($ls->total_accepted_val, 2) }}</td>
                                            <td style="text-align: right; font-weight: 700; color: #b91c1c;">৳{{ number_format($ls->total_loss, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">No purchase return losses recorded for this period.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px;">
                        <h6 style="font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 12px; text-transform: uppercase;">
                            <i class="fas fa-calendar-alt mr-1 text-info"></i>Loss by Month
                        </h6>
                        <div style="overflow-x: auto;">
                            <table class="table table-sm table-bordered mb-0" style="font-size: 12px;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th>Month</th>
                                        <th style="text-align: center; width: 60px;">Returns</th>
                                        <th style="text-align: right; width: 90px;">Return Val</th>
                                        <th style="text-align: right; width: 90px;">Accepted</th>
                                        <th style="text-align: right; width: 90px; color: #b91c1c;">Loss</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($returnLossByMonth as $lm)
                                        <tr>
                                            <td><strong>{{ \Carbon\Carbon::createFromFormat('Y-m', $lm->month_label)->format('F Y') }}</strong></td>
                                            <td style="text-align: center;">{{ $lm->return_count }}</td>
                                            <td style="text-align: right;">৳{{ number_format($lm->total_return_val, 2) }}</td>
                                            <td style="text-align: right; color: #166534;">৳{{ number_format($lm->total_accepted_val, 2) }}</td>
                                            <td style="text-align: right; font-weight: 700; color: #b91c1c;">৳{{ number_format($lm->total_loss, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">No monthly return losses recorded.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="stmt-foot">
            <span>Due = Total − Paid (at purchase). Later supplier payments appear in the Supplier Statement.</span>
            <span>Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
@endpush
