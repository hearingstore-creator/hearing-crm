{{--
    Tinted KPI card.
    Params: label, value, icon (fa classes), color (indigo|green|blue|amber|rose|violet|teal|sky|orange|slate),
            optional: href, sub (HTML allowed – escape user data before passing), active (bool), small (bool), id
--}}
@php
    $tag = !empty($href) ? 'a' : 'div';
    $classes = 'kpi k-' . ($color ?? 'indigo') . (!empty($active) ? ' active' : '') . (!empty($small) ? ' kpi-sm' : '');
@endphp
<{{ $tag }} @if(!empty($href)) href="{{ $href }}" @endif class="{{ $classes }}" @if(!empty($id)) id="{{ $id }}" @endif>
    <div class="k-ic"><i class="{{ $icon ?? 'fas fa-chart-bar' }}"></i></div>
    <div class="k-body">
        <div class="k-lbl" title="{{ $label }}">{{ $label }}</div>
        <div class="k-val">{{ $value }}</div>
        @if(!empty($sub))<div class="k-sub">{!! $sub !!}</div>@endif
    </div>
    @if(!empty($href))<span class="k-go"><i class="fas fa-arrow-right"></i></span>@endif
</{{ $tag }}>
