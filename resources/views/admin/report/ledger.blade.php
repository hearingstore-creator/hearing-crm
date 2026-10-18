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
                                    <h5>Ledger</h5>
                                </div>
                            </div>
                            <div class="filter-form pt-2">
                                <form class="form" action="{{ route('report.ledger') }}" method="post">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">Type</label>
                                                <select name="type" class="form-control select required" id="type"
                                                    required>
                                                    <option selected="selected" value="">Select Type</option>
                                                    @foreach ($ledgerFilterTypes as $item)
                                                        <option value="{{ $item }}"
                                                            @isset($type) {{ $type == $item ? 'selected' : '' }} @endisset>
                                                            {{ $item }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
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
                                        @if(!empty($branches))
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="branch">Branch</label>
                                                <select name="branch" id="branch" class="form-control">
                                                    <option value="all">All branches</option>
                                                    <option value="hq" @selected(($branch ?? '') === 'hq')>Head Office</option>
                                                    @foreach ($branches as $branchId => $branchName)
                                                        <option value="{{ $branchId }}" @selected((string) ($branch ?? '') === (string) $branchId)>{{ $branchName }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        @endif
                                        <div class="col-md-2 submit">
                                            <div class="form-group">
                                                <input type="submit" class="btn btn-success btn-sm" value="Submit">
                                            </div>
                                        </div>
                                        <div class="col-md-2 submit">
                                            <div class="form-group">
                                                <a href="#" class="btn-success btn-sm print-btn"><i
                                                        class="fas fa-align-justify mr-1 print"></i>Print Invoice</a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body" id="print-area">
                            <div class="text-center pb-2">
                                <h3>Ledger</h3>
                            </div>
                            @php
                                $totalExpense = 0;
                                $totalIncome = 0;
                            @endphp
                            <div class="row">
                                <div class="col-sm-6 table-responsive">
                                    @if (isset($incomes))
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr class="text-center">
                                                    <td colspan="7"> <strong> Incomes - ( <span
                                                                class="text-">{{ $startDate }}</span> To <span
                                                                class="text">
                                                                {{ $endDate }}</span>)</strong><strong></strong>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="text-center">Invoice No</th>
                                                    <th class="text-center">Date</th>
                                                    <th class="text-center">Description</th>
                                                    <th class="text-right">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($incomes as $key => $val)
                                                    <tr>
                                                        <td class="text-center">{{ $val->invoice_no }}</td>
                                                        <td class="text-right">{{ $val->date }}</td>
                                                        <td class="text-center">{{ $val->details }}
                                                            {{ $val->ref_invoice_no != null ? '(' . $val->type . '-' . 'invoice no-' . $val->ref_invoice_no . ')' : '' }}
                                                        </td>
                                                        <td class="text-right">{{ $val->income_amount }}</td>
                                                    </tr>

                                                    @php
                                                        $totalIncome += $val->income_amount;
                                                    @endphp
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>
                                <div class="col-sm-6 table-responsive">
                                    @if (isset($expenses))
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr class="text-center">
                                                    <td colspan="7"> <strong> Expenses - ( <span
                                                                class="text-">{{ $startDate }}</span> To <span
                                                                class="text">
                                                                {{ $endDate }}</span>)</strong><strong></strong>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="text-center">Invoice No</th>
                                                    <th class="text-center">Date</th>
                                                    <th class="text-center">Description</th>
                                                    <th class="text-right">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>

                                                @foreach ($expenses as $key => $val)
                                                    <tr>
                                                        <td class="text-center">{{ $val->invoice_no }}</td>
                                                        <td class="text-right">{{ $val->date }}</td>
                                                        <td class="text-center">{{ $val->details }}
                                                            {{ $val->ref_invoice_no != null ? '(' . $val->type . '-' . 'invoice no-' . $val->ref_invoice_no . ')' : '' }}
                                                        </td>
                                                        <td class="text-right">{{ $val->expense_amount }}</td>
                                                    </tr>

                                                    @php
                                                        $totalExpense += $val->expense_amount;
                                                    @endphp
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>
                            </div>
                            <div class="row">
                                <table class="table table-bordered table-striped">
                                    <tbody>
                                        <tr>
                                            <th colspan="2" class="text-center"> Total Income </th>
                                            <th colspan="2" class="text-center">{{ $totalIncome }}</th>
                                        </tr>
                                        <tr>
                                            <th colspan="2" class="text-center"> Total Expense </th>
                                            <th colspan="2" class="text-center">{{ $totalExpense }}</th>
                                        </tr>
                                        <tr>
                                            <th colspan="2" class="text-center"> Account Balance </th>
                                            <th colspan="2" class="text-center">{{ $totalIncome - $totalExpense }}</th>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
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
@endpush
