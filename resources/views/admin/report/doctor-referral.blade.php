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
                                    <h5>Doctor & Referral Report</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown">
                                            <i class="fas fa-file-excel"></i> Export Excel
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('report.doctor-referral.export', ['type' => 'full', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
                                                <i class="fas fa-file-excel text-success"></i> Table Report
                                            </a>
                                            <a class="dropdown-item" href="{{ route('report.doctor-referral.export', ['type' => 'summary', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
                                                <i class="fas fa-file-excel text-primary"></i> Summary Report
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="filter-form pt-2">
                                <form class="form" action="{{ route('report.doctor-referral') }}" method="get">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">Doctor</label>
                                                <select name="doctor_id" class="form-control select" id="doctor_id">
                                                    <option value="">All Doctors</option>
                                                    @foreach ($doctors ?? [] as $doctor)
                                                        <option value="{{ $doctor->id }}"
                                                            @isset($doctorId) {{ $doctorId == $doctor->id ? 'selected' : '' }} @endisset>
                                                            {{ $doctor->name }} ({{ $doctor->qualification ?? 'N/A' }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">Branch</label>
                                                <select name="vendor_id" class="form-control select" id="vendor_id">
                                                    <option value="">All Branches</option>
                                                    @foreach ($vendors ?? [] as $vendor)
                                                        <option value="{{ $vendor->id }}"
                                                            @isset($vendorId) {{ $vendorId == $vendor->id ? 'selected' : '' }} @endisset>
                                                            {{ $vendor->supplier_name ?? $vendor->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">Payment Status</label>
                                                <select name="payment_status" class="form-control select" id="payment_status">
                                                    <option value="all" @isset($paymentStatus) {{ $paymentStatus == 'all' ? 'selected' : '' }} @endisset>All Status</option>
                                                    <option value="paid" @isset($paymentStatus) {{ $paymentStatus == 'paid' ? 'selected' : '' }} @endisset>Paid</option>
                                                    <option value="pending" @isset($paymentStatus) {{ $paymentStatus == 'pending' ? 'selected' : '' }} @endisset>Pending</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">From</label>
                                                <input type="date" autocomplete="off" name="start_date"
                                                    value="{{ $startDate ?? '' }}" class="form-control input-sm">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">To</label>
                                                <input type="date" autocomplete="OFF" name="end_date"
                                                    value="{{ $endDate ?? '' }}" class="form-control input-sm">
                                            </div>
                                        </div>
                                        <div class="col-md-2 submit">
                                            <div class="form-group">
                                                <input type="submit" class="btn btn-success btn-sm" value="Submit">
                                                <a href="{{ url('admin/report/doctor-referral') }}" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-sync-alt"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body" id="print-area">
                            <div class="text-center pb-2">
                                <h3>Doctor & Referral Report</h3>
                                @if(isset($startDate) && isset($endDate))
                                    <p class="text-muted">
                                        <strong>Date Range:</strong> {{ $startDate }} To {{ $endDate }}
                                        @if(isset($doctorId))
                                            @php $selectedDoctor = $doctors->firstWhere('id', $doctorId); @endphp
                                            @if($selectedDoctor)
                                                | <strong>Doctor:</strong> {{ $selectedDoctor->name }}
                                            @endif
                                        @endif
                                        @if(isset($vendorId))
                                            @php $selectedVendor = $vendors->firstWhere('id', $vendorId); @endphp
                                            @if($selectedVendor)
                                                | <strong>Branch:</strong> {{ $selectedVendor->supplier_name ?? $selectedVendor->name }}
                                            @endif
                                        @endif
                                        @if(isset($paymentStatus) && $paymentStatus != 'all')
                                            | <strong>Payment Status:</strong> {{ ucfirst($paymentStatus) }}
                                        @endif
                                    </p>
                                @endif
                            </div>

                            @php
                                $totalSalesValue = 0;
                                $totalCommissionAmount = 0;
                                $totalPaid = 0;
                                $totalPending = 0;
                            @endphp

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th class="text-center">SL</th>
                                            <th class="text-center">Doctor</th>
                                            <th class="text-center">Qualification</th>
                                            <th class="text-center">Sales Value</th>
                                            <th class="text-center">Commission %</th>
                                            <th class="text-center">Commission Amount</th>
                                            <th class="text-center">Paid</th>
                                            <th class="text-center">Pending</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($reportData) && count($reportData) > 0)
                                            @foreach ($reportData as $key => $row)
                                                <tr>
                                                    <td class="text-center">{{ ++$key }}</td>
                                                    <td>{{ $row['doctor_name'] }}</td>
                                                    <td>{{ $row['qualification'] }}</td>
                                                    <td class="text-right">{{ number_format($row['sales_value'], 2) }}</td>
                                                    <td class="text-center">{{ $row['commission_percent'] }}%</td>
                                                    <td class="text-right">{{ number_format($row['commission_amount'], 2) }}</td>
                                                    <td class="text-right">
                                                        <span class="text-success">{{ number_format($row['paid'], 2) }}</span>
                                                    </td>
                                                    <td class="text-right">
                                                        <span class="text-{{ $row['pending'] > 0 ? 'danger' : 'muted' }}">
                                                            {{ number_format($row['pending'], 2) }}
                                                        </span>
                                                    </td>
                                                </tr>

                                                @php
                                                    $totalSalesValue += $row['sales_value'];
                                                    $totalCommissionAmount += $row['commission_amount'];
                                                    $totalPaid += $row['paid'];
                                                    $totalPending += $row['pending'];
                                                @endphp
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="8" class="text-center">No data found</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    @if(isset($reportData) && count($reportData) > 0)
                                    <tfoot>
                                        <tr class="font-weight-bold">
                                            <td colspan="3" class="text-right">Total:</td>
                                            <td class="text-right">{{ number_format($totalSalesValue, 2) }}</td>
                                            <td></td>
                                            <td class="text-right">{{ number_format($totalCommissionAmount, 2) }}</td>
                                            <td class="text-right">{{ number_format($totalPaid, 2) }}</td>
                                            <td class="text-right">{{ number_format($totalPending, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>

                            @if(isset($reportData) && count($reportData) > 0)
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Summary</h6>
                                        <ul class="mb-0 pl-3">
                                            <li><strong>Total Doctors:</strong> {{ count($reportData) }}</li>
                                            <li><strong>Total Sales Value:</strong> {{ number_format($totalSalesValue, 2) }}</li>
                                            <li><strong>Total Commission:</strong> {{ number_format($totalCommissionAmount, 2) }}</li>
                                            <li><strong>Total Paid:</strong> {{ number_format($totalPaid, 2) }}</li>
                                            <li><strong>Total Pending:</strong> {{ number_format($totalPending, 2) }}</li>
                                            <li><strong>Average Commission %:</strong> {{ $totalSalesValue > 0 ? round(($totalCommissionAmount / $totalSalesValue) * 100, 2) : 0 }}%</li>
                                        </ul>
                                    </div>
                                </div>
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

@push('scripts')
    <!-- Select2 -->
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select').select2({
                placeholder: 'Select an option',
                allowClear: true
            });
        });
    </script>
@endpush
