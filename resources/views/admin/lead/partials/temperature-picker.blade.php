{{-- Hot / Warm / Cold lead temperature picker. Expects optional $selected (string). --}}
@php $selectedTemp = $selected ?? 'warm'; @endphp
<div class="form-group">
    <label>Lead Category <small class="text-muted">(Hot / Warm / Cold)</small></label>
    <div class="temp-picker" role="radiogroup" aria-label="Lead category">
        @foreach (\App\Enums\LeadTemperature::cases() as $temp)
            <label class="temp-opt temp-{{ $temp->value }}" for="lead_temperature_{{ $temp->value }}">
                <input type="radio" name="lead_temperature" id="lead_temperature_{{ $temp->value }}" value="{{ $temp->value }}" {{ $selectedTemp === $temp->value ? 'checked' : '' }}>
                <span><i class="{{ $temp->icon() }}"></i>{{ $temp->label() }}</span>
            </label>
        @endforeach
    </div>
    @error('lead_temperature')
        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
    @enderror
</div>

@once
@push('css')
<style>
    .temp-picker { display: flex; gap: 8px; flex-wrap: wrap; }
    .temp-opt { flex: 1 1 0; min-width: 96px; margin: 0; cursor: pointer; }
    .temp-opt input { position: absolute; opacity: 0; pointer-events: none; }
    .temp-opt span {
        display: flex; align-items: center; justify-content: center; gap: 6px;
        height: 38px; padding: 0 10px; border-radius: 8px; font-size: 13px; font-weight: 600;
        border: 1.5px solid var(--card-border, #e2e8f0); color: var(--page-text-secondary, #64748b);
        background: var(--card-bg, #fff); transition: all .18s ease;
    }
    .temp-opt:hover span { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(15, 23, 42, .06); }
    .temp-hot input:checked + span  { background: #fef2f2; border-color: #f87171; color: #dc2626; }
    .temp-warm input:checked + span { background: #fffbeb; border-color: #fbbf24; color: #d97706; }
    .temp-cold input:checked + span { background: #eff6ff; border-color: #60a5fa; color: #2563eb; }
    .temp-opt input:focus-visible + span { outline: 2px solid var(--accent-500, #6366f1); outline-offset: 2px; }
</style>
@endpush
@endonce
