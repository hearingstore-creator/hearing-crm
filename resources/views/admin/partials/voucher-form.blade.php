{{-- Shared Debit / Credit Voucher form.
     Needs: $kind ('debit'|'credit'), $heads, $methods, $action, $backUrl, $amountField,
     optional $voucher + $lines (edit). Debit vouchers have a typed voucher number and several lines. --}}
@php
    $credit = $kind === 'credit';
    $v = $voucher ?? null;
    $amountValue = $v ? abs($credit ? $v->income_amount : $v->expense_amount) : null;

    // Debit voucher lines: old input first, then the saved lines, else one blank line
    $lineRows = old('lines');
    if (!$credit && !is_array($lineRows)) {
        $lineRows = isset($lines)
            ? collect($lines)->map(fn ($l) => ['id' => $l->id, 'income_expense_head_id' => $l->income_expense_head_id, 'amount' => abs($l->expense_amount), 'details' => $l->details])->all()
            : [['id' => null, 'income_expense_head_id' => null, 'amount' => null, 'details' => null]];
    }
@endphp
<form action="{{ $action }}" enctype="multipart/form-data" method="post">
    @csrf
    @if ($v) @method('PUT') @endif
    <div class="crm-form-section crm-animate-in delay-1">
        <div class="crm-form-section-header">
            <div class="section-icon" style="background: var(--{{ $credit ? 'success' : 'danger' }}-500);"><i class="fas {{ $credit ? 'fa-hand-holding-usd' : 'fa-money-bill-wave' }}"></i></div>
            <h6>Voucher Details</h6>
        </div>
        <div class="crm-form-section-body">
            <div class="row">
                @if ($credit)
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="income_expense_head_id">Income Head <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control" name="income_expense_head_id" id="income_expense_head_id" required>
                                <option value="">Select Head</option>
                                @foreach ($heads as $val)
                                    <option value="{{ $val->id }}" @selected(old('income_expense_head_id', $v->income_expense_head_id ?? null) == $val->id)>{{ $val->head_name }}</option>
                                @endforeach
                            </select>
                            @error('income_expense_head_id')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                @else
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="voucher_no">Voucher No <span style="color: var(--danger-500);">*</span></label>
                            @if ($v)
                                <input type="text" class="form-control" id="voucher_no" value="{{ $v->voucher_no }}" disabled>
                            @else
                                <input type="text" class="form-control" name="voucher_no" id="voucher_no" maxlength="40" value="{{ old('voucher_no') }}" placeholder="Number from your voucher book" required>
                            @endif
                            @error('voucher_no')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                @endif
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="date">Voucher Date <span style="color: var(--danger-500);">*</span></label>
                        <input type="date" class="form-control" name="date" id="date" value="{{ old('date', $v ? \Carbon\Carbon::parse($v->date)->format('Y-m-d') : date('Y-m-d')) }}" required>
                        @error('date')<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                </div>
                @if ($credit)
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Voucher No</label>
                            <input type="text" class="form-control" value="{{ $v->voucher_no ?? 'Auto (CV-YYYYMMDD-0001)' }}" disabled>
                        </div>
                    </div>
                @else
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="payment_method">Payment Mode <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control" name="payment_method" id="payment_method" required>
                                @foreach ($methods as $m)
                                    <option value="{{ $m }}" @selected(old('payment_method', $v->payment_method ?? 'Cash') === $m)>{{ $m }}</option>
                                @endforeach
                            </select>
                            @error('payment_method')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                @endif
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="payee_name">{{ $credit ? 'Received From' : 'Paid To' }}</label>
                        <input type="text" class="form-control" name="payee_name" id="payee_name" maxlength="150" value="{{ old('payee_name', $v->payee_name ?? '') }}" placeholder="{{ $credit ? 'Person or company that paid you' : 'Person or company that received the money' }}">
                        @error('payee_name')<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                </div>
                @if ($credit)
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="{{ $amountField }}">Amount <span style="color: var(--danger-500);">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="{{ $amountField }}" id="{{ $amountField }}" value="{{ old($amountField, $amountValue) }}" required>
                            @error($amountField)<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="payment_method">Receipt Mode <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control" name="payment_method" id="payment_method" required>
                                @foreach ($methods as $m)
                                    <option value="{{ $m }}" @selected(old('payment_method', $v->payment_method ?? 'Cash') === $m)>{{ $m }}</option>
                                @endforeach
                            </select>
                            @error('payment_method')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>
                @endif
            </div>

            @unless ($credit)
                {{-- Lines: one expense head and amount per line --}}
                <div class="form-group">
                    <label>Lines <span style="color: var(--danger-500);">*</span></label>
                    @error('lines')<small class="text-danger d-block">{{ $message }}</small>@enderror
                    <table class="table table-sm" id="voucher-lines" style="margin-bottom: 6px;">
                        <thead>
                            <tr><th style="width:38%;">Expense Head</th><th style="width:18%;">Amount</th><th>Line remarks</th><th style="width:40px;"></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($lineRows as $i => $line)
                                <tr class="voucher-line">
                                    <td>
                                        <input type="hidden" name="lines[{{ $i }}][id]" value="{{ $line['id'] ?? '' }}">
                                        <select class="form-control form-control-sm" name="lines[{{ $i }}][income_expense_head_id]">
                                            <option value="">Select Head</option>
                                            @foreach ($heads as $val)
                                                <option value="{{ $val->id }}" @selected(($line['income_expense_head_id'] ?? null) == $val->id)>{{ $val->head_name }}</option>
                                            @endforeach
                                        </select>
                                        @error("lines.$i.income_expense_head_id")<small class="text-danger">{{ $message }}</small>@enderror
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm line-amount" name="lines[{{ $i }}][amount]" value="{{ $line['amount'] ?? '' }}">
                                        @error("lines.$i.amount")<small class="text-danger">{{ $message }}</small>@enderror
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" maxlength="500" name="lines[{{ $i }}][details]" value="{{ $line['details'] ?? '' }}"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove line">&times;</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><button type="button" class="btn btn-sm btn-secondary" id="add-line"><i class="fas fa-plus mr-1"></i>Add line</button></td>
                                <td colspan="3" style="font-weight:600;">Total: ৳<span id="lines-total">0.00</span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endunless

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="bank_name">Bank / Wallet Name</label>
                        <input type="text" class="form-control" name="bank_name" id="bank_name" maxlength="100" value="{{ old('bank_name', $v->bank_name ?? '') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="transaction_no">Cheque / Transaction No</label>
                        <input type="text" class="form-control" name="transaction_no" id="transaction_no" maxlength="100" value="{{ old('transaction_no', $v->transaction_no ?? '') }}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="details">Remarks / Particulars</label>
                        <textarea class="form-control" name="details" id="details" rows="3" maxlength="1000">{{ old('details', $credit ? ($v->details ?? '') : '') }}</textarea>
                        @unless ($credit)<small class="text-muted">Used for any line that has no remarks of its own.</small>@endunless
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="attachment">Bill / Receipt (JPG, PNG, PDF, max 4 MB)</label>
                        <input type="file" class="form-control" name="attachment" id="attachment" accept=".jpg,.jpeg,.png,.pdf">
                        @if ($v && $v->attachment_path)
                            <small class="text-muted">A file is attached. Choosing a new one replaces it.</small>
                        @endif
                        @error('attachment')<small class="text-danger d-block">{{ $message }}</small>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="crm-form-submit-bar">
        <a href="{{ $backUrl }}" class="btn btn-secondary btn-sm">Cancel</a>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>{{ $v ? 'Update Voucher' : 'Save' }}</button>
    </div>
</form>

@unless ($credit)
<script>
(function () {
    var body = document.querySelector('#voucher-lines tbody');
    if (!body) return;
    var total = document.getElementById('lines-total');

    function recount() {
        var sum = 0;
        body.querySelectorAll('.line-amount').forEach(function (i) { sum += parseFloat(i.value) || 0; });
        total.textContent = sum.toFixed(2);
    }
    function reindex() {
        body.querySelectorAll('tr.voucher-line').forEach(function (tr, n) {
            tr.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + n + ']');
            });
        });
    }
    document.getElementById('add-line').addEventListener('click', function () {
        var rows = body.querySelectorAll('tr.voucher-line');
        var copy = rows[rows.length - 1].cloneNode(true);
        copy.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        copy.querySelectorAll('select').forEach(function (s) { s.value = ''; });
        copy.querySelectorAll('small.text-danger').forEach(function (s) { s.remove(); });
        body.appendChild(copy);
        reindex();
    });
    body.addEventListener('click', function (e) {
        if (!e.target.classList.contains('remove-line')) return;
        if (body.querySelectorAll('tr.voucher-line').length > 1) {
            e.target.closest('tr').remove();
            reindex();
            recount();
        }
    });
    body.addEventListener('input', recount);
    recount();
})();
</script>
@endunless
