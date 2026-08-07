@extends('admin.layouts.master')

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
                                    <h5>Edit Doctor</h5>
                                </div>
                                @can('permission', 'doctor.index')
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/doctor') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-align-justify mr-1"></i>Doctor List
                                    </a>
                                </div>
                                @endcan
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/doctor', $doctor->id) }}" method="post">
                                @csrf
                                @method('put')
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="name" class="col-sm-3 col-form-label">Name <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="name" placeholder="Doctor Name" name="name" autocomplete="off" required value="{{ $doctor->name }}">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="contact_number" class="col-sm-3 col-form-label">Contact Number:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="contact_number" placeholder="Contact Number" name="contact_number" autocomplete="off" value="{{ $doctor->contact_number }}">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="email" class="col-sm-3 col-form-label">Email:</label>
                                            <div class="col-sm-6">
                                                <input type="email" class="form-control" id="email" placeholder="Email" name="email" autocomplete="off" value="{{ $doctor->email }}">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="qualification" class="col-sm-3 col-form-label">Qualification:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="qualification" placeholder="Qualification" name="qualification" autocomplete="off" value="{{ $doctor->qualification }}">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="product_commission_percent" class="col-sm-3 col-form-label">Product Commission %:</label>
                                            <div class="col-sm-6">
                                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="product_commission_percent" placeholder="Product Commission Percentage" name="product_commission_percent" autocomplete="off" required value="{{ $doctor->product_commission_percent }}">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="product_commission_amount" class="col-sm-3 col-form-label">Product Commission Amount:</label>
                                            <div class="col-sm-6">
                                                <input type="number" step="0.01" min="0" class="form-control" id="product_commission_amount" placeholder="Product Commission Amount" name="product_commission_amount" autocomplete="off" required value="{{ $doctor->product_commission_amount }}">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="service_commission_percent" class="col-sm-3 col-form-label">Service Commission %:</label>
                                            <div class="col-sm-6">
                                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="service_commission_percent" placeholder="Service Commission Percentage" name="service_commission_percent" autocomplete="off" required value="{{ $doctor->service_commission_percent }}">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="service_commission_amount" class="col-sm-3 col-form-label">Service Commission Amount:</label>
                                            <div class="col-sm-6">
                                                <input type="number" step="0.01" min="0" class="form-control" id="service_commission_amount" placeholder="Service Commission Amount" name="service_commission_amount" autocomplete="off" required value="{{ $doctor->service_commission_amount }}">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="address" class="col-sm-3 col-form-label">Address:</label>
                                            <div class="col-sm-6">
                                                <textarea class="form-control" id="address" name="address" rows="3" placeholder="Address">{{ $doctor->address }}</textarea>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="status" class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-3">
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="1" {{ $doctor->status == 1 ? 'checked' : '' }} id="status_active">
                                                    Active
                                                </label>
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="0" {{ $doctor->status == 0 ? 'checked' : '' }} id="status_inactive">
                                                    Inactive
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <div>
                                            <button type="submit" class="btn btn-success">Update</button>
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
@endsection
