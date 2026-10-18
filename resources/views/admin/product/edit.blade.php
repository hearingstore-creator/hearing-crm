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
                                    <h5>Edit Product</h5>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/product/'.$product->id) }}" method="post">
                                @csrf
                                @method('PUT')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="category_id" class="col-sm-3 col-form-label">Category <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control" id="category_id" name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    @foreach($categories as $key => $category)
                                                    <option value="{{$key}}" {{ $product->category_id == $key ? 'selected' : '' }}>{{$category}}</option>
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
                                                <select class="form-control" id="type_id" name="type_id" required>
                                                    <option value="">Select Type</option>
                                                    @foreach($types as $key => $type)
                                                    <option value="{{$key}}" {{ $product->type_id == $key ? 'selected' : '' }}>{{$type}}</option>
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
                                                <select class="form-control" id="brand_id" name="brand_id" required>
                                                    <option value="">Select Brand</option>
                                                    @foreach($brands as $key => $brand)
                                                    <option value="{{$key}}" {{ $product->brand_id == $key ? 'selected' : '' }}>{{$brand}}</option>
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
                                                <input type="text" class="form-control" id="model" placeholder="Enter Model" name="model" value="{{ $product->model }}" autocomplete="off" required>
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
                                                    <option value="{{$size}}" {{ $product->battery_size == $size ? 'selected' : '' }}>{{$size}}</option>
                                                    @endforeach
                                                </select>
                                                @error('battery_size')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="warranty_sales_months" class="col-sm-3 col-form-label">Sales Warranty (Months):</label>
                                            <div class="col-sm-6">
                                                <input type="number" class="form-control" id="warranty_sales_months" placeholder="Enter Sales Warranty Months" name="warranty_sales_months" value="{{ $product->warranty_sales_months ?? '' }}" min="1">
                                                <small class="form-text text-muted">Usually 24 or 12 months based on model and purchase date</small>
                                                @error('warranty_sales_months')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="warranty_inventory_months" class="col-sm-3 col-form-label">Inventory Warranty (Months) <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="number" class="form-control" id="warranty_inventory_months" placeholder="Enter Inventory Warranty Months" name="warranty_inventory_months" value="{{ $product->warranty_inventory_months ?? 27 }}" min="1" required>
                                                <small class="form-text text-muted">Default is 27 months with alert after 25 months</small>
                                                @error('warranty_inventory_months')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="usage_months" class="col-sm-3 col-form-label">Usage Months:</label>
                                            <div class="col-sm-6">
                                                <input type="number" class="form-control" id="usage_months" placeholder="Enter usage months" name="usage_months" value="{{ $product->usage_months ?? '' }}" autocomplete="off">
                                                @error('usage_months')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="reorder_level" class="col-sm-3 col-form-label">Reorder Level:</label>
                                            <div class="col-sm-6">
                                                <input type="number" class="form-control" id="reorder_level" placeholder="e.g. 5" name="reorder_level" value="{{ old('reorder_level', $product->reorder_level) }}" min="0" autocomplete="off">
                                                <small class="form-text text-muted">Dashboard warns when stock falls to this level (blank = 5)</small>
                                                @error('reorder_level')
                                                    <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="service_interval_months" class="col-sm-3 col-form-label">Service Interval (Months):</label>
                                            <div class="col-sm-6">
                                                <input type="number" class="form-control" id="service_interval_months" placeholder="e.g. 6" name="service_interval_months" value="{{ old('service_interval_months', $product->service_interval_months) }}" min="1" max="60" autocomplete="off">
                                                <small class="form-text text-muted">Cleaning / service reminder cycle after sale (blank = 6 for hearing aids)</small>
                                                @error('service_interval_months')
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
                                            <button type="submit" class="btn btn-primary">Update Product</button>
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
