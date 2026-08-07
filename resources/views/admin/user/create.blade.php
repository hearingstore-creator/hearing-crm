@extends('admin.layouts.master')
@push('css')
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
    <section class="content pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Add User</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/user') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-list mr-1"></i> User List
                                    </a>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/user') }}" enctype="multipart/form-data" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">

                                        <div class="form-group row">
                                            <label for="name" class="col-sm-2 col-form-label">Name <i
                                                    class="text-danger">*</i>:</label>
                                            <div class="col-sm-4">
                                                <input type="text" class="form-control" name="name" id="name"
                                                    required>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="email" class="col-sm-2 col-form-label">Email <i
                                                    class="text-danger">*</i>:</label>
                                            <div class="col-sm-4">
                                                <input type="email" class="form-control" name="email" id="email"
                                                    required>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="password" class="col-sm-2 col-form-label">Password <i
                                                    class="text-danger">*</i>:</label>
                                            <div class="col-sm-4">
                                                <input type="password" class="form-control" name="password" id="password"
                                                    required>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="roles" class="col-sm-2 col-form-label">Roles <i
                                                    class="text-danger">*</i>:</label>
                                            <div class="col-sm-4">
                                                <select name="roles[]" class="form-control select" id="roles" multiple
                                                    required>
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                                <small class="form-text text-muted">You can assign multiple roles to a
                                                    user.</small>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="details" class="col-sm-2 col-form-label">Remarks</label>
                                            <div class="col-sm-4">
                                                <textarea class="form-control" name="details" id="details"></textarea>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                        <button type="submit" class="btn btn-success">
                                            Save
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
@endpush
