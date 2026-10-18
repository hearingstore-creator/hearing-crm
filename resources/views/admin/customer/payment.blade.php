@extends('admin.layouts.master')
@section('page_title', 'Customer Payment')
@section('breadcrumb_parent', 'Customers')
@section('breadcrumb_parent_url', url('admin/customers'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .inv-info-box {
        background: linear-gradient(135deg, rgba(238, 242, 255, 0.7) 0%, rgba(243, 232, 255, 0.5) 100%);
        border: 1px solid #c7d2fe;
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 18px;
        display: none;
    }
    .inv-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 12px;
        margin-top: 8px;
    }
    .inv-info-item .label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--page-text-muted);
        display: block;
        margin-bottom: 2px;
    }
    .inv-info-item .val {
        font-size: 14px;
        font-weight: 700;
        color: var(--page-text);
    }
    .inv-info-item .val.due {
        color: #ef4444;
    }
    .inv-info-item .val.paid {
        color: #10b981;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-money-bill-wave" style="color: var(--accent-500); margin-right: 8px;"></i>Customer Payment & Due Collection</h1>
            <p>Record invoice-wise due collections or general customer ledger transactions</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/sales/due-list') }}" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-clock mr-1"></i>Patient Due List
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1" style="padding: 0;">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-receipt" style="color: var(--page-text-muted);"></i> Payment Details</div>
        </div>
        <div style="padding: 24px;">
            <form action="{{ url('admin/customer-payment') }}"
                  enctype="multipart/form-data"
                  method="post"
                  data-customer-search="{{ url('/admin/customer-search') }}"
                  class="customer-payment"
                  id="customerPaymentForm">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="crm-form-label">Customer <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control customer" name="customer_id" id="payment_customer_id" required>
                                @if(isset($preselectedCustomer) && $preselectedCustomer)
                                    <option value="{{ $preselectedCustomer->id }}" selected>
                                        {{ $preselectedCustomer->lead->full_name ?? $preselectedCustomer->name }} ({{ $preselectedCustomer->lead->phone_number ?? '' }}) [{{ $preselectedCustomer->customer_uid }}]
                                    </option>
                                @else
                                    <option value="">Select Customer</option>
                                @endif
                            </select>
                        </div>

                        {{-- Specific Invoice Selection --}}
                        <div class="form-group" id="wrap-invoice-select" style="display: none;">
                            <label class="crm-form-label">
                                <i class="fas fa-file-invoice mr-1" style="color: var(--accent-500);"></i>Select Invoice (For Invoice-Wise Due Collection)
                            </label>
                            <select class="form-control" name="sale_invoice_no" id="sale_invoice_no">
                                <option value="">-- General / Unallocated Account Payment --</option>
                            </select>
                            <small class="text-muted" id="invoice-status-hint">Choose an invoice to apply this payment directly to its balance and promise date.</small>
                        </div>

                        {{-- Invoice Summary Pill Box --}}
                        <div class="inv-info-box" id="inv-info-box">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="font-size: 13px; color: var(--accent-700);"><i class="fas fa-info-circle mr-1"></i>Selected Invoice Summary</strong>
                                <span class="badge badge-light" id="inv-date-badge">Date: —</span>
                            </div>
                            <div class="inv-info-grid">
                                <div class="inv-info-item">
                                    <span class="label">Total Bill</span>
                                    <div class="val" id="inv-total-disp">৳0.00</div>
                                </div>
                                <div class="inv-info-item">
                                    <span class="label">Already Paid</span>
                                    <div class="val paid" id="inv-paid-disp">৳0.00</div>
                                </div>
                                <div class="inv-info-item">
                                    <span class="label">Current Due</span>
                                    <div class="val due" id="inv-due-disp">৳0.00</div>
                                </div>
                                <div class="inv-info-item">
                                    <span class="label">Current Promise</span>
                                    <div class="val" id="inv-promise-disp" style="font-size: 12px; font-weight: 600;">—</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="crm-form-label">Type <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control" name="type" id="payment_type" required>
                                <option value="">Select Type</option>
                                @foreach ($transactionType as $key => $val)
                                    <option value="{{ $val }}" @selected($val === 'Receive')>{{ $val }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="crm-form-label">Payment Date <span style="color: var(--danger-500);">*</span></label>
                            <input type="date" class="form-control" name="date" value="{{ date('Y-m-d') }}" id="date" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="crm-form-label">Amount (৳) <span style="color: var(--danger-500);">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="paid_amount" id="paid_amount" placeholder="Enter amount" required style="font-size: 16px; font-weight: 600;">
                        </div>

                        {{-- New Promise Date (Shown if partial payment against an invoice) --}}
                        <div class="form-group" id="wrap-new-promise-date" style="display: none;">
                            <label class="crm-form-label">
                                <i class="fas fa-calendar-alt mr-1" style="color: var(--accent-500);"></i>New Due / Promise Date (For Remaining Balance)
                            </label>
                            <input type="date" class="form-control" name="new_due_date" id="new_due_date" min="{{ date('Y-m-d') }}">
                            <small class="text-muted">If this payment is partial, set when the patient promised to clear the rest.</small>
                        </div>

                        <div class="form-group">
                            <label class="crm-form-label">Remarks / Note</label>
                            <textarea class="form-control" name="details" id="details" rows="4" placeholder="Enter receipt details, reference, or note"></textarea>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--gray-200); margin-top: 8px;">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check mr-1"></i>Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(document).ready(function() {
    let customerInvoices = {};
    let selectedInvoiceDue = 0;

    function loadCustomerInvoices(customerId, preselectInvoiceNo) {
        if (!customerId) {
            $('#wrap-invoice-select').hide();
            $('#inv-info-box').hide();
            $('#sale_invoice_no').empty().append('<option value="">-- General / Unallocated Account Payment --</option>');
            return;
        }

        $.ajax({
            url: "{{ url('/admin/sales/unpaid-invoices') }}",
            type: "GET",
            data: { customer_id: customerId },
            dataType: "json",
            success: function(invoices) {
                customerInvoices = {};
                var $invSelect = $('#sale_invoice_no');
                $invSelect.empty();
                $invSelect.append('<option value="">-- General / Unallocated Account Payment --</option>');

                if (invoices && invoices.length > 0) {
                    invoices.forEach(function(inv) {
                        customerInvoices[inv.sale_invoice_no] = inv;
                        var promiseInfo = inv.due_date_formatted ? (' · Promise: ' + inv.due_date_formatted) : '';
                        $invSelect.append(
                            $('<option>', {
                                value: inv.sale_invoice_no,
                                text: '#' + inv.sale_invoice_no + ' (' + inv.date_formatted + ') · Due: ৳' + parseFloat(inv.due).toLocaleString() + promiseInfo
                            })
                        );
                    });

                    $('#wrap-invoice-select').slideDown(200);

                    if (preselectInvoiceNo && customerInvoices[preselectInvoiceNo]) {
                        $invSelect.val(preselectInvoiceNo).trigger('change');
                    }
                } else {
                    $('#wrap-invoice-select').hide();
                    $('#inv-info-box').slideUp(200);
                }
            }
        });
    }

    // Trigger on customer change
    $('#payment_customer_id').on('change', function() {
        var cid = $(this).val();
        loadCustomerInvoices(cid);
    });

    // Check if customer preselected on page load
    var initialCustomerId = $('#payment_customer_id').val();
    var urlParams = new URLSearchParams(window.location.search);
    var preselectedInv = urlParams.get('invoice_no');
    if (initialCustomerId) {
        loadCustomerInvoices(initialCustomerId, preselectedInv);
    }

    // When invoice is chosen
    $('#sale_invoice_no').on('change', function() {
        var invNo = $(this).val();
        if (invNo && customerInvoices[invNo]) {
            var inv = customerInvoices[invNo];
            selectedInvoiceDue = parseFloat(inv.due);

            $('#inv-date-badge').text('Invoice Date: ' + inv.date_formatted);
            $('#inv-total-disp').text('৳' + parseFloat(inv.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2}));
            $('#inv-paid-disp').text('৳' + parseFloat(inv.paid).toLocaleString(undefined, {minimumFractionDigits: 2}));
            $('#inv-due-disp').text('৳' + selectedInvoiceDue.toLocaleString(undefined, {minimumFractionDigits: 2}));
            $('#inv-promise-disp').text(inv.due_date_formatted || 'No promise date');

            $('#inv-info-box').slideDown(200);

            // Set type to Receive
            $('#payment_type').val('Receive');

            // Auto-fill amount with current due
            $('#paid_amount').val(selectedInvoiceDue.toFixed(2));
            checkPartialPayment(selectedInvoiceDue, selectedInvoiceDue);
        } else {
            selectedInvoiceDue = 0;
            $('#inv-info-box').slideUp(200);
            $('#wrap-new-promise-date').slideUp(200);
        }
    });

    function checkPartialPayment(entered, totalDue) {
        if (selectedInvoiceDue > 0 && entered < (totalDue - 0.009)) {
            $('#wrap-new-promise-date').slideDown(200);
        } else {
            $('#wrap-new-promise-date').slideUp(200);
        }
    }

    $('#paid_amount').on('input', function() {
        var entered = parseFloat($(this).val()) || 0;
        if (selectedInvoiceDue > 0) {
            checkPartialPayment(entered, selectedInvoiceDue);
        }
    });
});
</script>
@endpush
