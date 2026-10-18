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
                                    <h5> Sale Report</h5>
                                </div>
                            </div>
                            <div class="filter-form pt-2">
                                <form class="form" action="{{ route('sale.report') }}" method="post">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="">From</label>
                                                <input type="date" autocomplete="off" name="start_date"
                                                    value="{{ $startDate ?? '' }}" class="form-control input-sm"
                                                    required="">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="">To</label>
                                                <input type="date" autocomplete="OFF" name="end_date"
                                                    value="{{ $endDate ?? '' }}" class="form-control input-sm"
                                                    required="">
                                            </div>
                                        </div>
                                        <div class="col-md-2 submit">
                                            <div class="form-group">
                                                <input type="submit" class="btn btn-success btn-sm" value="Submit">
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body" id="print-area">
                            @if (isset($saleMaster))
                                <h2 class="text-center pt-3 title report-title"> {{ $setting->company_name }}</h2>
                                <h3 class="text-center pt-1 title report-title"> Sale Report</h3>
                                <h5 class="text-center title pb-2 report-title"><b>Date:</b> {{ date('F jS, Y') }}</h5>
                                <div class="text-right pb-2">
                                    <a href="#" class="btn-success btn-sm print-btn"><i
                                            class="fas fa-align-justify mr-1 print"></i>Print</a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th class="text-center">SL</th>
                                                <th class="text-center">Sale Invoice No</th>
                                                <th class="text-center">Customer Name</th>
                                                <th class="text-center">Date</th>
                                                <th class="text-center">Description</th>
                                                <th class="text-right">Total</th>
                                                <th class="text-right">Paid</th>
                                                <th class="text-right">Due</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $totalAmount = 0;
                                                $totalPaid = 0;
                                                $totalDue = 0;
                                            @endphp

                                            @foreach ($saleMaster as $key => $val)
                                                <tr>
                                                    <td class="text-center">{{ ++$key }}</td>
                                                    <td class="text-center">{{ $val->sale_invoice_no }}</td>
                                                    <td class="text-center">{{ $val->customer?->name ?? 'N/A' }}</td>
                                                    <td class="text-center">{{ $val->date }}</td>
                                                    <td class="text-center">{{ $val->details }}</td>
                                                    <td class="text-right">{{ $val->total_amount }}</td>
                                                    <td class="text-right">{{ $val->paid }}</td>
                                                    <td class="text-right">
                                                        {{ number_format($val->total_amount - $val->paid, 2) }}</td>
                                                </tr>

                                                @php
                                                    $totalAmount += $val->total_amount;
                                                    $totalPaid += $val->paid;
                                                    $totalDue += $val->total_amount - $val->paid;

                                                @endphp
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5" class="text-right"><strong>Total</strong></td>
                                                <td class="text-right">
                                                    <strong>{{ number_format($totalAmount, 2) }}</strong></td>
                                                <td class="text-right"><strong>{{ number_format($totalPaid, 2) }}</strong>
                                                </td>
                                                <td class="text-right"><strong>{{ number_format($totalDue, 2) }}</strong>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
@endsection
