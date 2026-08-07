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
                                    <h5> Add Designation</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/designation') }}" class="btn-primary btn-cs-primary  btn-sm"><i
                                            class="fas fa-align-justify mr-1"></i>Designation List</a>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/designation') }}" enctype="multipart/form-data" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="designation_name" class="col-sm-3 col-form-label">Designation Name
                                                <i class="text-danger"> * </i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="designation_name"
                                                    placeholder="Designation Name" name="designation_name"
                                                    autocomplete="off" required>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="details" class="col-sm-3 col-form-label">Details </i>:</label>
                                            <div class="col-sm-6">
                                                <textarea name="details" class="form-control" id="details"></textarea>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="status" class="col-sm-3 col-form-label">status <i
                                                    class="text-danger">*</i>:</label>
                                            <div class="col-sm-3">
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="1" checked="checked"
                                                        id="status">
                                                    Active
                                                </label>
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="0" id="status">
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
                                        <div class="">
                                            <button type="submit" class="btn btn-success">
                                                Save</button>
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
