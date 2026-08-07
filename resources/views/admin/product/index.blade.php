@extends('admin.layouts.master')

@push('css')
@include('admin.partials.datatables-css')
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
                        <h5>Products List</h5>
                     </div>
                     @can('permission', 'product.create')
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/product/create')}}" class="btn-primary btn-cs-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Product</a>
                     </div>
                     @endcan
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                <table  class="table table-bordered table-striped"
                 data-datatable="products-list">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Battery</th>
                            <th>Usage (Months)</th>
                            <th>Inventory Warranty</th>
                            <th>Sales Warranty</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $key=> $product)
                         <tr>
                            <td>{{++$key}}</td>
                            <td>{{$product->category->category_name ?? 'N/A'}}</td>
                            <td>{{$product->type->type_name ?? 'N/A'}}</td>
                            <td>{{$product->brand->brand_name ?? 'N/A'}}</td>
                            <td>{{$product->model}}</td>
                            <td>{{$product->battery_size ?? 'N/A'}}</td>
                            <td>{{$product->usage_months ? $product->usage_months . ' months' : 'N/A'}} </td>
                            <td>{{$product->warranty_inventory_months}} months</td>
                            <td>{{$product->warranty_sales_months ?? 'N/A'}} months</td>
                            <td>
                                <div class="d-flex flex-wrap justify-content-center">
                                    @can('permission', 'product.edit')
                                    <a href="{{ url('admin/product/'.$product->id.'/edit') }}" class="edit btn btn-success btn-sm mr-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endcan
                                    {{-- @can('permission', 'product.delete')
                                    <form action="{{ url('admin/product/'.$product->id) }}" method="POST">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)">
                                            <i class="far fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    @endcan --}}
                                </div>
                            </td>
                         </tr>
                        @endforeach
                    </tbody>
                </table>
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
@include('admin.partials.datatables-scripts')
@endpush