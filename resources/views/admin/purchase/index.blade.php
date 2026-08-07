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
                                <h5>Purchase List</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                @can('permission','add_purchase.create')
                                <a href="{{ url('admin/purchase/create') }}"
                                   class="btn-primary btn-cs-primary btn-sm ml-2">
                                    <i class="fas fa-plus mr-1"></i>Add Purchase
                                </a>
                                @endcan
                            </div>
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET"
                                 data-supplier-search="{{ url('/admin/supplier-search') }}"
                                 >
                                    <div class="row">
                                        {{-- Supplier ID --}}
                                        <div class="col-md-3">
                                            <select class="form-control supplier select" name="supplier_id">
                                                <option value="">Select Supplier</option>
                                                @foreach ($suppliers ?? [] as $supplier)
                                                    <option value="{{ $supplier->id }}"
                                                        {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                                        {{ $supplier->supplier_name }}
                                                    </option>
                                                @endforeach    
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <input type="text" name="purchase_invoice_no"
                                                class="form-control"
                                                placeholder="Invoice No"
                                                value="{{ request('purchase_invoice_no') }}">
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
                                            <a href="{{ url('admin/purchase') }}" class="btn btn-primary">
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
                                    <th>Supplier</th>
                                    <th>Date</th>
                                    <th>Discount</th>
                                    <th>Total Amount</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($purchases ?? [] as $key => $item)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $item->purchase_invoice_no ?? null }}</td>
                                    <td>{{ $item->supplier->supplier_name ?? null }}</td>
                                    <td>{{ $item->date->format('d M Y') }}</td>
                                    <td>{{ number_format($item->discount, 2) }}</td>
                                    <td>{{ number_format($item->total_amount, 2) }}</td>
                                    <td>{{ $item->created_at->format('d M Y, h:i A') }}</td>
                                    <td>
                                    <div class="d-flex flex-wrap justify-content-center">
                                        @can('permission','purchase_list.edit')
                                        <a href="{{ url('admin/purchase/'.$item->id.'/edit') }}"
                                           class="btn btn-info btn-sm mb-1 mr-1"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endcan

                                        @can('permission', 'purchase_list.delete')
                                            <form action="{{ route('purchase.destroy', $item->id) }}" method="POST">
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
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>
@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/purchase.js') }}"></script>
@endpush
@endsection
