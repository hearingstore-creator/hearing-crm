@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6"><h5>Add Appointment Service</h5></div>
                            @can('permission', 'service_list.index')
                            <div class="col-md-6 text-right">
                                <a href="{{ url('admin/appointment-services') }}" class="btn-primary btn-cs-primary btn-sm">
                                    <i class="fas fa-list mr-1"></i> Service List
                                </a>
                            </div>
                            @endcan
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body">
                        <form action="{{ url('admin/appointment-services') }}" method="post">
                            @csrf
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Service Name <i class="text-danger">*</i>:</label>
                                        <div class="col-sm-6">
                                            <input type="text" class="form-control" name="name" placeholder="e.g. Hearing Test" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Description:</label>
                                        <div class="col-sm-6">
                                            <textarea class="form-control" name="description" rows="2" placeholder="Brief description of the service"></textarea>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Category <i class="text-danger">*</i>:</label>
                                        <div class="col-sm-6">
                                            <select class="form-control" name="category" required>
                                                <option value="">Select Category</option>
                                                @foreach ($serviceCategories as $category)
                                                    <option value="{{ $category->value }}">{{ $category->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Max Per Day:</label>
                                        <div class="col-sm-6">
                                            <input type="number" class="form-control" name="max_per_day" value="10" min="1" max="100">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                                        <div class="col-sm-3">
                                            <label class="radio-inline my-2">
                                                <input type="radio" name="status" value="1" checked> Active
                                            </label>
                                            <label class="radio-inline my-2">
                                                <input type="radio" name="status" value="0"> Inactive
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-6 text-right">
                                    <button type="submit" class="btn btn-success">Save</button>
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
