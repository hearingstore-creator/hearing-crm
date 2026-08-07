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
                                    <h5>Setting </h5>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ route('setting.update', $setting->id) }}" enctype="multipart/form-data"
                                method="post">
                                @csrf
                                @method('put')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="company_name" class="col-sm-4 col-form-label">Company Name <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" id="company_name"
                                                    placeholder="Company Name" name="company_name"
                                                    value="{{ $setting->company_name }}" autocomplete="off" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="phone" class="col-sm-4 col-form-label">Phone: </label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" id="phone"
                                                    placeholder="Phone" name="phone" value="{{ $setting->phone }}">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="address" class="col-sm-4 col-form-label">Address:</label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" id="address" name="address" required>{{ $setting->address }} </textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="mobile_no" class="col-sm-4 col-form-label">Mobile No <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" id="mobile_no"
                                                    placeholder="Mobile No" value="{{ $setting->mobile }}" name="mobile"
                                                    autocomplete="off" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="email" class="col-sm-4 col-form-label">Email:</label>
                                            <div class="col-sm-8">
                                                <input type="email" class="form-control" id="email"
                                                    placeholder="Email" name="email" value="{{ $setting->email }}"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="logo" class="col-sm-4 col-form-label">Logo:</label>
                                            <div class="col-sm-8">
                                                <input type="file" class="form-control" id="logo" placeholder="logo"
                                                    name="logo" {{ empty($setting->logo) ? 'required' : '' }}>
                                                <div class="image pt-2">
                                                    @if (!empty($setting->logo))
                                                        <img src="{{ asset($setting->logo) }}" class="img-rounded"
                                                            width="150">
                                                    @endif
                                                </div>
                                            </div>

                                        </div>
                                        <div class="form-group row">
                                            <label for="favicon" class="col-sm-4 col-form-label">Favicon:</label>
                                            <div class="col-sm-8">
                                                <input type="file" class="form-control" id="favicon"
                                                    placeholder="Favicon" name="favicon">
                                                <div class="image pt-2">
                                                    @if (!empty($setting->favicon))
                                                        <img src="{{ asset($setting->favicon) }}" class="img-rounded"
                                                            width="150">
                                                    @endif
                                                </div>
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
                                                Update</button>
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
