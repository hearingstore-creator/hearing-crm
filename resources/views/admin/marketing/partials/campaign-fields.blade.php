@php $p = $c ? 'e' . $c->id . '_' : 'n_'; @endphp
<div class="mk-form">
    <div>
        <label for="{{ $p }}name">Name *</label>
        <input type="text" name="name" id="{{ $p }}name" class="form-control form-control-sm" required maxlength="150" value="{{ $c ? $c->name : old('name') }}" placeholder="e.g. Eid hearing-aid offer">
    </div>
    <div>
        <label for="{{ $p }}code">Code (utm_campaign)</label>
        <input type="text" name="code" id="{{ $p }}code" class="form-control form-control-sm" maxlength="150" value="{{ $c ? $c->code : old('code') }}" placeholder="made from the name if empty">
    </div>
    <div>
        <label for="{{ $p }}channel">Channel *</label>
        <select name="channel" id="{{ $p }}channel" class="form-control form-control-sm" required>
            @foreach (\App\Enums\MarketingChannel::forCampaigns() as $ch)
                <option value="{{ $ch->value }}" @selected(($c ? $c->channel : old('channel', 'facebook')) === $ch->value)>{{ $ch->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $p }}vendor">Branch</label>
        <select name="vendor_id" id="{{ $p }}vendor" class="form-control form-control-sm">
            <option value="">All / head office</option>
            @foreach (\App\Classes\Helper::branchOptions() as $bid => $bname)
                <option value="{{ $bid }}" @selected((string) ($c ? $c->vendor_id : old('vendor_id')) === (string) $bid)>{{ $bname }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $p }}start">Start</label>
        <input type="date" name="start_date" id="{{ $p }}start" class="form-control form-control-sm" value="{{ $c ? $c->start_date?->toDateString() : old('start_date') }}">
    </div>
    <div>
        <label for="{{ $p }}end">End</label>
        <input type="date" name="end_date" id="{{ $p }}end" class="form-control form-control-sm" value="{{ $c ? $c->end_date?->toDateString() : old('end_date') }}">
    </div>
    <div>
        <label for="{{ $p }}budget">Budget (৳)</label>
        <input type="number" step="0.01" min="0" name="budget" id="{{ $p }}budget" class="form-control form-control-sm" value="{{ $c ? $c->budget : old('budget') }}">
    </div>
    <div>
        <label for="{{ $p }}status">Status</label>
        <select name="status" id="{{ $p }}status" class="form-control form-control-sm">
            @foreach (\App\Models\MarketingCampaign::STATUSES as $k => $v)
                <option value="{{ $k }}" @selected(($c ? $c->status : old('status', 'active')) === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="{{ $p }}ext">Meta / Google campaign ID</label>
        <input type="text" name="external_id" id="{{ $p }}ext" class="form-control form-control-sm" maxlength="60" value="{{ $c ? $c->external_id : old('external_id') }}" placeholder="filled in by lead-form leads">
    </div>
    <div style="grid-column: 1 / -1;">
        <label for="{{ $p }}notes">Notes</label>
        <input type="text" name="notes" id="{{ $p }}notes" class="form-control form-control-sm" maxlength="2000" value="{{ $c ? $c->notes : old('notes') }}" placeholder="Audience, offer, agency…">
    </div>
</div>
