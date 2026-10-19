@extends('admin.layouts.master')
@section('page_title', 'Sound Booths')
@section('breadcrumb_parent', 'Appointments')
@section('breadcrumb_parent_url', url('admin/appointments'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-door-closed" style="color: var(--accent-500); margin-right: 8px;"></i>Sound Booths</h1>
            <p>Hearing tests need a sound booth. A branch with no active booth cannot take those bookings.</p>
        </div>
    </div>

    @include('admin.alert_message')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @can('permission', 'sound_booths.create')
    <div class="crm-table-card crm-animate-in" style="margin-bottom: 20px; padding: 16px;">
        <form action="{{ route('sound-booths.store') }}" method="post" class="form-inline" style="gap: 8px;">
            @csrf
            @if (count($branches) > 1)
                <select name="vendor_id" class="form-control form-control-sm" required>
                    <option value="">Branch...</option>
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((int) old('vendor_id', $selectedBranch) === (int) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="vendor_id" value="{{ array_key_first($branches) }}">
                <strong>{{ reset($branches) }}</strong>
            @endif
            <input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-sm" placeholder="Booth name, e.g. Sound Booth 1" maxlength="100" required>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Booth</button>
        </form>
    </div>
    @endcan

    @forelse ($branches as $branchId => $branchName)
        @php($booths = $boothsByBranch->get($branchId, collect()))
        <div class="crm-table-card crm-animate-in" style="margin-bottom: 16px;">
            <div class="crm-table-header">
                <div class="table-title"><i class="fas fa-store" style="color: var(--page-text-muted);"></i> {{ $branchName }} <span class="table-count">{{ $booths->count() }}</span></div>
            </div>
            @if ($booths->where('is_active', true)->isEmpty())
                <div class="alert alert-warning" style="margin: 12px 16px 0;">No active sound booth here: Pure Tone Audiometry and other booth services cannot be booked at this branch.</div>
            @endif
            <div style="overflow-x: auto;">
                <table class="crm-table">
                    <tbody>
                    @forelse ($booths as $booth)
                        <tr>
                            <td>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    @can('permission', 'sound_booths.edit')
                                    <form action="{{ route('sound-booths.update', $booth->id) }}" method="post" class="form-inline" style="gap: 8px; flex: 1;">
                                        @csrf @method('PUT')
                                        <input type="text" name="name" value="{{ $booth->name }}" class="form-control form-control-sm" maxlength="100" required>
                                        <label style="margin: 0; font-size: 13px;"><input type="checkbox" name="is_active" value="1" @checked($booth->is_active)> Active</label>
                                        <button type="submit" class="btn btn-outline-primary btn-sm">Save</button>
                                    </form>
                                    @else
                                        <span style="font-weight: 600;">{{ $booth->name }}</span>
                                        <span class="crm-status-badge {{ $booth->is_active ? 'badge-status-won' : 'badge-status-lost' }}"><span class="dot"></span>{{ $booth->is_active ? 'Active' : 'Inactive' }}</span>
                                    @endcan
                                    @can('permission', 'sound_booths.delete')
                                        @if (in_array($booth->id, $usedIds))
                                            <span class="text-muted" style="font-size: 12px;" title="Appointments are booked on this booth">In use</span>
                                        @else
                                        <form action="{{ route('sound-booths.destroy', $booth->id) }}" method="post" style="display: inline;">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button>
                                        </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td style="text-align: center; padding: 24px !important; color: var(--page-text-muted);">No sound booth added for this branch yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="alert alert-info">No branches found.</div>
    @endforelse
</div>
@endsection
