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
                                    <h5>Customer List</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    @can('permission', 'customer_list.import')
                                    <a href="{{ asset('file/demo_customer.xlsx') }}" class="btn-primary btn-cs-primary btn-sm ml-2">
                                        <i class="fas fa-download mr-1"></i>Download Sample File
                                    </a>
                                    <a href="#" data-toggle="modal" data-target="#importCustomers" class="btn-primary btn-cs-primary btn-sm ml-2">
                                        <i class="fas fa-upload mr-1"></i>Import Customers
                                    </a>
                                    @endcan
                                </div>
                            </div>
                             <div class="row pb-3">
                              <div class="col-md-12">
                                 <form method="GET">
                                    <div class="row">
                                       <div class="col-md-2">
                                          <input type="text" name="phone_number" class="form-control form-control is-valid" id="phone_number" placeholder="Phone Number"
                                             value="{{ request('phone_number', '') }}">
                                       </div>
                                       <div class="col-md-2">
                                          <input type="text" name="lead_uid" class="form-control form-control is-valid" id="lead_uid" placeholder="Lead ID"
                                             value="{{ request('lead_uid', '') }}">
                                       </div>
                                       <div class="col-md-2">
                                          <input type="text" name="email" class="form-control form-control is-valid" id="email" placeholder="Email"
                                             value="{{ request('email', '') }}">
                                       </div>
                                       <div class="col-md-2">
                                          <input type="date" name="start_date" onfocus="(this.type='date')" class="form-control form-control is-valid" id="start_date"
                                             value="{{ request('start_date', $start_date) }}">
                                       </div>
                                       <div class="col-md-2">
                                          <input type="date" name="end_date" class="form-control form-control is-valid" id="end_date"
                                             value="{{ request('end_date', $end_date) }}">
                                       </div>
                                       <div class="col-md-2">
                                          <button class="btn btn-info" title="Search" type="submit"><i class="fas fa-search"></i> </button>
                                          <a href="{{ url('admin/customers') }}" title="Refresh" class="btn btn-primary"><i class="fas fa-sync-alt"></i> </a>
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
                                        <th>Customer ID</th>
                                        <th>Lead ID</th>
                                        <th>Full Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Referral Code</th>
                                        <th>Membership ID</th>
                                        <th>Membership Tier</th>
                                        <th>Created At</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($customers as $key => $customer)
                                        <tr>
                                            <td>{{ ++$key }}</td>
                                            <td>{{ $customer->customer_uid }}</td>
                                            <td>{{ $customer->lead->lead_uid }}</td>
                                            <td>{{ $customer->lead->full_name }}</td>
                                            <td>{{ $customer->lead->phone_number }}</td>
                                            <td>{{ $customer->lead->email }}</td>
                                            <td>
                                                @if($customer->referral_code)
                                                    <code class="text-primary">{{ $customer->referral_code }}</code>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $customer->membership_id ?? '-' }}</td>
                                            <td>{{ $customer->membershipTier->name ?? '-' }}</td>
                                            <td>{{ $customer->created_at->format('d M Y, h:i A') }}</td>
                                            <td>
                                                <div class="d-flex flex-wrap justify-content-center">
                                                    @can('permission', 'customer_list.show')
                                                        <a href="{{ route('customers.show', $customer->id) }}"
                                                            class="btn btn-info btn-sm mr-1" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    @endcan
                                                    @can('permission', 'customer_list.edit')
                                                        <a href="{{ route('customers.edit', $customer->id) }}"
                                                            class="btn btn-success btn-sm mr-1" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    @endcan
                                                    {{-- @can('permission', 'customer_list.delete')
                                                        <form action="{{ route('customers.destroy', $customer->id) }}" method="POST">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger btn-sm"
                                                                onclick="confirmDelete(event)">
                                                                <i class="far fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    @endcan --}}
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

    <!-- Import Customers Modal -->
    <div class="modal fade" id="importCustomers" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Customers</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form action="{{ route('customers.import') }}" enctype="multipart/form-data" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Excel File Format:</strong>
                            <ul class="mb-0 pl-3">
                                <li>File types: CSV, XLSX, XLS</li>
                                <li>Max file size: 10MB</li>
                                <li>Required columns: full_name, phone_number, age</li>
                                <li>Optional columns: email, address, gender</li>
                            </ul>
                        </div>
                        <div class="form-group">
                            <label>Excel File <span class="text-danger">*</span></label>
                            <input type="file" name="file" class="form-control" accept=".csv,.xlsx,.xls" required>
                            <small class="form-text text-muted">Supported formats: CSV, XLSX, XLS</small>
                        </div>
                        @error('file')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror
                        @error('rows')
                            <div class="alert alert-danger">
                                <strong>Validation Errors:</strong>
                                <ul class="mb-0 pl-3">
                                    @foreach($errors->get('rows') as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload mr-1"></i>Upload File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('js')
  <script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
@endpush
