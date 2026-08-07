@extends('admin.layouts.master')

@push('css')
    <!-- DataTables -->
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
                                    <h5>Expense List</h5>
                                </div>
                                @can('permission', 'expense.create')
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/expense/create') }}" class="btn-primary btn-cs-primary btn-sm"><i
                                            class="fas fa-plus mr-1"></i>Add Expense</a>
                                </div>
                                @endcan
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <table class="table table-bordered table-striped"
                                data-datatable="expense-list">
                                <thead>
                                    <tr>
                                        <th>SL</th>
                                        <th>Date</th>
                                        <th>Invoice No</th>
                                        <th>Amount</th>
                                        <th>Details</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($expenses as $key => $val)
                                        <tr>
                                            <td>{{ ++$key }}</td>
                                            <td>{{ $val->date }}</td>
                                            <td>{{ $val->invoice_no }}</td>
                                            <td>{{ $val->expense_amount }}</td>
                                            <td>
                                                @if ($val->ref_invoice_no !== null)
                                                    {{ $val->details }} ({{ $val->type }} - invoice no-{{ $val->ref_invoice_no }})
                                                @else
                                                    {{ $val->details }}
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap justify-content-center">
                                                    @can('permission', 'expense.delete')
                                                    @if(!empty($val->income_expense_head_id))
                                                    <form action="{{ route('expense.destroy', $val->id) }}" method="POST">
                                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                                        <input type="hidden" name="_method" value="delete">
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)">
                                                            <i class="far fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                    @endif
                                                    @endcan
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
