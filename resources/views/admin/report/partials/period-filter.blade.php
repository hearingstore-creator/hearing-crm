{{--
    Period filter controls (preset + custom range). Expects: $periods, $period, $start, $end.
    Rendered inside a <form>.
--}}
<div class="f-group">
    <label for="f-period">Period</label>
    <select name="period" id="f-period" onchange="document.getElementById('customDates').classList.toggle('hidden', this.value !== 'custom')">
        @foreach ($periods as $key => $label)
            <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div id="customDates" class="custom-dates {{ $period === 'custom' ? '' : 'hidden' }}">
    <div class="f-group">
        <label for="f-start">From</label>
        <input type="date" name="start_date" id="f-start" value="{{ $start->toDateString() }}">
    </div>
    <div class="f-group">
        <label for="f-end">To</label>
        <input type="date" name="end_date" id="f-end" value="{{ $end->toDateString() }}">
    </div>
</div>
