@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Suppliers Payment List</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                @can('permission','supplier_payment.create')
                                <a href="{{ url('admin/suppliers-payment/create') }}"
                                   class="btn-primary btn-cs-primary btn-sm ml-2">
                                    <i class="fas fa-plus mr-1"></i>Add Payment
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    @include('admin.alert_message')
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>SL#</th>
                                        <th>Date</th>
                                        <th>Supplier Name</th>
                                        <th>Payment Amount</th>
                                        <th>Details</th>
                                        <th>Reference No</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $i = 1; @endphp
                                    @foreach($payments as $payment)
                                    <tr>
                                        <td>{{ $i++ }}</td>
                                        <td>{{ $payment->date ? $payment->date->format('d-m-Y') : '' }}</td>
                                        <td>{{ $payment->supplier->supplier_name ?? '' }}</td>
                                        <td>{{ number_format($payment->debit, 2) }}</td>
                                        <td>{{ $payment->details ?? '-' }}</td>
                                        <td>{{ $payment->ref_invoice_no ?? '-' }}</td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center">
                                                @can('permission','supplier_payment.edit')
                                                <a href="{{ route('suppliers.payment.edit', $payment->id) }}"
                                                   class="btn btn-success btn-sm mr-1"
                                                   title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @endcan

                                                @can('permission','supplier_payment.delete')
                                                <form action="{{ route('suppliers.payment.destroy', $payment->id) }}"
                                                      method="POST"
                                                      onsubmit="return confirmDelete(event)"
                                                      style="display: inline-block;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-danger btn-sm"
                                                            title="Delete">
                                                        <i class="far fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($payments->hasPages())
                        <div class="row mt-3">
                            <div class="col-md-12">
                                {{ $payments->links() }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
