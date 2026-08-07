
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
                                    <h5>Edit Leaf Settings</h5>
                                </div>
                                @can('permission', 'leaf_settings.index')
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/leaf-settings') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-align-justify mr-1"></i>Leaf Settings List
                                    </a>
                                </div>
                                @endcan
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ route('leaf-settings.update', $leafSettings->id) }}" enctype="multipart/form-data" method="post">
                                @csrf
                                @method('put')
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="leaf_type" class="col-sm-3 col-form-label">Leaf Type <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="leaf_type" value="{{ $leafSettings->leaf_type }}" placeholder="Leaf Type" name="leaf_type" autocomplete="off" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="total_number_per_box" class="col-sm-3 col-form-label">Total Number Per Box <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="total_number_per_box" value="{{ $leafSettings->total_number_per_box }}" placeholder="Total Number Per Box" name="total_number_per_box" autocomplete="off" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="status" class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-3">
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="1" {{ $leafSettings->status == 1 ? 'checked' : '' }} id="status">
                                                    Active
                                                </label>
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="0" {{ $leafSettings->status == 0 ? 'checked' : '' }} id="status">
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
