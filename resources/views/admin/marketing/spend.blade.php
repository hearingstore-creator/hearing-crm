@extends('admin.layouts.master')
@section('page_title', 'Monthly Spend')
@section('breadcrumb_parent', 'Marketing')
@section('breadcrumb_parent_url', url('admin/marketing/roi'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
.mk-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; }
@media (max-width: 991px) { .mk-grid { grid-template-columns: 1fr; } }
.mk-card { padding: 14px 18px; margin-bottom: 16px; }
.mk-h { font-size: 14px; font-weight: 700; margin: 0 0 10px; }
.mk-inline { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px 12px; align-items: end; }
.mk-inline label { font-size: 12px; font-weight: 600; margin-bottom: 2px; }
.mk-src { font-size: 11px; color: var(--page-text-muted); }
</style>
@endpush

@php
    $money = fn ($v) => '৳' . number_format((float) $v, 2);
    $ym = $month->format('Y-m');
    $channelLabel = fn ($v) => \App\Enums\MarketingChannel::tryFrom((string) $v)?->label() ?? $v;
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-wallet" style="color: var(--accent-500); margin-right: 8px;"></i>Monthly Spend</h1>
            <p>What each campaign cost per month. Saving it also books the advertising expense, so do not type it again in Expenses.</p>
        </div>
        <div class="header-actions" style="display:flex; gap:6px; align-items:center;">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('marketing.spend.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" title="Previous month"><i class="fas fa-chevron-left"></i></a>
            <form method="GET"><input type="month" name="month" value="{{ $ym }}" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Month"></form>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('marketing.spend.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" title="Next month"><i class="fas fa-chevron-right"></i></a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('marketing.campaigns.index') }}"><i class="fas fa-bullhorn mr-1"></i>Campaigns</a>
        </div>
    </div>

    @if ($preview)
        <div class="crm-table-card mk-card" style="border-left: 4px solid var(--accent-500);" id="mkPreview">
            <h2 class="mk-h"><i class="fas fa-file-import mr-1"></i>Check the import from {{ $preview['platform'] === 'google' ? 'Google Ads' : 'Meta' }}</h2>
            <p style="font-size:12px;" class="text-muted">
                {{ count($preview['rows']) }} campaign-month(s) found{{ $preview['skipped'] ? ', ' . $preview['skipped'] . ' line(s) without spend skipped' : '' }}.
                @if ($preview['currency'] && $preview['currency'] !== 'BDT')
                    <strong style="color: var(--danger-600);">The file is in {{ $preview['currency'] }}; amounts below were multiplied by {{ $preview['rate'] }}.</strong>
                @endif
                Campaigns not set up yet are created when you save (as {{ $preview['platform'] === 'google' ? 'Google' : 'Facebook' }} campaigns).
            </p>
            <form method="POST" action="{{ route('marketing.spend.import.confirm') }}">
                @csrf
                <input type="hidden" name="platform" value="{{ $preview['platform'] }}">
                <table class="table table-sm" style="font-size:13px;">
                    <thead><tr><th style="width:40px;"></th><th>In the file</th><th>Month</th><th class="text-right">Amount</th><th>Counts for campaign</th></tr></thead>
                    <tbody>
                    @foreach ($preview['rows'] as $i => $r)
                        <tr>
                            <td><input type="checkbox" name="rows[{{ $i }}][include]" value="1" checked></td>
                            <td>{{ $r['name'] }}<input type="hidden" name="rows[{{ $i }}][name]" value="{{ $r['name'] }}"></td>
                            <td>{{ \Illuminate\Support\Carbon::parse($r['month'] . '-01')->format('M Y') }}<input type="hidden" name="rows[{{ $i }}][month]" value="{{ $r['month'] }}"></td>
                            <td class="text-right">{{ $money($r['amount']) }}<input type="hidden" name="rows[{{ $i }}][amount]" value="{{ $r['amount'] }}"></td>
                            <td>
                                <select name="rows[{{ $i }}][campaign_id]" class="form-control form-control-sm">
                                    <option value="">+ Create “{{ \Illuminate\Support\Str::limit($r['name'], 40) }}”</option>
                                    @foreach ($campaigns as $c)<option value="{{ $c->id }}" @selected($r['campaign_id'] == $c->id)>{{ $c->name }}</option>@endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <button class="btn btn-primary btn-sm" type="submit" id="mkImportSave">Save these amounts</button>
                <a href="{{ route('marketing.spend.index', ['month' => $ym]) }}" class="btn btn-link btn-sm">Cancel</a>
            </form>
        </div>
    @endif

    <div class="mk-grid">
        <div>
            @can('permission', 'marketing_spend.create')
            <div class="crm-table-card mk-card">
                <h2 class="mk-h">Add or change spend for {{ $month->format('F Y') }}</h2>
                <form method="POST" action="{{ route('marketing.spend.store') }}" class="mk-inline">
                    @csrf
                    <input type="hidden" name="month" value="{{ $ym }}">
                    <div style="grid-column: span 2;">
                        <label for="mkCampaign">Campaign</label>
                        <select name="campaign_id" id="mkCampaign" class="form-control form-control-sm" required>
                            <option value="">Choose…</option>
                            @foreach ($campaigns as $c)<option value="{{ $c->id }}" @selected(old('campaign_id') == $c->id)>{{ $c->name }} · {{ $channelLabel($c->channel) }}{{ $c->status !== 'active' ? ' (' . $c->status . ')' : '' }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="mkBranch">Branch</label>
                        <select name="vendor_id" id="mkBranch" class="form-control form-control-sm">
                            <option value="">Head office</option>
                            @foreach ($branches as $bid => $bname)<option value="{{ $bid }}">{{ $bname }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="mkAmount">Amount (৳)</label>
                        <input type="number" step="0.01" min="0" name="amount" id="mkAmount" class="form-control form-control-sm" required value="{{ old('amount') }}">
                    </div>
                    <div style="grid-column: span 2;">
                        <label for="mkNote">Note</label>
                        <input type="text" name="note" id="mkNote" class="form-control form-control-sm" maxlength="255" placeholder="e.g. boost, agency fee">
                    </div>
                    <div><button class="btn btn-primary btn-sm btn-block" type="submit" id="mkSpendSave">Save</button></div>
                </form>
                <small class="text-muted">Saving again for the same campaign, month and branch replaces the amount (the old expense is reversed).</small>
            </div>
            @endcan

            <div class="crm-table-card" style="padding:0; margin-bottom:16px;">
                <table class="table table-sm mb-0" style="font-size:13px;">
                    <thead><tr><th>Campaign</th><th>Channel</th><th>Branch</th><th class="text-right">Amount</th><th>Entered as</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>{{ $r->campaign->name ?? '—' }}@if ($r->note)<br><span class="mk-src">{{ $r->note }}</span>@endif</td>
                            <td>{{ $channelLabel($r->campaign->channel ?? '') }}</td>
                            <td>{{ $r->vendor->name ?? 'Head office' }}</td>
                            <td class="text-right">{{ $money($r->amount) }}</td>
                            <td class="mk-src">{{ \App\Models\MarketingSpend::SOURCES[$r->source] ?? $r->source }}</td>
                            <td class="text-right">
                                @can('permission', 'marketing_spend.delete')
                                <form method="POST" action="{{ route('marketing.spend.destroy', $r->id) }}" onsubmit="var x = prompt('Reason for removing this spend (its expense is reversed):'); if (!x) return false; this.reason.value = x;">
                                    @csrf @method('DELETE')<input type="hidden" name="reason">
                                    <button class="btn btn-outline-danger btn-sm py-0" type="submit" title="Remove"><i class="far fa-trash-alt"></i></button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No spend entered for {{ $month->format('F Y') }}.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot><tr><th colspan="3">Total</th><th class="text-right">{{ $money($total) }}</th><th colspan="2"></th></tr></tfoot>
                </table>
            </div>
        </div>

        <div>
            @can('permission', 'marketing_spend.import')
            <div class="crm-table-card mk-card">
                <h2 class="mk-h"><i class="fas fa-file-csv mr-1"></i>Import from Meta or Google</h2>
                <form method="POST" action="{{ route('marketing.spend.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group mb-2">
                        <select name="platform" class="form-control form-control-sm" aria-label="Platform">
                            <option value="meta">Meta Ads Manager (Facebook, Instagram, WhatsApp)</option>
                            <option value="google">Google Ads</option>
                        </select>
                    </div>
                    <div class="form-group mb-2"><input type="file" name="file" accept=".csv,text/csv" class="form-control-file" required aria-label="CSV file"></div>
                    <div class="form-group mb-2" style="display:flex; gap:6px; align-items:center;">
                        <label class="mb-0" style="font-size:12px;" for="mkRate">Rate to ৳</label>
                        <input type="number" step="0.0001" min="0.0001" name="rate" id="mkRate" value="1" class="form-control form-control-sm" style="width:110px;">
                    </div>
                    <button class="btn btn-outline-primary btn-sm" type="submit">Read file</button>
                </form>
                <small class="text-muted d-block mt-2">Meta: Ads Manager → Reports → export with “Campaign name”, “Amount spent” and “Reporting starts” (by month or day). Google: Campaigns report with “Campaign”, “Cost” and “Month” or “Day”. If the account is billed in USD, enter the taka rate.</small>
            </div>
            @endcan

            <div class="crm-table-card mk-card">
                <h2 class="mk-h">Last 6 months</h2>
                <table class="table table-sm mb-0" style="font-size:13px;">
                    @for ($i = 5; $i >= 0; $i--)
                        @php $m = $month->copy()->subMonths($i); @endphp
                        <tr><td><a href="{{ route('marketing.spend.index', ['month' => $m->format('Y-m')]) }}">{{ $m->format('M Y') }}</a></td><td class="text-right">{{ $money($trend[$m->format('Y-m')] ?? 0) }}</td></tr>
                    @endfor
                </table>
            </div>

            @if ($unlinked->count())
            @can('permission', 'marketing_spend.create')
            <div class="crm-table-card mk-card">
                <h2 class="mk-h">Advertising already in Expenses</h2>
                <p style="font-size:12px;" class="text-muted">These were typed in Expenses before. Count each for a campaign; no second expense is booked.</p>
                @foreach ($unlinked as $l)
                    <form method="POST" action="{{ route('marketing.spend.link') }}" style="border-top:1px solid var(--gray-100); padding:6px 0; font-size:12px;">
                        @csrf <input type="hidden" name="ledger_id" value="{{ $l->id }}">
                        <div><strong>{{ $money($l->expense_amount) }}</strong> · {{ \Illuminate\Support\Carbon::parse($l->date)->format('d M Y') }} · {{ $l->head_name }}<br><span class="mk-src">{{ \Illuminate\Support\Str::limit($l->details, 80) }}</span></div>
                        <div style="display:flex; gap:6px; margin-top:4px;">
                            <select name="campaign_id" class="form-control form-control-sm" required aria-label="Campaign">
                                <option value="">Campaign…</option>
                                @foreach ($campaigns as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                            </select>
                            <button class="btn btn-outline-primary btn-sm" type="submit">Count</button>
                        </div>
                    </form>
                @endforeach
            </div>
            @endcan
            @endif
        </div>
    </div>
</div>
@endsection
