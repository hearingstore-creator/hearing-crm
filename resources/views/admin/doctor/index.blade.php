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
                                <h5>Doctor List</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                @can('permission','doctor.create')
                                <a href="{{ url('admin/doctor/create') }}"
                                   class="btn-primary btn-cs-primary btn-sm ml-2">
                                    <i class="fas fa-plus mr-1"></i>Add Doctor
                                </a>
                                @endcan
                            </div>
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET">
                                    <div class="row">
                                        {{-- Name --}}
                                        <div class="col-md-3">
                                            <input type="text" name="name"
                                                class="form-control"
                                                placeholder="Name"
                                                value="{{ request('name') }}">
                                        </div>

                                        {{-- Contact Number --}}
                                        <div class="col-md-3">
                                            <input type="text" name="contact_number"
                                                class="form-control"
                                                placeholder="Contact Number"
                                                value="{{ request('contact_number') }}">
                                        </div>

                                        {{-- Email --}}
                                        <div class="col-md-3">
                                            <input type="text" name="email"
                                                class="form-control"
                                                placeholder="Email"
                                                value="{{ request('email') }}">
                                        </div>

                                        {{-- Qualification --}}
                                        {{-- <div class="col-md-3">
                                            <input type="text" name="qualification"
                                                class="form-control"
                                                placeholder="Qualification"
                                                value="{{ request('qualification') }}">
                                        </div> --}}
                                    </div>

                                    <div class="row mt-2">
                                        {{-- Status --}}
                                        <div class="col-md-3">
                                            <select class="form-control" name="status">
                                                <option value="">Select Status</option>
                                                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                                                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                                            </select>
                                        </div>

                                        {{-- Start Date --}}
                                        <div class="col-md-3">
                                            <input type="date" name="start_date"
                                                class="form-control"
                                                value="{{ request('start_date') }}">
                                        </div>

                                        {{-- End Date --}}
                                        <div class="col-md-3">
                                            <input type="date" name="end_date"
                                                class="form-control"
                                                value="{{ request('end_date') }}">
                                        </div>

                                        {{-- Buttons --}}
                                        <div class="col-md-3">
                                            <button class="btn btn-info" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <a href="{{ url('admin/doctor') }}" class="btn btn-primary">
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
                                    <th>Name</th>
                                    <th>Contact Number</th>
                                    <th>Email</th>
                                    <th>Qualification</th>
                                    <th>Product Commission %</th>
                                    <th>Product Commission Amount</th>
                                    <th>Service Commission %</th>
                                    <th>Service Commission Amount</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($doctors ?? [] as $key => $item)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $item->contact_number ?? 'N/A' }}</td>
                                    <td>{{ $item->email ?? 'N/A' }}</td>
                                    <td>{{ $item->qualification ?? 'N/A' }}</td>
                                    <td>{{ number_format($item->product_commission_percent, 2) }}%</td>
                                    <td>{{ number_format($item->product_commission_amount, 2) }}</td>
                                    <td>{{ number_format($item->service_commission_percent, 2) }}%</td>
                                    <td>{{ number_format($item->service_commission_amount, 2) }}</td>
                                    <td>
                                        @php
                                            $dueAmount = $item->due_amount;
                                        @endphp
                                        <span class="badge {{ $dueAmount['due'] > 0 ? 'badge-warning' : 'badge-success' }}">
                                            {{ number_format($dueAmount['due'], 2) }}
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            CR: {{ number_format($dueAmount['total_credit'], 2) }} |
                                            DR: {{ number_format($dueAmount['total_debit'], 2) }}
                                        </small>
                                    </td>
                                    <td>
                                        @if($item->status)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->created_at->format('d M Y, h:i A') }}</td>
                                    <td>
                                    <div class="d-flex flex-wrap justify-content-center">
                                        @can('permission','doctor_list.edit')
                                        <a href="{{ url('admin/doctor/'.$item->id.'/edit') }}"
                                           class="btn btn-info btn-sm mb-1 mr-1"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endcan

                                        {{-- @can('permission', 'doctor_list.delete')
                                            <form action="{{ url('admin/doctor', $item->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="confirmDelete(event)">
                                                    <i class="far fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endcan --}}
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
@endsection
