@extends('admin.layouts.master')
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
                                    <h5>Add Product</h5>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/product') }}" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="category_id" class="col-sm-3 col-form-label">Category <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select"  id="category_id" name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    @foreach($categories as $key => $category)
                                                    <option value="{{$key}}">{{$category}}</option>
                                                    @endforeach
                                                </select>
                                                @error('category_id')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="type_id" class="col-sm-3 col-form-label">Type <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select" id="type_id" name="type_id" required>
                                                    <option value="">Select Type</option>
                                                    @foreach($types as $key => $type)
                                                    <option value="{{$key}}">{{$type}}</option>
                                                    @endforeach
                                                </select>
                                                @error('type_id')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="brand_id" class="col-sm-3 col-form-label">Brand <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select" id="brand_id" name="brand_id" required>
                                                    <option value="">Select Brand</option>
                                                    @foreach($brands as $key => $brand)
                                                    <option value="{{$key}}">{{$brand}}</option>
                                                    @endforeach
                                                </select>
                                                @error('brand_id')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="model" class="col-sm-3 col-form-label">Model <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="model" placeholder="Enter Model" name="model" autocomplete="off" required>
                                                @error('model')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="battery_size" class="col-sm-3 col-form-label">Battery Size:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control" id="battery_size" name="battery_size">
                                                    <option value="">Select Battery Size</option>
                                                    @foreach($batterySizes as $size)
                                                    <option value="{{$size}}">{{$size}}</option>
                                                    @endforeach
                                                </select>
                                                @error('battery_size')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="warranty_inventory_months" class="col-sm-3 col-form-label">Inventory Warranty (Months) <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="number" class="form-control" id="warranty_inventory_months" placeholder="Enter Inventory Warranty Months" name="warranty_inventory_months" value="27" min="1" required>
                                                <small class="form-text text-muted">Default is 27 months with alert after 25 months</small>
                                                @error('warranty_inventory_months')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="warranty_sales_months" class="col-sm-3 col-form-label">Sales Warranty (Months):</label>
                                            <div class="col-sm-6">
                                                <input type="number" class="form-control" id="warranty_sales_months" placeholder="Enter Sales Warranty Months" name="warranty_sales_months" min="1">
                                                <small class="form-text text-muted">Usually 24 or 12 months based on model and purchase date</small>
                                                @error('warranty_sales_months')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="usage_months" class="col-sm-3 col-form-label">Usage Months:</label>
                                            <div class="col-sm-6">
                                                <input type="number" class="form-control" id="usage_months" placeholder="Enter usage months" name="usage_months" autocomplete="off">
                                                @error('usage_months')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <div>
                                            <button type="submit" class="btn btn-primary">Save Product</button>
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

@push('scripts')
  <!-- Select2 -->
  <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
  <script>
    $(document).ready(function() {
      $('.select').select2({
        width: '100%'
      });
    });
  </script>
@endpush
