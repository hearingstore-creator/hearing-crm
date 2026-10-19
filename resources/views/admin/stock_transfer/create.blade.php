@extends('admin.layouts.master')
@section('page_title', 'New Stock Transfer')
@section('breadcrumb_parent', 'Stock Transfers')
@section('breadcrumb_parent_url', route('stock-transfers.index'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .st-line { border:1px solid var(--card-border); border-radius:12px; padding:14px; margin-bottom:12px; background:var(--card-bg); }
        .st-fields { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-top:10px; }
        .st-serials { max-height:150px; overflow:auto; border:1px solid var(--card-border); border-radius:8px; padding:8px; }
        .st-serials label { display:flex; gap:6px; font-size:13px; margin:0 0 4px; }
        .st-note { font-size:12px; color:var(--page-text-muted); }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-exchange-alt" style="color: var(--accent-500); margin-right: 8px;"></i>New Stock Transfer</h1>
            <p>Move stock between head office and branches. No money moves; warranty dates stay with the original purchase and sale.</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('stock-transfers.index') }}" class="btn btn-secondary btn-sm" id="btn-back-transfers"><i class="fas fa-list mr-1"></i>All transfers</a>
        </div>
    </div>

    @include('admin.alert_message')
    @if ($errors->any())
        <div class="alert alert-danger m-2">
            @foreach ($errors->get('stock') as $msg)<div>{{ $msg }}</div>@endforeach
            @foreach ($errors->except('stock')->all() as $msg)<div>{{ $msg }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('stock-transfers.store') }}" id="transfer-form"
          data-product-search="{{ url('admin/product-search') }}" data-stock-url="{{ route('stock-transfers.product-stock') }}">
        @csrf
        <div class="crm-table-card" style="padding:16px; margin-bottom:16px;">
            <div class="row">
                <div class="col-md-3 form-group">
                    <label for="from">From</label>
                    <select name="from" id="from" class="form-control">
                        @foreach ($locations as $k => $name)<option value="{{ $k }}" @selected((string) old('from', 'hq') === (string) $k)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="to">To</label>
                    <select name="to" id="to" class="form-control">
                        @foreach ($locations as $k => $name)<option value="{{ $k }}" @selected((string) old('to') === (string) $k)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="date">Date</label>
                    <input type="date" name="date" id="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-3 form-group">
                    <label for="note">Note</label>
                    <input type="text" name="note" id="note" class="form-control" maxlength="1000" value="{{ old('note') }}">
                </div>
            </div>
        </div>

        <div id="lines"></div>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-add-line"><i class="fas fa-plus mr-1"></i>Add product line</button>
        <button type="submit" class="btn btn-primary btn-sm" id="btn-save-transfer"><i class="fas fa-check mr-1"></i>Save transfer</button>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(function () {
    var $form = $('#transfer-form'), $lines = $('#lines'), n = 0;
    var searchUrl = $form.data('product-search'), stockUrl = $form.data('stock-url');

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }

    function addLine() {
        var i = n++;
        var $l = $('<div class="st-line" data-i="' + i + '"><label>Product</label><select class="form-control st-product" name="lines[' + i + '][product_id]" required></select>' +
            '<div class="st-body"><div class="st-note mt-2">Pick a product first.</div></div>' +
            '<div style="text-align:right;margin-top:6px;"><button type="button" class="btn btn-link btn-sm text-danger st-remove">Remove line</button></div></div>');
        $lines.append($l);
        $l.find('.st-product').select2({
            width: '100%', placeholder: 'Search product',
            ajax: {url: searchUrl, dataType: 'json', delay: 400, data: function (q) { return {search: q.term, type: 'purchase'}; }, processResults: function (r) { return {results: r}; }}
        }).on('select2:select', function () { renderBody($l); });
        $l.find('.st-remove').on('click', function () { $l.remove(); });
    }

    function renderBody($l) {
        var i = $l.data('i'), p = 'lines[' + i + ']', prod = $l.find('.st-product').val(), $b = $l.find('.st-body');
        var token = ($l.data('render') || 0) + 1; $l.data('render', token); // a slower earlier answer must not overwrite a newer choice
        if (!prod) { $b.html('<div class="st-note mt-2">Pick a product first.</div>'); return; }
        $b.html('<div class="st-note mt-2">Loading stock…</div>');
        $.getJSON(stockUrl, {product_id: prod, location: $('#from').val()}).done(function (d) {
            if ($l.data('render') !== token) { return; }
            var html = '';
            if (d.serials.length) {
                html += '<div><label>Serial numbers in stock (tick the ones to move)</label><div class="st-serials">' +
                    d.serials.map(function (s) { return '<label><input type="checkbox" class="st-serial" value="' + esc(s) + '"> ' + esc(s) + '</label>'; }).join('') +
                    '</div><input type="hidden" name="' + p + '[serials]" class="st-serials-out"></div>';
            }
            if (d.batches.length) {
                html += '<div><label>Batch</label><select class="form-control" name="' + p + '[batch_id]"><option value="">None</option>' +
                    d.batches.map(function (b) { return '<option value="' + b.id + '">' + esc(b.batch_no) + ' (' + b.available + ' in stock)</option>'; }).join('') +
                    '</select><label class="mt-2">Quantity to move</label><input type="number" min="0" step="0.01" class="form-control" name="' + p + '[qty]"></div>';
            }
            if (!html) { html = '<div class="st-note">Nothing in stock for this product at the source location.</div>'; }
            $b.html('<div class="st-fields">' + html + '</div>');
            $b.find('.st-serial').on('change', function () {
                $b.find('.st-serials-out').val($b.find('.st-serial:checked').map(function () { return this.value; }).get().join(','));
            });
        }).fail(function () { if ($l.data('render') !== token) { return; } $b.html('<div class="st-note text-danger mt-2">Could not load stock for this product.</div>'); });
    }

    // Changing the source changes what can be moved
    $('#from').on('change', function () { $('.st-line').each(function () { renderBody($(this)); }); });
    $('#btn-add-line').on('click', addLine);
    addLine();
});
</script>
@endpush
