
@extends('admin.layouts.master')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
    <!-- Main content -->
    <section class="content manufacturer-ledger pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Manufacturer Ledger</h5>
                                </div>
                            </div>
                            <div class="filter-form pt-2">
                                <form class="form" action="{{ route('manufacturer.ledger') }}" method="post">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="">Manufacturer</label>
                                                <select name="manufacturer_id" class="form-control select required" id="manufacturer_id" required>
                                                    <option selected="selected" value="">Select Manufacturer</option>
                                                    @foreach ($manufacturers as $val)
                                                        <option value="{{ $val->id }}" @isset($manufacturerId) {{ $manufacturerId == $val->id ? 'selected' : '' }} @endisset>{{ $val->manufacturer_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="">From</label>
                                                <input type="date" autocomplete="off" name="start_date" value="{{ $startDate ?? '' }}" class="form-control input-sm" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="">To</label>
                                                <input type="date" autocomplete="off" name="end_date" value="{{ $endDate ?? '' }}" class="form-control input-sm" required>
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
                        <div class="card-body table-responsive">
                            @if (isset($ledgers))
                                <table class="table table-bordered">
                                    <thead>
                                        <tr class="text-center">
                                            <td colspan="7">
                                                <strong>Manufacturer Ledger - {{ $manufacturerName }} (<span class="text-">{{ $startDate }}</span> To <span class="text">{{ $endDate }}</span>)</strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th class="text-center">SL</th>
                                            <th class="text-center">Date</th>
                                            <th class="text-center">Description</th>
                                            <th class="text-right">Debit</th>
                                            <th class="text-right">Credit</th>
                                            <th class="text-right">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $totalDebit = 0;
                                            $totalCredit = 0;
                                        @endphp
                                        @foreach ($ledgers as $key => $val)
                                            <tr>
                                                <td class="text-center">{{ ++$key }}</td>
                                                <td class="text-center">{{ $val->date }}</td>
                                                <td class="text-center">{{ $val->details }}</td>
                                                <td class="text-right">{{ $val->debit }}</td>
                                                <td class="text-right">{{ $val->credit }}</td>
                                                <td class="text-right">{{ number_format($val->debit - $val->credit, 2) }}</td>
                                            </tr>
                                            @php
                                                $totalDebit += $val->debit;
                                                $totalCredit += $val->credit;
                                            @endphp
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-right"><strong>Total</strong></td>
                                            <td class="text-right"><strong>{{ number_format($totalDebit, 2) }}</strong></td>
                                            <td class="text-right"><strong>{{ number_format($totalCredit, 2) }}</strong></td>
                                            <td class="text-right"><strong>{{ number_format($totalDebit - $totalCredit, 2) }}</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
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

@push('scripts')
    <!-- Select2 -->
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
@endpush
