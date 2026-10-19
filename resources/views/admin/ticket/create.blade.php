@extends('admin.layouts.master')
@section('page_title', 'New ticket')
@section('breadcrumb_parent', 'Support')
@section('breadcrumb_parent_url', url('admin/ticket'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
    <!-- Main content -->
    <section class="content pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Add Ticket</h5>
                                </div>
                                @can('permission', 'ticket_list.index')
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/ticket') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-align-justify mr-1"></i>Ticket List
                                    </a>
                                </div>
                                @endcan
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/ticket') }}" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="subject" class="col-sm-3 col-form-label">Subject <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="subject" placeholder="Ticket Subject" name="subject" autocomplete="off" required value="{{ old('subject') }}">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="description" class="col-sm-3 col-form-label">Description <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <textarea name="description" id="description" class="form-control" rows="6" maxlength="10000" required placeholder="What did the patient report? When did it start?">{{ old('description') }}</textarea>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="customer_id" class="col-sm-3 col-form-label">Customer <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control patient-search" name="customer_id" id="customer_id" required>
                                                    @if ($selectedCustomer)<option value="{{ $selectedCustomer['id'] }}" selected>{{ $selectedCustomer['text'] }}</option>@endif
                                                </select>
                                                <small class="form-text text-muted">Type a name, phone number or patient ID</small>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="device" class="col-sm-3 col-form-label">Invoice or device:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control" name="device" id="device">
                                                    <option value="">None</option>
                                                    @if ($selectedDevice)<option value="{{ $selectedDevice['value'] }}" selected>{{ $selectedDevice['text'] }}</option>@endif
                                                </select>
                                                <small class="form-text text-muted">Optional. Pick what the patient bought, to link the invoice, serial and warranty.</small>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="assigned_to" class="col-sm-3 col-form-label">Assigned To:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select" name="assigned_to">
                                                    <option value="">Select User</option>
                                                    @foreach ($users ?? [] as $user)
                                                        <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                                            {{ $user->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="status" class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control" name="status" required>
                                                    @foreach ($statuses ?? [] as $status)
                                                        <option value="{{ $status->value }}"
                                                            {{ old('status') == $status->value ? 'selected' : '' }}>
                                                            {{ $status->value }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="priority" class="col-sm-3 col-form-label">Priority <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control" name="priority" required>
                                                    @foreach ($priorities ?? [] as $priority)
                                                        <option value="{{ $priority->value }}"
                                                            {{ old('priority') == $priority->value ? 'selected' : '' }}>
                                                            {{ $priority->value }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <div>
                                            <button type="submit" class="btn btn-success">Save</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select').select2();
            var devicesUrl = @json(route('ticket.devices'));
            function loadDevices(customerId, keep) {
                var $d = $('#device'), chosen = keep ? $d.val() : '';
                $d.html('<option value="">None</option>');
                if (!customerId) { return; }
                $.getJSON(devicesUrl, {customer_id: customerId}).done(function (list) {
                    list.forEach(function (o) { $d.append($('<option>').val(o.value).text(o.text)); });
                    $d.val(chosen || '');
                });
            }
            $('.patient-search').on('select2:select', function (e) { loadDevices(e.params.data.id, false); });
            if ($('.patient-search').val()) { loadDevices($('.patient-search').val(), true); }
            $('.patient-search').select2({
                width: '100%', placeholder: 'Search patient', minimumInputLength: 2,
                ajax: {url: @json(route('ticket.patients')), dataType: 'json', delay: 300, data: function (p) { return {search: p.term}; }, processResults: function (r) { return {results: r}; }}
            });
        });
    </script>
@endpush
@endsection
