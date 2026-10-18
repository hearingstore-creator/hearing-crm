{{--
    "Works At (Branch)" picker for staff accounts — stores users.vendor_id (empty = Head Office).
    Usage: @include('admin.partials.branch_select', ['selected' => $user->vendor_id ?? null])
--}}
@php $selectedBranch = old('vendor_id', $selected ?? null); @endphp
<div class="form-group">
    <label for="vendor_id">Works At (Branch)</label>
    <select name="vendor_id" id="vendor_id" class="form-control">
        <option value="">Head Office</option>
        @foreach (\App\Classes\Helper::branchOptions() as $bid => $bname)
            <option value="{{ $bid }}" @selected((string) $selectedBranch === (string) $bid)>{{ $bname }}</option>
        @endforeach
    </select>
    <small class="form-text text-muted">Branch staff only see their branch's leads, sales &amp; stock.</small>
</div>
