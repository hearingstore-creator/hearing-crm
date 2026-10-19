@extends('admin.layouts.master')
@section('page_title', 'New Stock Adjustment')
@section('breadcrumb_parent', 'Stock Adjustments')
@section('breadcrumb_parent_url', route('stock-adjustments.index'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .sa-line { border:1px solid var(--card-border); border-radius:12px; padding:14px; margin-bottom:12px; background:var(--card-bg); }
        .sa-line .sa-grid { display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:10px; }
        .sa-line .sa-fields { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:10px; }
        .sa-serials { max-height:150px; overflow:auto; border:1px solid var(--card-border); border-radius:8px; padding:8px; }
        .sa-serials label { display:flex; gap:6px; font-size:13px; margin:0 0 4px; }
        .sa-note { font-size:12px; color:var(--page-text-muted); }
        @media (max-width:700px) { .sa-line .sa-grid { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-balance-scale-left" style="color: var(--accent-500); margin-right: 8px;"></i>New Stock Adjustment</h1>
            <p>Record damaged, lost or found stock. No money moves; only quantities change.</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('stock-adjustments.index') }}" class="btn btn-secondary btn-sm" id="btn-back-adjustments"><i class="fas fa-list mr-1"></i>All adjustments</a>
        </div>
    </div>

    @include('admin.alert_message')
    @if ($errors->any())
        <div class="alert alert-danger m-2">
            @foreach ($errors->get('stock') as $msg)<div>{{ $msg }}</div>@endforeach
            @foreach ($errors->except('stock')->all() as $msg)<div>{{ $msg }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('stock-adjustments.store') }}" id="adjustment-form"
          data-product-search="{{ url('admin/product-search') }}" data-stock-url="{{ route('stock-adjustments.product-stock') }}">
        @csrf
        <div class="crm-table-card" style="padding:16px; margin-bottom:16px;">
            <div class="row">
                <div class="col-md-3 form-group">
                    <label for="location">Location</label>
                    <select name="location" id="location" class="form-control">
                        @foreach ($locations as $k => $name)
                            <option value="{{ $k }}" @selected((string) old('location', 'hq') === (string) $k)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="date">Date</label>
                    <input type="date" name="date" id="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-3 form-group">
                    <label for="reason">Reason</label>
                    <select name="reason" id="reason" class="form-control" required>
                        @foreach ($reasons as $k => $name)
                            <option value="{{ $k }}" @selected(old('reason') === $k)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="note">Note <span class="sa-note" id="note-hint">(required for Other and Stock count correction)</span></label>
                    <input type="text" name="note" id="note" class="form-control" maxlength="1000" value="{{ old('note') }}">
                </div>
            </div>
        </div>

        <div id="lines"></div>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-add-line"><i class="fas fa-plus mr-1"></i>Add product line</button>
        <button type="submit" class="btn btn-primary btn-sm" id="btn-save-adjustment"><i class="fas fa-check mr-1"></i>Save adjustment</button>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(function () {
    var $form = $('#adjustment-form'), $lines = $('#lines'), n = 0;
    var searchUrl = $form.data('product-search'), stockUrl = $form.data('stock-url');

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }

    function addLine() {
        var i = n++, p = 'lines[' + i + ']';
        var $l = $(
            '<div class="sa-line" data-i="' + i + '">' +
            '<div class="sa-grid">' +
              '<div><label>Product</label><select class="form-control sa-product" name="' + p + '[product_id]" required></select></div>' +
              '<div><label>Change</label><select class="form-control sa-dir" name="' + p + '[direction]">' +
                '<option value="out">Remove stock (damaged, lost)</option><option value="in">Add stock (found, opening)</option></select></div>' +
            '</div>' +
            '<div class="sa-body"><div class="sa-note">Pick a product first.</div></div>' +
            '<div style="text-align:right;margin-top:6px;"><button type="button" class="btn btn-link btn-sm text-danger sa-remove">Remove line</button></div>' +
            '</div>');
        $lines.append($l);
        $l.find('.sa-product').select2({
            width: '100%', placeholder: 'Search product',
            ajax: {url: searchUrl, dataType: 'json', delay: 400, data: function (q) { return {search: q.term, type: 'purchase'}; }, processResults: function (r) { return {results: r}; }}
        }).on('select2:select', function () { renderBody($l); });
        $l.find('.sa-dir').on('change', function () { renderBody($l); });
        $l.find('.sa-remove').on('click', function () { $l.remove(); });
    }

    function renderBody($l) {
        var i = $l.data('i'), p = 'lines[' + i + ']', prod = $l.find('.sa-product').val(), dir = $l.find('.sa-dir').val(), $b = $l.find('.sa-body');
        var token = ($l.data('render') || 0) + 1; $l.data('render', token); // a slower earlier answer must not overwrite a newer choice
        if (!prod) { $b.html('<div class="sa-note">Pick a product first.</div>'); return; }

        if (dir === 'in') {
            $b.html(
              '<div class="sa-fields">' +
                '<div><label>Serial numbers <span class="sa-note">(one per line or comma separated; leave empty for a batch)</span></label><textarea class="form-control sa-serials-in" name="' + p + '[serials]" rows="3"></textarea></div>' +
                '<div><label>Batch no. <span class="sa-note">(batch items)</span></label><input class="form-control" name="' + p + '[batch_no]" maxlength="100"><label class="mt-2">Quantity <span class="sa-note">(batch items)</span></label><input type="number" min="0" step="0.01" class="form-control" name="' + p + '[qty]"></div>' +
                '<div><label>Unit cost (৳) *</label><input type="number" min="0" step="0.01" class="form-control" name="' + p + '[unit_cost]" required>' +
                    '<label class="mt-2">Selling price (৳)</label><input type="number" min="0" step="0.01" class="form-control" name="' + p + '[mrp_price]"></div>' +
                '<div><label>Supplier warranty starts <span class="sa-note">(optional)</span></label><input type="date" class="form-control" name="' + p + '[warranty_start_date]"><div class="sa-note mt-1">Without a date this stock is left out of supplier-warranty alerts.</div></div>' +
              '</div>');
            return;
        }

        $b.html('<div class="sa-note">Loading stock…</div>');
        $.getJSON(stockUrl, {product_id: prod, location: $('#location').val()}).done(function (d) {
            if ($l.data('render') !== token) { return; }
            var html = '';
            if (d.serials.length) {
                html += '<div><label>Serial numbers in stock (tick the ones to remove)</label><div class="sa-serials">' +
                    d.serials.map(function (s) { return '<label><input type="checkbox" class="sa-serial" value="' + esc(s) + '"> ' + esc(s) + '</label>'; }).join('') +
                    '</div><input type="hidden" name="' + p + '[serials]" class="sa-serials-out"></div>';
            }
            if (d.batches.length) {
                html += '<div><label>Batch</label><select class="form-control" name="' + p + '[batch_id]"><option value="">None</option>' +
                    d.batches.map(function (b) { return '<option value="' + b.id + '">' + esc(b.batch_no) + ' (' + b.available + ' in stock)</option>'; }).join('') +
                    '</select><label class="mt-2">Quantity to remove</label><input type="number" min="0" step="0.01" class="form-control" name="' + p + '[qty]"></div>';
            }
            if (!html) { html = '<div class="sa-note">Nothing in stock for this product at this location.</div>'; }
            $b.html('<div class="sa-fields">' + html + '</div>');
            $b.find('.sa-serial').on('change', function () {
                $b.find('.sa-serials-out').val($b.find('.sa-serial:checked').map(function () { return this.value; }).get().join(','));
            });
        }).fail(function () { if ($l.data('render') !== token) { return; } $b.html('<div class="sa-note text-danger">Could not load stock for this product.</div>'); });
    }

    // Changing the location changes what can be removed
    $('#location').on('change', function () { $('.sa-line').each(function () { renderBody($(this)); }); });
    $('#btn-add-line').on('click', addLine);
    addLine();
});
</script>
@endpush
