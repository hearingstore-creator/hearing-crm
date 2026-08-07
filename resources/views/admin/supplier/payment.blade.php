@extends('admin.layouts.master')

@push('css')
    <!-- Select2 -->
    <link rel="stylesheet" href="{{ asset("assets/plugins/select2/css/select2.min.css") }}">
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
                                    <h5>Manufacturer Payment</h5>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/manufacturer-payment') }}" enctype="multipart/form-data" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="type_name" class="col-sm-2 col-form-label">
                                                Manufacturer <i class="text-danger">*</i>:
                                            </label>
                                            <div class="col-sm-4">
                                                <select name="manufacturer_id" class="form-control select required" id="manufacturer_id" required>
                                                    <option selected="selected" value="">Select Manufacturer</option>
                                                    @foreach ($manufacturers as $val)
                                                        <option value="{{ $val->id }}">{{ $val->manufacturer_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="type_name" class="col-sm-2 col-form-label">
                                                Type <i class="text-danger">*</i>:
                                            </label>
                                            <div class="col-sm-4">
                                                <select class="form-control" name="type" required>
                                                    <option value="">Select Type</option>
                                                    @foreach ($transactionType as $key => $val)
                                                        <option value="{{ $val }}">{{ $val }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="date" class="col-sm-2 col-form-label">
                                                Date <i class="text-danger">*</i>:
                                            </label>
                                            <div class="col-sm-4">
                                                <input type="date" class="form-control" name="date" value="{{ date('Y-m-d') }}" id="date" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="paid_amount" class="col-sm-2 col-form-label">
                                                Amount <i class="text-danger">*</i>:
                                            </label>
                                            <div class="col-sm-4">
                                                <input type="text" class="form-control" name="paid_amount" id="paid_amount" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="details" class="col-sm-2 col-form-label">
                                                Remarks
                                            </label>
                                            <div class="col-sm-4">
                                                <textarea type="text" class="form-control" name="details" id="details" required></textarea>
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
            <!-- /.container-fluid -->
        </section>
        <!-- /.content -->
@endsection

@push('scripts')
  <!-- Select2 -->
  <script src="{{asset("assets/plugins/select2/js/select2.full.min.js") }}"></script>
@endpush
