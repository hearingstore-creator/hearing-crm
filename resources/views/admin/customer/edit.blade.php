@extends('admin.layouts.master')
@section('page_title', 'Edit Customer')
@section('breadcrumb_parent', 'Customers')
@section('breadcrumb_parent_url', url('admin/customers'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-edit" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Customer</h1>
            <p>Update customer information for <strong>{{ $customer->lead->full_name ?? '' }}</strong></p>
        </div>
        <div class="header-actions">
            @can('permission', 'customer_list.index')
                <a href="{{ url('admin/customers') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Back to List</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1" style="padding: 0;">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-id-card" style="color: var(--page-text-muted);"></i> Customer Information</div>
        </div>
        <div style="padding: 24px;">
            <form action="{{ route('customers.update', $customer->id) }}" enctype="multipart/form-data" method="post">
                @csrf
                @method('put')
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="full_name" class="crm-form-label">Full Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="full_name" value="{{ $customer->lead->full_name ?? '' }}" placeholder="Full Name" name="full_name" autocomplete="off" required>
                        </div>

                        <div class="form-group">
                            <label for="email" class="crm-form-label">Email</label>
                            <input type="email" class="form-control" id="email" value="{{ $customer->lead->email ?? '' }}" placeholder="Email" name="email">
                        </div>

                        <div class="form-group">
                            <label for="district_id" class="crm-form-label">District</label>
                            <select class="form-control select2" id="district_id" name="district_id">
                                <option value="">Select District</option>
                                @if(isset($districts))
                                    @foreach($districts as $district)
                                        <option value="{{ $district->id }}" {{ ($customer->lead->district_id ?? '') == $district->id ? 'selected' : '' }}>{{ $district->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="address" class="crm-form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3">{{ $customer->lead->address ?? '' }}</textarea>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone_number" class="crm-form-label">Phone Number <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="phone_number" value="{{ $customer->lead->phone_number ?? '' }}" placeholder="Phone Number" name="phone_number" autocomplete="off" required>
                        </div>

                        <div class="row">
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="age" class="crm-form-label">Age</label>
                                    <input type="number" class="form-control" id="age" value="{{ old('age', $customer->lead->age ?? '') }}" placeholder="Age (optional)" name="age" min="0">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="date_of_birth" class="crm-form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', optional($customer->lead?->date_of_birth)->format('Y-m-d')) }}">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="gender" class="crm-form-label">Gender</label>
                            <select class="form-control select2" id="gender" name="gender">
                                <option value="">Select Gender</option>
                                @foreach(\App\Enums\Gender::cases() as $gender)
                                    <option value="{{ $gender->value }}" {{ ($customer->lead->gender ?? '') == $gender->value ? 'selected' : '' }}>{{ $gender->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="membership_id" class="crm-form-label">Membership ID</label>
                            <input type="text" class="form-control" id="membership_id" value="{{ $customer->membership_id ?? '' }}" placeholder="Membership ID" name="membership_id" autocomplete="off">
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--gray-200); margin-top: 8px;">
                    <a href="{{ url('admin/customers') }}" class="btn btn-secondary btn-sm" style="margin-right: 8px;">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check mr-1"></i>Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({ width: '100%' });

        $('#date_of_birth').on('change', function () {
            if (!this.value) return;
            const birth = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            const ageEl = document.getElementById('age');
            if (ageEl && (!ageEl.value || ageEl.dataset.autofilled === '1')) {
                ageEl.value = Math.max(0, age);
                ageEl.dataset.autofilled = '1';
            }
        });
    });
</script>
@endpush
