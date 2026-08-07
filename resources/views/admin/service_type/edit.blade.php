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
                                    <h5>Edit Service Type</h5>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ route('service-type.update', $serviceType->id) }}" enctype="multipart/form-data" method="post">
                                @csrf
                                @method('put')
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="service_type_name" class="col-sm-3 col-form-label">Service Type Name <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="service_type_name" value="{{ $serviceType->service_type_name }}" placeholder="Service Type Name" name="service_type_name" autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="price" class="col-sm-3 col-form-label">Price:</label>
                                            <div class="col-sm-6">
                                                <input type="number" step="0.01" class="form-control" id="price" value="{{ $serviceType->price ?? '' }}" placeholder="Price" name="price" autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="status" class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-3">
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="1" {{ $serviceType->status == 1 ? 'checked' : '' }} id="status">
                                                    Active
                                                </label>
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="0" {{ $serviceType->status == 0 ? 'checked' : '' }} id="status">
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