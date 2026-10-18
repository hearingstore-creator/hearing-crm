@extends('admin.layouts.master')
@section('page_title', 'Doctor Commission Settlement & Payment')
@section('breadcrumb_parent', 'Finance')
@section('breadcrumb_parent_url', route('doctors.payment.index'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<style>
.settlement-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    margin-bottom: 20px;
}
.mode-toggle-group {
    display: inline-flex;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.mode-toggle-btn {
    padding: 6px 16px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: all .15s ease;
}
.mode-toggle-btn.active {
    background: #ffffff;
    color: #4f46e5;
    box-shadow: 0 2px 4px rgba(0,0,0,.08);
}
.stat-pill {
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
}
.stat-pill .label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 2px;
}
.stat-pill .value {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
}
.stat-pill.highlight {
    background: #eff6ff;
    border-color: #bfdbfe;
}
.stat-pill.highlight .value {
    color: #1d4ed8;
}
.stat-pill.payable {
    background: #fef2f2;
    border-color: #fecaca;
}
.stat-pill.payable .value {
    color: #b91c1c;
}
.invoices-table {
    width: 100%;
    font-size: 12.5px;
    border-collapse: collapse;
}
.invoices-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    padding: 8px 12px;
    border-bottom: 2px solid #e2e8f0;
}
.invoices-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-hand-holding-usd" style="color: var(--accent-500); margin-right: 8px;"></i>Doctor Commission Settlement & Payment</h1>
            <p>Settle monthly or custom period commissions and disburse doctor payments</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('doctors.payment.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Payment List
            </a>
            <a href="{{ route('report.doctor-referral') }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-file-invoice mr-1"></i>Referral Report
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <form action="{{ route('doctors.payment.store') }}" method="POST" id="form-doctor-payment">
        @csrf
        <input type="hidden" name="settlement_type" id="input_settlement_type" value="month">
        <input type="hidden" name="settlement_period_start" id="input_period_start" value="">
        <input type="hidden" name="settlement_period_end" id="input_period_end" value="">

        <div class="row">
            {{-- Left Column: Doctor Selection & Period Controls --}}
            <div class="col-lg-7">
                {{-- Doctor & Settlement Period Card --}}
                <div class="settlement-card p-4 crm-animate-in delay-1">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h6 class="font-weight-bold mb-0 text-dark">
                            <i class="fas fa-user-md text-primary mr-1"></i> 1. Select Doctor & Settlement Period
                        </h6>
                        <span class="badge badge-light border text-muted" style="font-size: 11px;">Step 1 of 2</span>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark mb-1">Doctor <span class="text-danger">*</span></label>
                        <select name="doctor_id" id="doctor_id" class="form-control select2" required>
                            <option value="">-- Choose a Doctor --</option>
                            @foreach ($doctors as $d)
                                <option value="{{ $d->id }}" {{ (old('doctor_id', $selectedDoctorId) == $d->id) ? 'selected' : '' }}>
                                    {{ $d->name }} ({{ $d->qualification ?? 'MBBS' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Doctor Overall Balance Indicator --}}
                    <div id="doctor-balance-box" class="p-3 mb-3 rounded" style="background: #f8fafc; border: 1px dashed #cbd5e1; display: none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted d-block text-uppercase font-weight-bold" style="font-size: 10px;">Lifetime Overall Due</small>
                                <span class="h5 font-weight-bold mb-0 text-danger" id="lbl-overall-due">৳0.00</span>
                            </div>
                            <div class="text-right">
                                <small class="text-muted d-block" style="font-size: 11px;">Total Commission Earned: <span class="font-weight-bold text-dark" id="lbl-total-credit">৳0.00</span></small>
                                <small class="text-muted d-block" style="font-size: 11px;">Total Disbursed: <span class="font-weight-bold text-success" id="lbl-total-debit">৳0.00</span></small>
                            </div>
                        </div>
                    </div>

                    {{-- Settlement Mode Switcher --}}
                    <div class="mb-3">
                        <label class="font-weight-bold text-dark d-block mb-2">Settlement Mode</label>
                        <div class="mode-toggle-group">
                            <button type="button" class="mode-toggle-btn active" data-mode="month" id="btn-mode-month">
                                <i class="fas fa-calendar-alt mr-1"></i> Month-wise Settlement
                            </button>
                            <button type="button" class="mode-toggle-btn" data-mode="custom" id="btn-mode-custom">
                                <i class="fas fa-clock mr-1"></i> Custom Date Range
                            </button>
                            <button type="button" class="mode-toggle-btn" data-mode="general" id="btn-mode-general">
                                <i class="fas fa-hand-holding-usd mr-1"></i> General Advance / Lump Sum
                            </button>
                        </div>
                    </div>

                    {{-- Mode A: Month-wise selector --}}
                    <div id="mode-month-container" class="mb-3">
                        <label class="small font-weight-bold text-muted mb-1">Select Month to Settle</label>
                        <select id="select-month" class="form-control">
                            @foreach($months as $idx => $m)
                                <option value="{{ $m['start'] }}|{{ $m['end'] }}" {{ $idx === 0 ? 'selected' : '' }}>
                                    {{ $m['label'] }} ({{ Carbon\Carbon::parse($m['start'])->format('d M') }} — {{ Carbon\Carbon::parse($m['end'])->format('d M') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Mode B: Custom Date Range selector --}}
                    <div id="mode-custom-container" class="mb-3" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold text-muted mb-1">From Date</label>
                                <input type="date" id="custom_start_date" class="form-control form-control-sm" value="{{ Carbon\Carbon::now()->startOfMonth()->toDateString() }}">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold text-muted mb-1">To Date</label>
                                <input type="date" id="custom_end_date" class="form-control form-control-sm" value="{{ Carbon\Carbon::now()->toDateString() }}">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold" id="btn-fetch-breakdown">
                            <i class="fas fa-sync-alt mr-1"></i> Fetch Period Invoices
                        </button>
                        <small class="text-muted" id="breakdown-status"></small>
                    </div>
                </div>

                {{-- Live Referred Invoices Breakdown Card --}}
                <div class="settlement-card p-4 crm-animate-in delay-2" id="breakdown-card" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h6 class="font-weight-bold mb-0 text-dark">
                            <i class="fas fa-file-invoice text-info mr-1"></i> Referred Patient Invoices in Period
                        </h6>
                        <span class="badge badge-info px-2 py-1" id="lbl-invoice-count">0 Invoices</span>
                    </div>

                    {{-- Stat Pills for Selected Period --}}
                    <div class="row mb-3">
                        <div class="col-4">
                            <div class="stat-pill highlight">
                                <div class="label">Period Commission</div>
                                <div class="value" id="val-period-commission">৳0.00</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-pill">
                                <div class="label">Already Paid</div>
                                <div class="value text-success" id="val-period-paid">৳0.00</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-pill payable">
                                <div class="label">Period Net Due</div>
                                <div class="value" id="val-period-net-due">৳0.00</div>
                            </div>
                        </div>
                    </div>

                    {{-- Referred Invoices Table --}}
                    <div style="max-height: 280px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <table class="invoices-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Invoice #</th>
                                    <th style="text-align: right;">Sale (৳)</th>
                                    <th style="text-align: right;">Commission (৳)</th>
                                </tr>
                            </thead>
                            <tbody id="invoices-tbody">
                                {{-- Populated via AJAX --}}
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 text-right">
                        <button type="button" class="btn btn-xs btn-outline-danger font-weight-bold" id="btn-fill-period-due">
                            <i class="fas fa-check-circle mr-1"></i> Fill Payment Amount with Period Due
                        </button>
                    </div>
                </div>
            </div>

            {{-- Right Column: Payment Disbursement Details --}}
            <div class="col-lg-5">
                <div class="settlement-card p-4 crm-animate-in delay-2">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h6 class="font-weight-bold mb-0 text-dark">
                            <i class="fas fa-money-check-alt text-success mr-1"></i> 2. Payment Disbursement
                        </h6>
                        <span class="badge badge-light border text-muted" style="font-size: 11px;">Step 2 of 2</span>
                    </div>

                    {{-- Payment Date --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark mb-1">Disbursement Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>

                    {{-- Amount Paid (Debit) --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark mb-1">Payment Amount (৳) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text font-weight-bold bg-light">৳</span>
                            </div>
                            <input type="number" step="0.01" min="0.01" name="debit" id="input_debit" class="form-control font-weight-bold text-success" style="font-size: 17px;" placeholder="0.00" required>
                        </div>
                    </div>

                    {{-- Payment Method --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark mb-1">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-control" required>
                            <option value="Cash" selected>Cash</option>
                            <option value="Bank Transfer">Bank Transfer / EFTN / RTGS</option>
                            <option value="Cheque">Cheque</option>
                            <option value="bKash">bKash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    {{-- Bank & Reference Fields (Conditional) --}}
                    <div id="bank-fields" style="display: none;">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Bank Name / Wallet</label>
                            <input type="text" name="bank_name" class="form-control form-control-sm" placeholder="e.g. City Bank, Dhaka Bank, bKash Merchant">
                        </div>
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Cheque / Transaction Ref #</label>
                            <input type="text" name="transaction_no" class="form-control form-control-sm" placeholder="e.g. CHQ-482091 or Trx ID">
                        </div>
                    </div>

                    {{-- Remarks --}}
                    <div class="form-group mb-3">
                        <label class="small font-weight-bold text-muted mb-1">Payment Remarks (Optional)</label>
                        <textarea name="details" class="form-control" rows="2" placeholder="e.g. Full commission payment for September 2026"></textarea>
                    </div>

                    {{-- General Ledger Notice --}}
                    <div class="alert alert-light border small text-muted mb-3" style="border-radius: 8px;">
                        <i class="fas fa-info-circle text-primary mr-1"></i>
                        <strong>Accounting Auto-Post:</strong> This disbursement will automatically be logged as an Expense in the Company General Ledger under <strong>Head #1 (Doctor Payment)</strong>, ensuring 100% accurate Profit & Loss reports.
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('doctors.payment.index') }}" class="btn btn-secondary btn-sm mr-2 flex-grow-1">Cancel</a>
                        <button type="submit" class="btn btn-success btn-sm flex-grow-1 font-weight-bold">
                            <i class="fas fa-save mr-1"></i> Confirm & Print Slip
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({ width: '100%' });

    var currentPeriodNetDue = 0;

    // Show/hide bank fields based on payment method
    $('#payment_method').on('change', function() {
        var m = $(this).val();
        if (m === 'Cash') {
            $('#bank-fields').slideUp(150);
        } else {
            $('#bank-fields').slideDown(150);
        }
    });

    // Handle settlement mode tabs
    $('.mode-toggle-btn').on('click', function() {
        $('.mode-toggle-btn').removeClass('active');
        $(this).addClass('active');

        var mode = $(this).data('mode');
        $('#input_settlement_type').val(mode);

        if (mode === 'month') {
            $('#mode-month-container').slideDown(150);
            $('#mode-custom-container').slideUp(150);
            $('#breakdown-card').slideDown(150);
            syncDatesFromMonth();
        } else if (mode === 'custom') {
            $('#mode-month-container').slideUp(150);
            $('#mode-custom-container').slideDown(150);
            $('#breakdown-card').slideDown(150);
            syncDatesFromCustom();
        } else {
            $('#mode-month-container').slideUp(150);
            $('#mode-custom-container').slideUp(150);
            $('#breakdown-card').slideUp(150);
            $('#input_period_start').val('');
            $('#input_period_end').val('');
        }
    });

    $('#select-month').on('change', function() {
        syncDatesFromMonth();
        fetchBreakdown();
    });

    $('#custom_start_date, #custom_end_date').on('change', function() {
        syncDatesFromCustom();
    });

    function syncDatesFromMonth() {
        var val = $('#select-month').val();
        if (val) {
            var parts = val.split('|');
            $('#input_period_start').val(parts[0]);
            $('#input_period_end').val(parts[1]);
        }
    }

    function syncDatesFromCustom() {
        $('#input_period_start').val($('#custom_start_date').val());
        $('#input_period_end').val($('#custom_end_date').val());
    }

    // Doctor selection changes: fetch lifetime due and period breakdown
    $('#doctor_id').on('change', function() {
        var docId = $(this).val();
        if (!docId) {
            $('#doctor-balance-box').slideUp(150);
            $('#breakdown-card').slideUp(150);
            return;
        }

        // Fetch overall due
        $.ajax({
            url: "{{ url('admin/doctors-due') }}/" + docId,
            type: "GET",
            dataType: "json",
            success: function(res) {
                if (res.success) {
                    $('#lbl-overall-due').text('৳' + parseFloat(res.due_amount).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    $('#lbl-total-credit').text('৳' + parseFloat(res.total_credit).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    $('#lbl-total-debit').text('৳' + parseFloat(res.total_debit).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    $('#doctor-balance-box').slideDown(150);
                }
            }
        });

        if ($('#input_settlement_type').val() !== 'general') {
            fetchBreakdown();
        }
    });

    $('#btn-fetch-breakdown').on('click', function() {
        fetchBreakdown();
    });

    function fetchBreakdown() {
        var docId = $('#doctor_id').val();
        var start = $('#input_period_start').val();
        var end = $('#input_period_end').val();

        if (!docId || !start || !end) {
            return;
        }

        $('#breakdown-status').html('<i class="fas fa-spinner fa-spin text-primary mr-1"></i>Loading...');

        $.ajax({
            url: "{{ route('doctors.period-unsettled') }}",
            type: "GET",
            data: {
                doctor_id: docId,
                start_date: start,
                end_date: end
            },
            dataType: "json",
            success: function(res) {
                $('#breakdown-status').text('');
                if (res.success) {
                    $('#breakdown-card').slideDown(200);
                    $('#lbl-invoice-count').text(res.sales_count + ' Invoices');

                    $('#val-period-commission').text('৳' + parseFloat(res.period_commission).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    $('#val-period-paid').text('৳' + parseFloat(res.period_paid).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    $('#val-period-net-due').text('৳' + parseFloat(res.period_net_payable).toLocaleString('en-US', {minimumFractionDigits: 2}));

                    currentPeriodNetDue = parseFloat(res.period_net_payable);

                    // Auto suggest amount if input is empty
                    if (!$('#input_debit').val() || parseFloat($('#input_debit').val()) === 0) {
                        $('#input_debit').val(res.suggested_amount > 0 ? res.suggested_amount.toFixed(2) : '');
                    }

                    // Render table rows
                    var tbody = $('#invoices-tbody');
                    tbody.empty();

                    if (res.sales && res.sales.length > 0) {
                        $.each(res.sales, function(i, s) {
                            var tr = $('<tr></tr>');
                            tr.append('<td class="text-muted">' + s.date + '</td>');
                            tr.append('<td><strong>' + s.patient_name + '</strong><br><small class="text-muted">' + (s.patient_phone || '') + '</small></td>');
                            tr.append('<td><span class="badge badge-light border">#' + s.sale_invoice_no + '</span></td>');
                            tr.append('<td style="text-align: right;">৳' + parseFloat(s.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2}) + '</td>');
                            tr.append('<td style="text-align: right; font-weight: 700; color: #166534;">৳' + parseFloat(s.commission_amount).toLocaleString('en-US', {minimumFractionDigits: 2}) + '</td>');
                            tbody.append(tr);
                        });
                    } else {
                        tbody.append('<tr><td colspan="5" class="text-center text-muted py-3">No referred sales found in this period.</td></tr>');
                    }
                }
            },
            error: function(xhr) {
                $('#breakdown-status').text('Error fetching data.');
            }
        });
    }

    $('#btn-fill-period-due').on('click', function() {
        if (currentPeriodNetDue > 0) {
            $('#input_debit').val(currentPeriodNetDue.toFixed(2));
        }
    });

    // Initialize initial dates
    syncDatesFromMonth();
    if ($('#doctor_id').val()) {
        $('#doctor_id').trigger('change');
    }
});
</script>
@endpush
