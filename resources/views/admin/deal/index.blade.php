@extends('admin.layouts.master')
@section('page_title', 'Deals Board')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/deals'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/deal-board.css') }}?v={{ filemtime(public_path('assets/css/deal-board.css')) }}">
@endpush

@php
    $viewUrl = fn ($s) => url('admin/deals') . '?' . http_build_query(array_filter(array_merge($filters, ['status' => $s === 'open' ? null : $s])));
    $isList = $status === 'list';
    $money = fn ($v) => '৳' . number_format((float) $v);
    $stagesForJs = $stages->map(fn ($s) => [
        'id' => $s->id, 'name' => $s->name, 'color' => $s->color, 'order' => $s->sort_order, 'required' => $s->required_fields ?? [],
    ])->values();
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-columns" style="color: var(--accent-500); margin-right: 8px;"></i>Deals Board</h1>
            <p>{{ $pipeline->name }} · drag a deal to move it, or drop it on Won / Lost</p>
        </div>
        <div class="header-actions">
            @if ($pipelines->count() > 1)
                <form method="GET" action="{{ url('admin/deals') }}" id="pipeline-switch-form">
                    @if ($status !== 'open')<input type="hidden" name="status" value="{{ $status }}">@endif
                    <label for="pipeline-switch" class="sr-only">Pipeline</label>
                    <select name="pipeline" id="pipeline-switch" class="form-control deal-pipeline-switch" onchange="this.form.submit()">
                        @foreach ($pipelines as $pl)
                            <option value="{{ $pl->id }}" @selected($pl->id === $pipeline->id)>{{ $pl->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <div class="deal-views" role="tablist" aria-label="Deal views">
                <a href="{{ $viewUrl('open') }}" id="view-open" class="{{ $status === 'open' ? 'active' : '' }}"><i class="fas fa-columns"></i> Board</a>
                <a href="{{ $viewUrl('list') }}" id="view-list" class="{{ $status === 'list' ? 'active' : '' }}"><i class="fas fa-list"></i> List</a>
                <a href="{{ $viewUrl('won') }}" id="view-won" class="{{ $status === 'won' ? 'active' : '' }}"><i class="fas fa-trophy"></i> Won</a>
                <a href="{{ $viewUrl('lost') }}" id="view-lost" class="{{ $status === 'lost' ? 'active' : '' }}"><i class="fas fa-times-circle"></i> Lost</a>
            </div>
            @php $myDue = \App\Models\LeadFollowUp::dueCountFor(auth()->id()); @endphp
            <a href="{{ route('deals.my-day') }}" class="btn btn-sm my-day-btn {{ $myDue ? 'has-due' : '' }}" id="btn-my-day" title="Your overdue and today's calls">
                <i class="fas fa-sun mr-1"></i>My Day @if ($myDue)<span class="my-day-badge">{{ $myDue }}</span>@endif
            </a>
            @if ($can['settings'])
                <a href="{{ url('admin/pipeline-settings?pipeline=' . $pipeline->id) }}" class="btn btn-secondary btn-sm" id="btn-pipeline-settings"><i class="fas fa-sliders-h mr-1"></i>Pipeline Settings</a>
            @endif
            @if ($can['create'])
                <a href="{{ route('leads.create') }}" class="btn btn-secondary btn-sm" id="btn-add-lead" title="Full lead form"><i class="fas fa-file-alt mr-1"></i>Full form</a>
                <button type="button" class="btn btn-primary btn-sm" id="btn-quick-add"><i class="fas fa-bolt mr-1"></i>Quick Add</button>
            @endif
        </div>
    </div>

    @if ($status === 'open')
        <div class="kpi-row crm-animate-in delay-1">
            @include('admin.partials.kpi-card', ['id' => 'kpi-open', 'color' => 'indigo', 'icon' => 'fas fa-layer-group',
                'label' => 'Open Deals', 'value' => number_format($summary['count']), 'sub' => 'across ' . $stages->count() . ' stages'])
            @include('admin.partials.kpi-card', ['id' => 'kpi-value', 'color' => 'green', 'icon' => 'fas fa-coins',
                'label' => 'Pipeline Value', 'value' => $money($summary['value']), 'sub' => 'quoted value of open deals'])
            @include('admin.partials.kpi-card', ['id' => 'kpi-weighted', 'color' => 'violet', 'icon' => 'fas fa-balance-scale',
                'label' => 'Weighted Forecast', 'value' => $money($summary['weighted']), 'sub' => 'value × stage probability'])
            @include('admin.partials.kpi-card', ['id' => 'kpi-rotting', 'color' => $summary['rotting'] ? 'rose' : 'slate', 'icon' => 'fas fa-hourglass-half',
                'label' => 'Stuck Deals', 'value' => number_format($summary['rotting']), 'sub' => 'no progress past the stage limit'])
        </div>
    @endif

    <form method="GET" action="{{ url('admin/deals') }}" class="deal-toolbar crm-animate-in delay-2" id="deal-filters">
        @if ($status !== 'open')<input type="hidden" name="status" value="{{ $status }}">@endif
        @if ($isList)<input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">@endif
        <div class="deal-search">
            <i class="fas fa-search"></i>
            <input type="text" name="q" id="deal-q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Name, phone, lead ID or card no.">
        </div>
        @if ($isHeadOffice)
            <select name="branch" id="deal-branch" class="form-control" aria-label="Branch">
                <option value="">All branches</option>
                <option value="hq" @selected(($filters['branch'] ?? '') === 'hq')>Head Office</option>
                @foreach ($branches as $bid => $bname)
                    <option value="{{ $bid }}" @selected(($filters['branch'] ?? '') == $bid)>{{ $bname }}</option>
                @endforeach
            </select>
        @endif
        <select name="owner" id="deal-owner" class="form-control" aria-label="Owner">
            <option value="">All owners</option>
            <option value="me" @selected(($filters['owner'] ?? '') === 'me')>My deals</option>
            @foreach (collect($owners)->flatten(1)->sortBy('name') as $o)
                <option value="{{ $o['id'] }}" @selected(($filters['owner'] ?? '') == $o['id'])>{{ $o['name'] }}</option>
            @endforeach
        </select>
        <select name="temperature" id="deal-temperature" class="form-control" aria-label="Temperature">
            <option value="">Any temperature</option>
            @foreach ($temperatures as $t)
                <option value="{{ $t->value }}" @selected(($filters['temperature'] ?? '') === $t->value)>{{ $t->label() }}</option>
            @endforeach
        </select>
        <select name="source" id="deal-source" class="form-control" aria-label="Source">
            <option value="">Any source</option>
            @foreach ($sources as $s)
                <option value="{{ $s->value }}" @selected(($filters['source'] ?? '') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary" id="deal-filter-apply"><i class="fas fa-filter mr-1"></i>Apply</button>
        @if (array_filter($filters))
            <a href="{{ url('admin/deals' . ($status !== 'open' ? '?status=' . $status : '')) }}" class="btn btn-secondary" id="deal-filter-reset">Reset</a>
        @endif
    </form>

    @if ($status === 'open')
        <div class="deal-board-wrap crm-animate-in delay-3">
            <div class="deal-board" id="deal-board">
                @foreach ($columns as $col)
                    @php $stage = $col['stage']; @endphp
                    <section class="deal-col" id="stage-col-{{ $stage->id }}" data-stage="{{ $stage->id }}" style="--stage-color: {{ $stage->color }};">
                        <header class="deal-col-head">
                            <div class="deal-col-title">
                                <span class="dot"></span>{{ $stage->name }}
                                <span class="deal-col-count" data-count>{{ $col['count'] }}</span>
                            </div>
                            <div class="deal-col-sub">
                                <span><b data-value>{{ $money($col['value']) }}</b></span>
                                <span title="Win probability · red after">{{ $stage->probability }}%{{ $stage->rotting_days ? ' · ' . $stage->rotting_days . 'd' : '' }}</span>
                            </div>
                        </header>
                        <div class="deal-col-body" data-stage="{{ $stage->id }}" data-total="{{ $col['value'] }}">@foreach ($col['deals'] as $deal)@include('admin.deal._card', ['deal' => $deal])@endforeach</div>
                        @if ($col['count'] > $col['deals']->count())
                            <button type="button" class="deal-col-more" id="more-{{ $stage->id }}" data-stage="{{ $stage->id }}">
                                Show more ({{ $col['count'] - $col['deals']->count() }})
                            </button>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>

        {{-- Drop zones, shown while dragging --}}
        <div class="deal-dock" id="deal-dock" aria-hidden="true">
            @if ($can['won'])
                <div class="deal-drop drop-won" id="drop-won"><i class="fas fa-trophy"></i> WON<div class="deal-drop-list" data-drop="won"></div></div>
            @endif
            @if ($can['lost'])
                <div class="deal-drop drop-lost" id="drop-lost"><i class="fas fa-times-circle"></i> LOST<div class="deal-drop-list" data-drop="lost"></div></div>
            @endif
            @if ($can['transfer'])
                <div class="deal-drop drop-transfer" id="drop-transfer"><i class="fas fa-share-square"></i> TRANSFER<div class="deal-drop-list" data-drop="transfer"></div></div>
            @endif
        </div>
        <div class="deal-menu" id="deal-menu" role="menu"></div>
    @elseif ($status === 'list')
        @php
            $sortUrl = fn ($key) => url('admin/deals') . '?' . http_build_query(array_filter(array_merge($filters, [
                'status' => 'list', 'sort' => $key, 'dir' => ($sort === $key && $dir === 'desc') ? 'asc' : 'desc',
            ])));
            $sortIcon = fn ($key) => $sort === $key ? ($dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
            $bulkAny = $can['transfer'] || $can['move'] || $can['sms'] || $can['lost'];
        @endphp
        <div class="crm-table-card crm-animate-in delay-3 deal-list-card">
            <div class="crm-table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color: var(--accent-500);"></i>
                    Open deals <span class="ps-badge">{{ number_format($listDeals->total()) }}</span>
                </div>
                @if ($bulkAny)<div class="dl-hint"><i class="far fa-check-square"></i> Tick deals to transfer, reassign, SMS or close several at once</div>@endif
            </div>
            <div class="dl-scroll">
                <table class="crm-table deal-list" id="deal-list">
                    <thead>
                        <tr>
                            @if ($bulkAny)
                                <th class="dl-check"><input type="checkbox" id="dl-check-all" aria-label="Select all on this page"></th>
                            @endif
                            <th><a href="{{ $sortUrl('name') }}" id="sort-name">Person <i class="fas {{ $sortIcon('name') }}"></i></a> · <a href="{{ $sortUrl('score') }}" id="sort-score" title="Lead score">Score <i class="fas {{ $sortIcon('score') }}"></i></a></th>
                            <th><a href="{{ $sortUrl('stage') }}" id="sort-stage">Stage <i class="fas {{ $sortIcon('stage') }}"></i></a></th>
                            <th>Interest</th>
                            <th class="text-right"><a href="{{ $sortUrl('value') }}" id="sort-value">Value <i class="fas {{ $sortIcon('value') }}"></i></a></th>
                            <th>Branch · Owner</th>
                            <th><a href="{{ $sortUrl('next') }}" id="sort-next">Next activity <i class="fas {{ $sortIcon('next') }}"></i></a></th>
                            <th><a href="{{ $sortUrl('age') }}" id="sort-age">In stage <i class="fas {{ $sortIcon('age') }}"></i></a></th>
                            <th><a href="{{ $sortUrl('created') }}" id="sort-created">Created <i class="fas {{ $sortIcon('created') }}"></i></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($listDeals as $deal)
                            @php
                                $lead = $deal->lead;
                                $temp = \App\Enums\LeadTemperature::tryFrom((string) $deal->temperature);
                                $next = $deal->nextActivity;
                                $nextAt = $next ? \Illuminate\Support\Carbon::parse($next->follow_up_date) : null;
                                $chip = !$nextAt ? 'none' : ($nextAt->isToday() ? 'today' : ($nextAt->isPast() ? 'overdue' : 'upcoming'));
                                $tier = $lead?->customer?->membershipTier;
                            @endphp
                            <tr class="deal-row{{ $deal->isRotting() ? ' is-rotting' : '' }}" id="deal-row-{{ $deal->id }}" tabindex="0"
                                data-id="{{ $deal->id }}" data-stage="{{ $deal->stage_id }}" data-updated="{{ $deal->updated_at?->toIso8601String() }}"
                                data-value="{{ (float) $deal->value }}" data-name="{{ $lead->full_name ?? 'Deal' }}"
                                data-inquiry='@json($deal->inquiry_for ?? [])' data-vendor="{{ $deal->vendor_id }}" data-owner="{{ $deal->owner_id }}">
                                @if ($bulkAny)
                                    <td class="dl-check"><input type="checkbox" class="dl-check-row" id="dl-check-{{ $deal->id }}" value="{{ $deal->id }}" aria-label="Select {{ $lead->full_name ?? 'deal' }}"></td>
                                @endif
                                <td>
                                    <div class="crm-name-cell">
                                        <div class="crm-avatar">{{ strtoupper(mb_substr($lead->full_name ?? 'D', 0, 1)) }}</div>
                                        <div class="crm-name-text">
                                            <span class="name">
                                                {{ $lead->full_name ?? '—' }}
                                                @if ($temp)<span class="dc-temp t-{{ $temp->value }}" title="{{ $temp->label() }}"><i class="{{ $temp->icon() }}"></i></span>@endif
                                                @if ($tier)<i class="fas fa-crown dl-crown" title="{{ $tier->name }} member"></i>@endif
                                                @include('admin.deal._score', ['deal' => $deal])
                                            </span>
                                            <span class="dl-sub">{{ $lead->phone_number ?? '' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="dl-stage" style="--stage-color: {{ $deal->stage->color ?? '#94a3b8' }};"><span class="dot"></span>{{ $deal->stage->name ?? '—' }}</span></td>
                                <td class="dl-interest">{{ $deal->display_title }}</td>
                                <td class="text-right"><b>{{ $deal->value ? $money($deal->value) : '—' }}</b></td>
                                <td>{{ $deal->vendor->name ?? 'Head Office' }}<span class="dl-sub d-block">{{ $deal->owner->name ?? 'Unassigned' }}</span></td>
                                <td>
                                    <span class="dc-chip chip-{{ $chip }}">
                                        <i class="{{ $next ? $next->type->icon() : 'fas fa-exclamation-triangle' }}"></i>
                                        {{ match ($chip) { 'none' => 'No activity', 'today' => 'Today ' . $nextAt->format('h:i A'), 'overdue' => 'Overdue · ' . $nextAt->format('d M'), default => $nextAt->format('d M') } }}
                                    </span>
                                </td>
                                <td><span class="{{ $deal->isRotting() ? 'dl-rot' : '' }}">{{ $deal->daysInStage() }}d</span></td>
                                <td class="dl-sub">{{ $deal->created_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="dl-empty"><i class="fas fa-inbox"></i><div>No open deals match these filters.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($listDeals->hasPages())
                <div class="crm-table-footer">{{ $listDeals->links() }}</div>
            @endif
        </div>

        @if ($bulkAny)
            <div class="dl-bulkbar" id="dl-bulkbar" role="toolbar" aria-label="Bulk actions">
                <span class="dl-count"><b id="dl-selected">0</b> selected</span>
                @if ($can['transfer'])<button type="button" class="dl-act" data-bulk="transfer" id="bulk-transfer"><i class="fas fa-share-square"></i> Transfer</button>@endif
                @if ($can['move'])<button type="button" class="dl-act" data-bulk="owner" id="bulk-owner"><i class="fas fa-user-tag"></i> Change owner</button>@endif
                @if ($can['sms'])<button type="button" class="dl-act" data-bulk="sms" id="bulk-sms"><i class="fas fa-comment-dots"></i> Send SMS</button>@endif
                @if ($can['lost'])<button type="button" class="dl-act dl-danger" data-bulk="lost" id="bulk-lost"><i class="fas fa-times-circle"></i> Mark lost</button>@endif
                <button type="button" class="dl-clear" id="dl-clear" title="Clear selection"><i class="fas fa-times"></i></button>
            </div>

            <div class="modal fade deal-modal" id="bulkModal" tabindex="-1" role="dialog" aria-labelledby="bulkModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <form class="modal-content" id="bulkForm" novalidate>
                        <div class="modal-header">
                            <h5 class="modal-title" id="bulkModalTitle"><span class="m-icon" id="bulkIcon"><i class="fas fa-layer-group"></i></span><span><span id="bulkTitle">Bulk action</span><span class="m-sub" id="bulkSub"></span></span></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <div class="modal-body">
                            @if ($can['transfer'])
                                <div data-for="transfer">
                                    <div class="form-group">
                                        <label for="bulk-branch">Branch <i class="req text-danger">*</i></label>
                                        <select name="vendor_id" id="bulk-branch" class="form-control">
                                            <option value="">Head Office</option>
                                            @foreach ($branches as $bid => $bname)<option value="{{ $bid }}">{{ $bname }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                            @endif
                            <div class="form-group" data-for="transfer owner">
                                <label for="bulk-owner-select">Handled by <i class="req text-danger">*</i></label>
                                <select name="owner_id" id="bulk-owner-select" class="form-control"></select>
                                <div class="deal-hint mt-1" data-for="owner">Deals in a branch this person doesn't work in are skipped.</div>
                            </div>
                            @if ($can['sms'])
                                <div data-for="sms">
                                    <div class="form-group">
                                        <label for="bulk-template">Template</label>
                                        <select name="sms_template_id" id="bulk-template" class="form-control">
                                            <option value="">— Write my own —</option>
                                            @foreach ($smsTemplates as $tpl)<option value="{{ $tpl->id }}" data-body="{{ $tpl->body }}">{{ $tpl->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="bulk-message">Message <i class="req text-danger">*</i></label>
                                        <textarea name="message" id="bulk-message" class="form-control" rows="4" maxlength="1000" placeholder="Dear @{{name}}, …"></textarea>
                                        <div class="deal-hint mt-1 d-flex justify-content-between"><span><code>@{{name}}</code> and <code>@{{company_phone}}</code> are filled in. Opted-out numbers are skipped.</span><span id="bulk-chars">0</span></div>
                                    </div>
                                </div>
                            @endif
                            @if ($can['lost'])
                                <div data-for="lost">
                                    <div class="form-group">
                                        <label for="bulk-reason">Reason <i class="req text-danger">*</i></label>
                                        <select name="lost_reason_id" id="bulk-reason" class="form-control">
                                            <option value="">Choose a reason…</option>
                                            @foreach ($lostReasons as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="bulk-lost-to">Lost to whom</label>
                                        <input type="text" name="lost_to" id="bulk-lost-to" class="form-control" maxlength="150" placeholder="Competitor / shop name, if known">
                                    </div>
                                </div>
                            @endif
                            <div class="form-group mb-0" data-for="transfer owner lost">
                                <label for="bulk-note"><span id="bulk-note-label">Note</span> <i class="req text-danger" id="bulk-note-req">*</i></label>
                                <textarea name="note" id="bulk-note" class="form-control" rows="2" maxlength="1000"></textarea>
                            </div>
                            <div class="deal-errors text-danger mt-2" style="font-size: 12.5px;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="bulkSubmit"><i class="fas fa-check mr-1"></i><span>Apply</span></button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @else
        <div class="crm-table-card crm-animate-in delay-3">
            <div class="crm-table-header">
                <div class="table-title">
                    <i class="fas {{ $status === 'won' ? 'fa-trophy' : 'fa-times-circle' }}" style="color: var(--{{ $status === 'won' ? 'success' : 'danger' }}-600);"></i>
                    {{ $status === 'won' ? 'Won deals' : 'Lost deals' }} <span class="ps-badge">{{ $closedDeals->total() }}</span>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="crm-table">
                    <thead>
                        <tr>
                            <th>Person</th><th>Interest</th><th>Value</th><th>Branch · Owner</th>
                            <th>{{ $status === 'won' ? 'Won on' : 'Lost on' }}</th>
                            <th>{{ $status === 'won' ? 'Invoice' : 'Reason' }}</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($closedDeals as $deal)
                            <tr id="closed-deal-{{ $deal->id }}">
                                <td>
                                    <div class="crm-name-cell">
                                        <div class="crm-avatar">{{ strtoupper(mb_substr($deal->lead->full_name ?? 'D', 0, 1)) }}</div>
                                        <div class="crm-name-text">
                                            @if ($can['lead'] && $deal->lead)
                                                <a href="{{ url('admin/leads/' . $deal->lead_id) }}" class="name">{{ $deal->lead->full_name }}</a>
                                            @else
                                                <span class="name">{{ $deal->lead->full_name ?? '—' }}</span>
                                            @endif
                                            <span style="font-size: 11px; color: var(--page-text-muted);">{{ $deal->lead->phone_number ?? '' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $deal->display_title }}</td>
                                <td><b>{{ $deal->value ? $money($deal->value) : '—' }}</b></td>
                                <td>{{ $deal->vendor->name ?? 'Head Office' }}<br><span style="font-size: 11px; color: var(--page-text-muted);">{{ $deal->owner->name ?? 'Unassigned' }}</span></td>
                                <td>{{ optional($status === 'won' ? $deal->won_at : $deal->lost_at)->format('d M Y') }}</td>
                                <td>
                                    @if ($status === 'won')
                                        @if ($deal->sale_master_id)
                                            @can('permission', 'sale_list.view')
                                                <a href="{{ url('admin/sale/' . $deal->sale_master_id) }}" class="btn btn-secondary btn-sm" id="invoice-{{ $deal->id }}"><i class="fas fa-file-invoice mr-1"></i>Invoice</a>
                                            @else
                                                <span class="crm-status-badge badge-status-won"><span class="dot"></span>Invoiced</span>
                                            @endcan
                                        @elseif ($can['sale'] && $deal->lead?->customer)
                                            <a href="{{ url('admin/sale/create?customer_id=' . $deal->lead->customer->id) }}" class="btn btn-primary btn-sm" id="create-sale-{{ $deal->id }}"><i class="fas fa-plus mr-1"></i>Create sale</a>
                                        @else
                                            <span style="color: var(--page-text-muted);">No invoice yet</span>
                                        @endif
                                    @else
                                        <b>{{ $deal->lostReason->name ?? '—' }}</b>
                                        @if ($deal->lost_to)<br><span style="font-size: 11px; color: var(--page-text-muted);">to {{ $deal->lost_to }}</span>@endif
                                        @if ($deal->lost_note)<br><span style="font-size: 11px; color: var(--page-text-muted);">{{ \Illuminate\Support\Str::limit($deal->lost_note, 80) }}</span>@endif
                                    @endif
                                </td>
                                <td style="text-align: center;">
                                    @if ($can['won'] || $can['lost'])
                                        <button type="button" class="btn btn-secondary btn-sm js-reopen" id="reopen-{{ $deal->id }}" data-id="{{ $deal->id }}"><i class="fas fa-undo mr-1"></i>Reopen</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--page-text-muted);">No {{ $status }} deals match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($closedDeals->hasPages())
                <div class="crm-table-footer">{{ $closedDeals->links() }}</div>
            @endif
        </div>
    @endif
</div>

{{-- Stage gate / move-back modal --}}
<div class="modal fade deal-modal" id="gateModal" tabindex="-1" role="dialog" aria-labelledby="gateModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" id="gateForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="gateModalTitle"><span class="m-icon" id="gateIcon"><i class="fas fa-arrow-right"></i></span><span><span id="gateTitle">Move deal</span><span class="m-sub" id="gateSub"></span></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group" data-gate="inquiry_for">
                    <label>What does the patient need? <i class="req text-danger">*</i></label>
                    <div class="deal-pills">
                        @foreach ($inquiries as $iq)
                            <label><input type="checkbox" name="inquiry_for[]" value="{{ $iq->value }}"><span>{{ $iq->label() }}</span></label>
                        @endforeach
                    </div>
                </div>
                <div class="form-group" data-gate="appointment_or_walkin">
                    <label>Assessment visit <i class="req text-danger">*</i></label>
                    <div class="deal-choice mb-2">
                        <label><input type="radio" name="visit_type" value="appointment" checked> <span><i class="far fa-calendar-check mr-1"></i>Appointment booked</span></label>
                        <label><input type="radio" name="visit_type" value="walk_in"> <span><i class="fas fa-walking mr-1"></i>Walk-in</span></label>
                    </div>
                    <input type="datetime-local" name="appointment_at" id="gate-appointment-at" class="form-control">
                    <div class="deal-hint mt-1">A follow-up is added for the appointment.</div>
                </div>
                <div class="form-group" data-gate="trial_product">
                    <label for="gate-trial-product">Trial product <i class="req text-danger">*</i></label>
                    <input type="text" name="trial_product" id="gate-trial-product" class="form-control" maxlength="150" placeholder="Brand, model" list="gate-trial-models" autocomplete="off">
                    <datalist id="gate-trial-models">@foreach ($trialModels ?? [] as $tm)<option value="{{ $tm }}"></option>@endforeach</datalist>
                    <select name="trial_result" id="gate-trial-result" class="form-control mt-2" aria-label="How the trial went">
                        <option value="">How did the trial go? (optional)</option>
                        @foreach (\App\Models\DealTrial::RESULTS as $rk => $rv)<option value="{{ $rk }}">{{ $rv }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group" data-gate="trial_return_date">
                    <label for="gate-trial-return">Trial return date <i class="req text-danger">*</i></label>
                    <input type="date" name="trial_return_date" id="gate-trial-return" class="form-control">
                    <div class="deal-hint mt-1">A "Trial return" follow-up call is added for this date.</div>
                </div>
                <div class="form-group" data-gate="value">
                    <label for="gate-value">Quoted value (৳) <i class="req text-danger">*</i></label>
                    <input type="number" name="value" id="gate-value" class="form-control" min="0" step="1">
                </div>
                <div class="form-group mb-0" data-gate="note">
                    <label for="gate-note"><span id="gateNoteLabel">Note</span> <i class="req text-danger" id="gateNoteReq">*</i></label>
                    <textarea name="note" id="gate-note" class="form-control" rows="3" maxlength="1000"></textarea>
                </div>
                <div class="deal-errors text-danger mt-2" style="font-size: 12.5px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="gateSubmit"><i class="fas fa-check mr-1"></i>Move deal</button>
            </div>
        </form>
    </div>
</div>

{{-- Won --}}
<div class="modal fade deal-modal" id="wonModal" tabindex="-1" role="dialog" aria-labelledby="wonModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" id="wonForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="wonModalTitle"><span class="m-icon" style="background: var(--success-500);"><i class="fas fa-trophy"></i></span><span>Mark as won<span class="m-sub" data-deal-name></span></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="deal-hint">The person becomes a customer (an existing customer record is reused). Points and tier are handled by the membership module when the sale is saved.</p>
                <div class="form-group">
                    <label for="won-note">Note</label>
                    <textarea name="note" id="won-note" class="form-control" rows="2" maxlength="1000" placeholder="Optional"></textarea>
                </div>
                @if ($can['sale'])
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="won-create-sale" name="create_sale" value="1" checked>
                        <label class="custom-control-label" for="won-create-sale">Open "Create sale" next, with this customer filled in</label>
                    </div>
                @endif
                <div class="deal-errors text-danger mt-2" style="font-size: 12.5px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" id="wonSubmit"><i class="fas fa-trophy mr-1"></i>Mark won</button>
            </div>
        </form>
    </div>
</div>

{{-- Lost --}}
<div class="modal fade deal-modal" id="lostModal" tabindex="-1" role="dialog" aria-labelledby="lostModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" id="lostForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="lostModalTitle"><span class="m-icon" style="background: var(--danger-500);"><i class="fas fa-times"></i></span><span>Mark as lost<span class="m-sub" data-deal-name></span></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="lost-reason">Reason <i class="req text-danger">*</i></label>
                    <select name="lost_reason_id" id="lost-reason" class="form-control">
                        <option value="">Choose a reason…</option>
                        @foreach ($lostReasons as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="lost-to">Lost to whom</label>
                    <input type="text" name="lost_to" id="lost-to" class="form-control" maxlength="150" placeholder="Competitor / shop name, if known">
                </div>
                <div class="form-group mb-0">
                    <label for="lost-note">Note <i class="req text-danger">*</i></label>
                    <textarea name="note" id="lost-note" class="form-control" rows="2" maxlength="1000"></textarea>
                </div>
                <div class="deal-errors text-danger mt-2" style="font-size: 12.5px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger" id="lostSubmit"><i class="fas fa-times mr-1"></i>Mark lost</button>
            </div>
        </form>
    </div>
</div>

{{-- Transfer --}}
@if ($can['transfer'])
<div class="modal fade deal-modal" id="transferModal" tabindex="-1" role="dialog" aria-labelledby="transferModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" id="transferForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="transferModalTitle"><span class="m-icon"><i class="fas fa-share-square"></i></span><span>Transfer deal<span class="m-sub" data-deal-name></span></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="deal-hint">Head Office keeps seeing the deal; source and campaign stay on it.</p>
                <div class="form-group">
                    <label for="transfer-branch">Branch <i class="req text-danger">*</i></label>
                    <select name="vendor_id" id="transfer-branch" class="form-control">
                        <option value="">Head Office</option>
                        @foreach ($branches as $bid => $bname)
                            <option value="{{ $bid }}">{{ $bname }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="transfer-owner">Handled by <i class="req text-danger">*</i></label>
                    <select name="owner_id" id="transfer-owner" class="form-control"></select>
                </div>
                <div class="form-group mb-0">
                    <label for="transfer-note">Note for the branch</label>
                    <textarea name="note" id="transfer-note" class="form-control" rows="2" maxlength="1000" placeholder="e.g. Prefers Saturday visit"></textarea>
                </div>
                <div class="deal-errors text-danger mt-2" style="font-size: 12.5px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="transferSubmit"><i class="fas fa-share mr-1"></i>Transfer</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Quick Add --}}
@if ($can['create'])
<div class="modal fade deal-modal" id="quickAddModal" tabindex="-1" role="dialog" aria-labelledby="quickAddTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" id="quickAddForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="quickAddTitle"><span class="m-icon"><i class="fas fa-bolt"></i></span><span>Quick Add<span class="m-sub">Person + deal in a few seconds</span></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="existing_lead_id" id="qa-existing">
                <div class="qa-picked-banner" id="qa-picked" style="display: none;">
                    <i class="fas fa-user-check"></i><span>New deal for <b data-picked-name></b></span>
                    <button type="button" class="btn btn-link btn-sm" id="qa-unpick">Change</button>
                </div>
                <div data-new-person>
                    <div class="form-row">
                        <div class="form-group col-sm-6">
                            <label for="qa-phone">Phone <i class="req text-danger">*</i></label>
                            <input type="tel" name="phone_number" id="qa-phone" class="form-control" maxlength="20" placeholder="01XXXXXXXXX" autocomplete="off">
                        </div>
                        <div class="form-group col-sm-6">
                            <label for="qa-card">Membership card no.</label>
                            <input type="text" id="qa-card" class="form-control" maxlength="50" placeholder="For returning customers" autocomplete="off">
                        </div>
                    </div>
                    <div class="qa-matches" id="qa-matches" aria-live="polite"></div>
                    <div class="form-group">
                        <label for="qa-name">Full name <i class="req text-danger">*</i></label>
                        <input type="text" name="full_name" id="qa-name" class="form-control" maxlength="255">
                    </div>
                </div>
                @if ($pipelines->count() > 1)
                    <div class="form-group">
                        <label for="qa-pipeline">Pipeline</label>
                        <select name="pipeline_id" id="qa-pipeline" class="form-control">
                            @foreach ($pipelines as $pl)
                                <option value="{{ $pl->id }}" @selected($pl->id === $pipeline->id)>{{ $pl->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group">
                    <label>Interest <i class="req text-danger">*</i></label>
                    <div class="deal-pills">
                        @foreach ($inquiries as $iq)
                            <label><input type="checkbox" name="inquiry_for[]" value="{{ $iq->value }}"><span>{{ $iq->label() }}</span></label>
                        @endforeach
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-sm-4">
                        <label for="qa-source">Source</label>
                        <select name="source" id="qa-source" class="form-control">
                            <option value="">—</option>
                            @foreach ($sources as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group col-sm-4">
                        <label for="qa-temperature">Temperature</label>
                        <select name="temperature" id="qa-temperature" class="form-control">
                            <option value="">—</option>
                            @foreach ($temperatures as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group col-sm-4">
                        <label for="qa-value">Value (৳)</label>
                        <input type="number" name="value" id="qa-value" class="form-control" min="0" step="1">
                    </div>
                </div>
                <div class="form-group qa-hearing" id="qa-hearing">
                    <label>Hearing loss <small class="text-muted">(optional)</small></label>
                    @foreach (['right' => 'Right ear', 'left' => 'Left ear'] as $ear => $earLabel)
                        <div class="qa-ear" id="qa-ear-{{ $ear }}">
                            <label class="qa-ear-toggle" for="qa-hl-{{ $ear }}">
                                <input type="checkbox" name="hearing_loss[{{ $ear }}][enabled]" value="1" id="qa-hl-{{ $ear }}">
                                <span class="dd-ear dd-ear-{{ $ear }}"><i class="fas fa-deaf"></i> {{ $earLabel }}</span>
                            </label>
                            <select name="hearing_loss[{{ $ear }}][degree]" id="qa-hl-{{ $ear }}-degree" class="form-control form-control-sm" aria-label="{{ $earLabel }} degree">
                                <option value="">Degree</option>
                                @foreach (\App\Enums\HearingDegree::cases() as $d)<option value="{{ $d->value }}">{{ $d->label() }}</option>@endforeach
                            </select>
                            <select name="hearing_loss[{{ $ear }}][type]" id="qa-hl-{{ $ear }}-type" class="form-control form-control-sm" aria-label="{{ $earLabel }} type">
                                <option value="">Type</option>
                                @foreach (\App\Enums\HearingType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <div class="form-group mb-0">
                    <label for="qa-note">First call note</label>
                    <textarea name="note" id="qa-note" class="form-control" rows="2" maxlength="1000" placeholder="A call is scheduled within 1 office hour"></textarea>
                </div>
                <div class="deal-errors text-danger mt-2" style="font-size: 12.5px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="quickAddSubmit"><i class="fas fa-plus mr-1"></i>Add deal</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Deal drawer --}}
<div class="deal-drawer-backdrop" id="deal-drawer-backdrop"></div>
<aside class="deal-drawer" id="deal-drawer" role="dialog" aria-modal="true" aria-labelledby="dd-title"></aside>

<div class="deal-toast-wrap" id="deal-toasts" aria-live="polite"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/sortablejs/Sortable.min.js') }}"></script>
    <script>
        window.DealBoard = {
            csrf: @json(csrf_token()),
            base: @json(url('admin/deals')),
            query: @json((object) $filters),
            can: @json($can),
            owners: @json($owners),
            stages: @json($stagesForJs),
            view: @json($status),
        };
    </script>
    <script src="{{ asset('assets/js/deal-board.js') }}?v={{ filemtime(public_path('assets/js/deal-board.js')) }}"></script>
@endpush
