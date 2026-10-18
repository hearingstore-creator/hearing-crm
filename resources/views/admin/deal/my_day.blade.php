@extends('admin.layouts.master')
@section('page_title', 'My Day')
@section('breadcrumb_parent', 'Deals Board')
@section('breadcrumb_parent_url', url('admin/deals'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/deal-board.css') }}?v={{ filemtime(public_path('assets/css/deal-board.css')) }}">
@endpush

@php
    $titles = [
        'overdue'  => ['Overdue', 'fas fa-exclamation-circle', 'Do these first'],
        'today'    => ['Today', 'fas fa-sun', 'Later today'],
        'upcoming' => ['Next 2 days', 'far fa-calendar', 'Plan ahead'],
    ];
    $phoneDigits = fn ($p) => preg_replace('/\D+/', '', (string) $p);
    $waNumber = function ($p) use ($phoneDigits) {
        $d = $phoneDigits($p);
        return str_starts_with($d, '01') ? '88' . $d : $d;
    };
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-sun" style="color: #f59e0b; margin-right: 8px;"></i>My Day</h1>
            <p>{{ now()->format('l, d F') }} · tap Call, then one result. The next call is planned for you.</p>
        </div>
        <div class="header-actions">
            @if ($canAll)
                <div class="deal-views" role="tablist" aria-label="Whose activities">
                    <a href="{{ route('deals.my-day') }}" id="md-mine" class="{{ $everyone ? '' : 'active' }}"><i class="fas fa-user"></i> Mine</a>
                    <a href="{{ route('deals.my-day', ['who' => 'all']) }}" id="md-all" class="{{ $everyone ? 'active' : '' }}"><i class="fas fa-users"></i> Everyone</a>
                </div>
            @endif
            <a href="{{ url('admin/deals') }}" class="btn btn-secondary btn-sm" id="md-board"><i class="fas fa-columns mr-1"></i>Deals Board</a>
        </div>
    </div>

    <div class="md-stats crm-animate-in delay-1">
        <div class="md-stat is-overdue" id="md-stat-overdue"><i class="fas fa-exclamation-circle"></i><b data-count="overdue">{{ $groups['overdue']->count() }}</b><span>Overdue</span></div>
        <div class="md-stat is-today" id="md-stat-today"><i class="fas fa-sun"></i><b data-count="today">{{ $groups['today']->count() }}</b><span>Due today</span></div>
        <div class="md-stat is-done" id="md-stat-done"><i class="fas fa-check-circle"></i><b data-count="done">{{ $doneToday }}</b><span>Done today</span></div>
    </div>

    @foreach ($titles as $key => [$title, $icon, $hint])
        @continue($key === 'upcoming' && $groups['upcoming']->isEmpty())
        <section class="md-group md-{{ $key }} crm-animate-in delay-2" id="md-group-{{ $key }}" data-group="{{ $key }}">
            <h2><i class="{{ $icon }}"></i>{{ $title }} <small>{{ $hint }}</small></h2>

            @forelse ($groups[$key] as $a)
                @php
                    $deal = $a->deal;
                    $lead = $a->lead;
                    $at = \Illuminate\Support\Carbon::parse($a->follow_up_date);
                    $isContact = in_array($a->activity_type, ['call', 'whatsapp'], true);
                @endphp
                <article class="md-item" id="md-item-{{ $a->id }}" data-id="{{ $a->id }}" data-deal="{{ $deal->id }}" data-group="{{ $key }}">
                    <div class="md-when {{ $key === 'overdue' ? 'is-late' : '' }}">
                        <b>{{ $at->format('h:i') }}</b><span>{{ $at->isToday() ? $at->format('A') : $at->format('d M') }}</span>
                    </div>
                    <span class="md-type" title="{{ $a->type->label() }}"><i class="{{ $a->type->icon() }}"></i></span>
                    <div class="md-main">
                        <a href="{{ url('admin/deals?deal=' . $deal->id) }}" class="md-name" id="md-open-{{ $a->id }}">{{ $lead->full_name ?? 'Unknown' }}</a>
                        <span class="md-stage" style="--stage: {{ $deal->stage->color ?? '#94a3b8' }};">{{ $deal->stage->name ?? '—' }}</span>
                        <div class="md-sub">
                            <span><i class="fas fa-phone-alt"></i> {{ $lead->phone_number ?? '—' }}</span>
                            @if ($everyone)<span><i class="fas fa-user"></i> {{ $a->assignedUser->name ?? 'Unassigned' }}</span>@endif
                        </div>
                        @if ($a->follow_up_notes)<div class="md-note">{{ $a->follow_up_notes }}</div>@endif
                    </div>
                    <div class="md-actions">
                        @if ($lead && $lead->phone_number)
                            <a href="tel:{{ $lead->phone_number }}" class="md-btn md-call js-md-contact" data-channel="call" id="md-call-{{ $a->id }}" title="Call"><i class="fas fa-phone"></i></a>
                            <a href="https://wa.me/{{ $waNumber($lead->phone_number) }}" target="_blank" rel="noopener" class="md-btn md-wa js-md-contact" data-channel="whatsapp" id="md-wa-{{ $a->id }}" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        @endif
                        @if ($can['move'])
                            @if ($isContact)
                                <button type="button" class="md-btn md-result-toggle" id="md-result-{{ $a->id }}" aria-expanded="false"><i class="fas fa-flag-checkered mr-1"></i>Result</button>
                            @else
                                <button type="button" class="md-btn md-done js-md-done" id="md-done-{{ $a->id }}"><i class="fas fa-check mr-1"></i>Done</button>
                            @endif
                        @endif
                    </div>
                    @if ($can['move'] && $isContact)
                        <div class="md-results" id="md-results-{{ $a->id }}" hidden>
                            @foreach ($outcomes as $o)
                                <button type="button" class="md-chip md-chip-{{ $o->value }} js-md-result" data-result="{{ $o->value }}" id="md-r-{{ $a->id }}-{{ $o->value }}">
                                    <i class="{{ $o->icon() }}"></i>{{ $o->label() }}
                                </button>
                            @endforeach
                            <div class="md-callback" hidden>
                                <input type="datetime-local" class="form-control form-control-sm" id="md-cb-{{ $a->id }}" aria-label="Call back at"
                                       min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->addDay()->setTime(11, 0)->format('Y-m-d\TH:i') }}">
                                <button type="button" class="btn btn-primary btn-sm js-md-callback" id="md-cb-save-{{ $a->id }}">Save</button>
                            </div>
                            <input type="text" class="form-control form-control-sm md-note-input" id="md-note-{{ $a->id }}" maxlength="500" placeholder="Note (optional)">
                        </div>
                    @endif
                </article>
            @empty
                <div class="md-empty" id="md-empty-{{ $key }}">
                    <i class="fas {{ $key === 'overdue' ? 'fa-check-double' : 'fa-mug-hot' }}"></i>
                    {{ $key === 'overdue' ? 'Nothing overdue. Great work!' : 'Nothing else due today.' }}
                </div>
            @endforelse
        </section>
    @endforeach
</div>

<div class="deal-toast-wrap" id="deal-toasts" aria-live="polite"></div>
@endsection

@push('scripts')
<script>
(function ($) {
    'use strict';
    var base = @json(url('admin/deals'));
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' } });

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function toast(msg, type, html) {
        var $t = $('<div class="deal-toast ' + (type || 'ok') + '"><i class="fas ' + (type === 'err' ? 'fa-exclamation-circle' : 'fa-check-circle') + '" style="margin-top:2px"></i><span>' + (html || esc(msg)) + '</span></div>');
        $('#deal-toasts').append($t);
        setTimeout(function () { $t.fadeOut(250, function () { $t.remove(); }); }, html ? 9000 : 3600);
    }
    function errorOf(xhr) {
        var r = xhr.responseJSON || {};
        if (r.errors) { for (var k in r.errors) { return r.errors[k][0]; } }
        return r.message || 'Something went wrong. Please try again.';
    }
    function bump(key, d) { var $b = $('[data-count="' + key + '"]'); $b.text(Math.max(0, (+$b.text() || 0) + d)); }

    function finish($item, res) {
        bump($item.data('group'), -1);
        bump('done', 1);
        $item.addClass('is-leaving');
        setTimeout(function () { $item.remove(); }, 320);
        if (res.suggest_lost) {
            toast('', 'info', esc(res.message) + ' <a href="' + base + '?deal=' + res.deal_id + '" class="md-toast-link">Mark as Lost?</a>');
        } else {
            toast(res.message);
        }
    }

    function complete($item, data) {
        $item.addClass('is-busy');
        data.outcome = $item.find('.md-note-input').val() || '';
        $.post(base + '/activities/' + $item.data('id') + '/complete', data)
            .done(function (res) { finish($item, res); })
            .fail(function (xhr) { $item.removeClass('is-busy'); toast(errorOf(xhr), 'err'); });
    }

    // Tap Call / WhatsApp: the phone app opens; the CRM logs the contact
    $(document).on('click', '.js-md-contact', function () {
        var $item = $(this).closest('.md-item');
        $.post(base + '/' + $item.data('deal') + '/contact', { channel: this.getAttribute('data-channel') });
        var $toggle = $item.find('.md-result-toggle');
        if ($toggle.length && $toggle.attr('aria-expanded') !== 'true') { $toggle.trigger('click'); }
    });

    $(document).on('click', '.md-result-toggle', function () {
        var $panel = $(this).closest('.md-item').find('.md-results');
        var open = $panel.prop('hidden');
        $panel.prop('hidden', !open);
        this.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    $(document).on('click', '.js-md-result', function () {
        var $item = $(this).closest('.md-item');
        var result = this.getAttribute('data-result');
        if (result === 'call_back') { $item.find('.md-callback').prop('hidden', false).find('input').trigger('focus'); return; }
        complete($item, { result: result });
    });

    $(document).on('click', '.js-md-callback', function () {
        var $item = $(this).closest('.md-item');
        complete($item, { result: 'call_back', callback_at: $item.find('.md-callback input').val() });
    });

    $(document).on('click', '.js-md-done', function () { complete($(this).closest('.md-item'), {}); });
})(jQuery);
</script>
@endpush
