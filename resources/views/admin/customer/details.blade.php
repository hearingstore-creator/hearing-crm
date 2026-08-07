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
                                    <h5>Customer Details</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/customers') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-arrow-left mr-1"></i>Back to List
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <!-- Customer Information -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h6 class="text-primary font-weight-bold mb-3">Customer Information</h6>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <tr>
                                                <th width="20%">Customer ID</th>
                                                <td width="30%">{{ $customer->customer_uid }}</td>
                                                <th width="20%">Lead ID</th>
                                                <td width="30%">{{ $customer->lead->lead_uid ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Membership ID</th>
                                                <td>{{ $customer->membership_id ?? 'N/A' }}</td>
                                                <th>Membership Tier</th>
                                                <td>{{ $customer->membershipTier->name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Referral Code</th>
                                                <td>
                                                    @if($customer->referral_code)
                                                        <code class="text-primary">{{ $customer->referral_code }}</code>
                                                    @else
                                                        <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                                <th>Referred By</th>
                                                <td>
                                                    @if($customer->referredBy)
                                                        <a href="{{ route('customers.show', $customer->referredBy->id) }}" class="text-primary">
                                                            {{ $customer->referredBy->lead->full_name }} ({{ $customer->referredBy->customer_uid }})
                                                        </a>
                                                    @else
                                                        <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Full Name</th>
                                                <td>{{ $customer->lead->full_name }}</td>
                                                <th>Phone Number</th>
                                                <td>{{ $customer->lead->phone_number }}</td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td>{{ $customer->lead->email ?? 'N/A' }}</td>
                                                <th>Age</th>
                                                <td>{{ $customer->lead->age }}</td>
                                            </tr>
                                            <tr>
                                                <th>Gender</th>
                                                <td>{{ $customer->lead->gender ?? 'N/A' }}</td>
                                                <th>District</th>
                                                <td>{{ $customer->lead->district->name ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Address</th>
                                                <td colspan="3">{{ $customer->lead->address ?? 'N/A' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Created By</th>
                                                <td>{{ optional($customer->creator)->name ?? 'N/A' }}</td>
                                                <th>Created At</th>
                                                <td>{{ $customer->created_at->format('d M Y, h:i A') }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Sales Information -->
                            <div class="row">
                                <div class="col-md-12">
                                    <h6 class="text-primary font-weight-bold mb-3">Sales History</h6>

                                    @if($saleMasters->count() > 0)
                                        @foreach($saleMasters as $saleMaster)
                                            <div class="card mb-3">
                                                <div class="card-header bg-light">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <strong>Sale Invoice:</strong> {{ $saleMaster->sale_invoice_no }}
                                                            <span class="ml-3">
                                                                <strong>Date:</strong> {{ $saleMaster->date->format('d M Y') }}
                                                            </span>
                                                        </div>
                                                        <div class="col-md-6 text-right">
                                                            <a href="{{ route('sale.show', $saleMaster->id) }}" class="btn btn-info btn-sm" target="_blank">
                                                                <i class="fas fa-eye mr-1"></i>View Details
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-body">

                                                    <!-- Products Section -->
                                                    @if($saleMaster->saleDetails && $saleMaster->saleDetails->count() > 0)
                                                        <h6 class="text-success font-weight-bold mb-2">Products</h6>
                                                        <div class="table-responsive mb-3">
                                                            <table class="table table-sm table-bordered">
                                                                <thead class="thead-light">
                                                                    <tr>
                                                                        <th>Product</th>
                                                                        <th class="text-center">Qty</th>
                                                                        <th class="text-right">Unit Price</th>
                                                                        <th class="text-right">Discount %</th>
                                                                        <th class="text-right">Total</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($saleMaster->saleDetails as $detail)
                                                                        @php
                                                                            $totalPrice = ($detail->qty * $detail->mrp_price) - (($detail->qty * $detail->mrp_price / 100) * $detail->discount);
                                                                        @endphp
                                                                        <tr>
                                                                            <td>
                                                                                {{ $detail->product->category->category_name ?? 'N/A' }}
                                                                                - {{ $detail->product->model ?? 'N/A' }}
                                                                            </td>
                                                                            <td class="text-center">{{ $detail->qty }}</td>
                                                                            <td class="text-right">{{ number_format($detail->mrp_price, 2) }}</td>
                                                                            <td class="text-right">{{ $detail->discount }}</td>
                                                                            <td class="text-right">{{ number_format($totalPrice, 2) }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @endif

                                                    <!-- Services Section -->
                                                    @if($saleMaster->serviceDetails && $saleMaster->serviceDetails->count() > 0)
                                                        <h6 class="text-info font-weight-bold mb-2">Services</h6>
                                                        <div class="table-responsive mb-3">
                                                            <table class="table table-sm table-bordered">
                                                                <thead class="thead-light">
                                                                    <tr>
                                                                        <th>Service Type</th>
                                                                        <th class="text-center">Qty</th>
                                                                        <th class="text-right">Unit Price</th>
                                                                        <th class="text-right">Discount %</th>
                                                                        <th class="text-right">Total</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach($saleMaster->serviceDetails as $service)
                                                                        @php
                                                                            $serviceTotal = ($service->qty * $service->service_price) - (($service->qty * $service->service_price / 100) * ($service->discount ?? 0));
                                                                        @endphp
                                                                        <tr>
                                                                            <td>{{ $service->serviceType->service_type_name ?? 'N/A' }}</td>
                                                                            <td class="text-center">{{ $service->qty }}</td>
                                                                            <td class="text-right">{{ number_format($service->service_price, 2) }}</td>
                                                                            <td class="text-right">{{ $service->discount ?? 0 }}</td>
                                                                            <td class="text-right">{{ number_format($serviceTotal, 2) }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @endif

                                                    <!-- Sale Summary -->
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered">
                                                                    <tr>
                                                                        <td class="text-right"><strong>Sub Total:</strong></td>
                                                                        <td class="text-right">{{ number_format($saleMaster->total_amount + $saleMaster->total_discount - $saleMaster->vat, 2) }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="text-right"><strong>VAT:</strong></td>
                                                                        <td class="text-right">{{ number_format($saleMaster->vat, 2) }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="text-right"><strong>Discount:</strong></td>
                                                                        <td class="text-right">{{ number_format($saleMaster->total_discount, 2) }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="text-right"><strong>Grand Total:</strong></td>
                                                                        <td class="text-right"><strong>{{ number_format($saleMaster->total_amount, 2) }}</strong></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="text-right"><strong>Paid Amount:</strong></td>
                                                                        <td class="text-right text-success">{{ number_format($saleMaster->paid, 2) }}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="text-right"><strong>Due Amount:</strong></td>
                                                                        <td class="text-right text-danger">{{ number_format($saleMaster->total_amount - $saleMaster->paid, 2) }}</td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            No sales found for this customer.
                                        </div>
                                    @endif

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
