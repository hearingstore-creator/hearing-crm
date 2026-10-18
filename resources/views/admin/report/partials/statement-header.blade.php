{{-- Statement document header. Expects: $company, $title, and optional $subtitle (HTML). --}}
<div class="stmt-head">
    <div class="stmt-brand">
        @if (!empty($company['logo']))
            <img src="{{ $company['logo'] }}" alt="{{ $company['name'] }}">
        @endif
        <div>
            <div class="c-name">{{ $company['name'] }}</div>
            <div class="c-meta">
                @if ($company['address']){{ $company['address'] }}<br>@endif
                @if ($company['phone'])<i class="fas fa-phone-alt" style="font-size:9px;"></i> {{ $company['phone'] }}@endif
                @if ($company['email']) &nbsp;·&nbsp; {{ $company['email'] }}@endif
            </div>
        </div>
    </div>
    <div class="stmt-title">
        <h2>{{ $title }}</h2>
        @isset($subtitle)<div class="s-period">{!! $subtitle !!}</div>@endisset
    </div>
</div>
