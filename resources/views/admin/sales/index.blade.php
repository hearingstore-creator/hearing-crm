@extends('admin.layouts.master')
@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Sales List</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                @can('permission','add_sale.create')
                                <a href="{{ url('admin/sale/create') }}"
                                   class="btn-primary btn-cs-primary btn-sm ml-2">
                                    <i class="fas fa-plus mr-1"></i>Add Sale
                                </a>
                                @endcan
                            </div>
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET"
                                 data-product-search="{{ url('/admin/product-search') }}"
                                 data-customer-search="{{ url('/admin/customer-search') }}"
                                 >
                                    <div class="row">
                                        {{-- Customer ID --}}
                                        <div class="col-md-2">
                                            <select class="form-control customer" name="customer_id">
                                                <option value="">Select</option>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <input type="text" name="sale_invoice_no"
                                                class="form-control"
                                                placeholder="Invoice No"
                                                value="{{ request('sale_invoice_no') }}">
                                        </div>

                                        <div class="col-md-2">
                                            <input type="text" name="serial_no"
                                                class="form-control"
                                                placeholder="Serial No"
                                                value="{{ request('serial_no') }}">
                                        </div>

                                        <div class="col-md-2">
                                            <input type="date" name="start_date"
                                                class="form-control form-control is-valid"
                                                value="{{ request('start_date') }}">
                                        </div>

                                        <div class="col-md-2">
                                            <input type="date" name="end_date"
                                                class="form-control form-control is-valid"
                                                value="{{ request('end_date') }}">
                                        </div>

                                        <div class="col-md-2">
                                            <button class="btn btn-info" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <a href="{{ url('admin/sale') }}" class="btn btn-primary">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                    @include('admin.alert_message')

                    <div class="card-body table-responsive">
                        <table class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>Invoice No</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Total Discount</th>
                                    <th>Total Amount</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($sales ?? [] as $key => $item)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $item->sale_invoice_no ?? null }}</td>
                                    <td>{{ $item->customer->lead->full_name ?? null }}</td>
                                    <td>{{ $item->date->format('d M Y') }}</td>
                                    <td>{{ number_format($item->total_discount, 2) }}</td>
                                    <td>{{ number_format($item->total_amount, 2) }}</td>
                                    <td>{{ number_format($item->paid, 2) }}</td>
                                    <td>{{ number_format($item->total_amount - $item->paid, 2) }}</td>
                                    <td>{{ $item->created_at->format('d M Y, h:i A') }}</td>
                                    <td>
                                    <div class="d-flex flex-wrap justify-content-center">
                                        @can('permission','sale_list.edit')
                                        <a href="{{ url('admin/sale/'.$item->id.'/edit') }}"
                                           class="btn btn-info btn-sm mb-1 mr-1"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endcan

                                        @can('permission','sale_list.view')
                                        <a href="{{ url('admin/sale/'.$item->id) }}"
                                           class="btn btn-success btn-sm mb-1 mr-1"
                                           title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @endcan

                                        {{-- <a href="{{ url('admin/sale-pos-print/'.$item->id) }}"
                                           class="btn btn-secondary btn-sm mb-1 mr-1"
                                           target="_blank"
                                           title="POS Print">
                                            <i class="fas fa-print"></i>
                                        </a> --}}

                                        @can('permission', 'sale_list.delete')
                                            <form action="{{ route('sale.destroy', $item->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="confirmDelete(event)">
                                                    <i class="far fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </tr>
                                @endforeach
                            </tbody>

                        </table>

                        @if(isset($sales) && method_exists($sales, 'links'))
                            <div class="d-flex justify-content-center mt-3">
                                {{ $sales->links() }}
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>
@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/sales.js') }}"></script>
@endpush
@endsection
