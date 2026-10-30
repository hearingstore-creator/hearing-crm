@extends('admin.layouts.master')
@section('page_title', 'Campaigns')
@section('breadcrumb_parent', 'Marketing')
@section('breadcrumb_parent_url', url('admin/marketing/roi'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
.mk-code { font-family: monospace; font-size: 12px; background: var(--gray-50); border: 1px solid var(--gray-100); padding: 1px 6px; border-radius: 4px; }
.mk-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px 14px; }
.mk-form label { font-size: 12px; font-weight: 600; margin-bottom: 2px; }
.mk-links { font-size: 12px; background: var(--gray-50); border-radius: 8px; padding: 10px 12px; margin-top: 6px; }
.mk-links input { font-family: monospace; font-size: 11px; }
.mk-qr { display: inline-block; padding: 6px; background: #fff; border: 1px solid var(--gray-100); border-radius: 6px; }
.mk-status-active { color: var(--success-700); } .mk-status-paused { color: var(--warning-700); } .mk-status-ended { color: var(--page-text-muted); }
</style>
@endpush

@php
    $money = fn ($v) => $v === null ? '—' : '৳' . number_format((float) $v);
    $channelLabel = fn ($v) => \App\Enums\MarketingChannel::tryFrom((string) $v)?->label() ?? $v;
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-bullhorn" style="color: var(--accent-500); margin-right: 8px;"></i>Campaigns</h1>
            <p>Each campaign has a code. Put it in your ad links as <span class="mk-code">utm_campaign</span>, or use its QR code, and every lead it brings is counted for it.</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('marketing.spend.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-wallet mr-1"></i>Monthly spend</a>
            @can('permission', 'marketing_campaigns.create')
            <button class="btn btn-primary btn-sm" type="button" data-toggle="collapse" data-target="#mkNew" id="mkNewBtn"><i class="fas fa-plus mr-1"></i>New campaign</button>
            @endcan
        </div>
    </div>

    @can('permission', 'marketing_campaigns.create')
    <div class="collapse {{ $errors->any() && !old('_campaign_id') ? 'show' : '' }}" id="mkNew">
        <div class="crm-table-card" style="padding: 16px 20px; margin-bottom: 16px;">
            <form method="POST" action="{{ route('marketing.campaigns.store') }}">
                @csrf
                @include('admin.marketing.partials.campaign-fields', ['c' => null])
                <div class="mt-3"><button class="btn btn-primary btn-sm" type="submit" id="mkSave">Save campaign</button></div>
            </form>
        </div>
    </div>
    @endcan

    <form method="GET" class="crm-table-card" style="padding: 10px 16px; margin-bottom: 12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:220px;" placeholder="Name or code">
        <select name="channel" class="form-control form-control-sm" style="max-width:200px;">
            <option value="">All channels</option>
            @foreach ($channels as $ch)<option value="{{ $ch->value }}" @selected(request('channel') === $ch->value)>{{ $ch->label() }}</option>@endforeach
        </select>
        <select name="status" class="form-control form-control-sm" style="max-width:150px;">
            <option value="">Any status</option>
            @foreach (\App\Models\MarketingCampaign::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
    </form>

    <div class="crm-table-card" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-sm mb-0" style="font-size: 13px;">
                <thead><tr><th>Campaign</th><th>Channel</th><th>Branch</th><th>Dates</th><th class="text-right">Budget</th><th class="text-right">Spent</th><th class="text-right">Enquiries</th><th>Status</th><th style="width:150px;"></th></tr></thead>
                <tbody>
                @forelse ($campaigns as $c)
                    <tr>
                        <td><strong>{{ $c->name }}</strong><br><span class="mk-code">{{ $c->code }}</span></td>
                        <td>{{ $channelLabel($c->channel) }}</td>
                        <td>{{ $c->vendor_id ? ($branches[$c->vendor_id] ?? '#' . $c->vendor_id) : 'All' }}</td>
                        <td style="white-space:nowrap;">{{ $c->start_date?->format('d M Y') ?? '—' }} – {{ $c->end_date?->format('d M Y') ?? '…' }}</td>
                        <td class="text-right">{{ $money($c->budget) }}</td>
                        <td class="text-right">{{ $money($c->spent ?? 0) }}</td>
                        <td class="text-right">{{ number_format($enquiries[$c->id] ?? 0) }}</td>
                        <td class="mk-status-{{ $c->status }}">{{ \App\Models\MarketingCampaign::STATUSES[$c->status] ?? $c->status }}</td>
                        <td class="text-right" style="white-space:nowrap;">
                            <button class="btn btn-outline-secondary btn-sm py-0" type="button" data-toggle="collapse" data-target="#mkRow{{ $c->id }}">Links &amp; edit</button>
                            @can('permission', 'marketing_campaigns.delete')
                            <form action="{{ route('marketing.campaigns.destroy', $c->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this campaign? If it has spend or leads it is marked Ended instead.');">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm py-0" type="submit" title="Delete"><i class="far fa-trash-alt"></i></button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    <tr class="collapse" id="mkRow{{ $c->id }}">
                        <td colspan="9" style="background: var(--gray-50);">
                            <div class="mk-links">
                                <div class="mb-1"><strong>Ad link ending</strong> (add to the website address in the ad):</div>
                                <input type="text" readonly class="form-control form-control-sm mb-2" onclick="this.select()"
                                       value="?utm_source={{ in_array($c->channel, ['facebook', 'instagram', 'google', 'youtube', 'whatsapp', 'sms']) ? $c->channel : 'other' }}&utm_medium=paid&utm_campaign={{ $c->code }}">
                                @foreach ($forms as $form)
                                    @php $qrUrl = url('f/' . $form->key) . '?c=' . urlencode($c->code); @endphp
                                    <div style="display:flex; gap:14px; align-items:center; margin-top:8px;">
                                        <div class="mk-qr" data-qr="{{ $qrUrl }}" title="QR for {{ $form->name }}"></div>
                                        <div style="flex:1;">
                                            <div><strong>QR / form link</strong> – {{ $form->name }}</div>
                                            <input type="text" readonly class="form-control form-control-sm" onclick="this.select()" value="{{ $qrUrl }}">
                                            <small class="text-muted">Print this QR on leaflets or show it at the camp. Right-click the code to save it.</small>
                                        </div>
                                    </div>
                                @endforeach
                                @if ($forms->isEmpty())<div class="text-muted">Add a website capture form (Leads → Website Leads) to get a QR link.</div>@endif
                            </div>
                            @can('permission', 'marketing_campaigns.edit')
                            <form method="POST" action="{{ route('marketing.campaigns.update', $c->id) }}" class="mt-3">
                                @csrf @method('PUT')
                                @include('admin.marketing.partials.campaign-fields', ['c' => $c])
                                <div class="mt-2"><button class="btn btn-primary btn-sm" type="submit">Save changes</button></div>
                            </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted" style="padding: 30px;">No campaigns yet. Add one for each ad campaign, hearing camp or leaflet run you want to measure.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding: 8px 16px;">{{ $campaigns->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll('.mk-qr[data-qr]').forEach(function (el) {
    if (window.QRCode) { new QRCode(el, { text: el.getAttribute('data-qr'), width: 96, height: 96, correctLevel: QRCode.CorrectLevel.M }); }
});
</script>
@endpush
