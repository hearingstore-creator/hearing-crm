{{-- Per-model commission overrides for a doctor. Needs $products (id, model) and optional $rules (existing DoctorProductCommission rows). --}}
@php
    $productRows = old('product_commissions', ($rules ?? collect())->map(fn ($r) => ['product_id' => $r->product_id, 'commission_type' => $r->commission_type, 'commission_value' => $r->commission_value])->values()->all());
@endphp
<div class="crm-form-section crm-animate-in delay-4" id="product-rules-card">
    <div class="crm-form-section-header">
        <div class="section-icon" style="background: #0ea5e9;"><i class="fas fa-box-open"></i></div>
        <div>
            <h6>Model-Wise Commission</h6>
            <div style="font-size: 12px; color: var(--page-text-muted);">A rate for one specific product or model. It beats the category rate and the default (for example Phonak Lumity L90: fixed ৳5,000; Oticon Real 2: 3%).</div>
        </div>
    </div>
    <div class="crm-form-section-body">
        <div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
            <button type="button" class="btn btn-outline-primary btn-xs" id="btn-add-product-comm"><i class="fas fa-plus mr-1"></i>Add Model Rule</button>
        </div>
        <table class="rule-table" id="product-comm-table">
            <thead>
                <tr>
                    <th style="width: 40%;">Product / Model</th>
                    <th style="width: 25%;">Commission Type</th>
                    <th style="width: 25%;">Commission Value</th>
                    <th style="width: 10%; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody id="product-comm-body"></tbody>
        </table>
        <div id="no-product-comm-msg" style="text-align: center; padding: 16px; color: var(--page-text-muted); font-size: 13px; background: #fff; border: 1px dashed #e2e8f0; border-radius: 8px;">
            No model rules yet. Products use the category rate, or the default product rate.
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var products = @json(($products ?? collect())->map(fn ($p) => ['id' => $p->id, 'model' => $p->model])->values());
    var existing = @json($productRows);
    var idx = 0;

    function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
    function check() { $('#no-product-comm-msg').toggle($('#product-comm-body tr').length === 0); }

    function addRow(row) {
        row = row || {};
        var i = idx++;
        var opts = products.map(function (p) {
            return '<option value="' + p.id + '"' + (String(p.id) === String(row.product_id) ? ' selected' : '') + '>' + esc(p.model) + '</option>';
        }).join('');
        var type = row.commission_type || 'percent';
        $('#product-comm-body').append(
            '<tr>' +
            '<td><select name="product_commissions[' + i + '][product_id]" class="form-control" required>' + opts + '</select></td>' +
            '<td><select name="product_commissions[' + i + '][commission_type]" class="form-control" required>' +
                '<option value="percent"' + (type === 'percent' ? ' selected' : '') + '>Percentage (%)</option>' +
                '<option value="fixed"' + (type === 'fixed' ? ' selected' : '') + '>Fixed Amount (৳ per unit)</option></select></td>' +
            '<td><input type="number" step="0.01" min="0" name="product_commissions[' + i + '][commission_value]" class="form-control" placeholder="Value" value="' + (row.commission_value !== undefined ? esc(row.commission_value) : '') + '" required></td>' +
            '<td style="text-align:center;"><button type="button" class="btn-del-rule" title="Remove"><i class="fas fa-trash-alt"></i></button></td>' +
            '</tr>');
        check();
    }

    $(document).on('click', '#btn-add-product-comm', function () { addRow(); });
    $(document).on('click', '#product-comm-body .btn-del-rule', function () { $(this).closest('tr').remove(); check(); });
    existing.forEach(addRow);
    check();
})();
</script>
@endpush
