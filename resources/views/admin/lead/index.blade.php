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
                                    <h5>Leads</h5>
                                </div>
                                    <div class="col-md-6 text-right">
                                        @can('permission', 'lead_list.create')
                                        <a href="{{ route('leads.create') }}" class="btn-primary btn-cs-primary btn-sm">
                                            <i class="fas fa-plus mr-1"></i>Add Lead
                                        </a>
                                         @endcan
                                        @can('permission', 'lead_list.import')
                                        <a href="{{ asset('file/demo_lead.xlsx') }}" download="" class="btn-primary btn-cs-primary btn-sm ml-2">
                                            <i class="fas fa-download mr-1"></i>Download Sample File
                                        </a>
                                        <a href="#" data-toggle="modal" data-target="#importLeads" class="btn-primary btn-cs-primary btn-sm ml-2">
                                            <i class="fas fa-upload mr-1"></i>Import Leads
                                        </a>
                                         @endcan
                                        @can('permission', 'lead_list.export')
                                         <a href="{{url('admin/leads-export')}}" class="btn-primary btn-cs-primary btn-sm ml-2">
                                            <i class="fas fa-download mr-1"></i>Export Leads
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
                                             value="{{ isset($_GET['phone_number'])? $_GET['phone_number']:'' }}">
                                       </div>
                                       <div class="col-md-2">
                                          <input type="text" name="lead_uid" class="form-control form-control is-valid" id="lead_uid" placeholder="Lead ID"
                                             value="{{ isset($_GET['lead_uid'])? $_GET['lead_uid']:'' }}">
                                       </div>
                                       <div class="col-md-2">
                                          <input type="text" name="email" class="form-control form-control is-valid" id="email" placeholder="Email"
                                             value="{{ isset($_GET['email'])? $_GET['email']:'' }}">
                                       </div>
                                       <div class="col-md-2">
                                          <input type="date" name="start_date" onfocus="(this.type='date')" class="form-control form-control is-valid" id="start_date"
                                             value="{{ isset($_GET['start_date'])? $_GET['start_date']:$start_date }}">
                                       </div>
                                       <div class="col-md-2">
                                          <input type="date" name="end_date" class="form-control form-control is-valid" id="end_date"
                                             value="{{ isset($_GET['end_date'])? $_GET['end_date']:$end_date }}">
                                       </div>
                                       <div class="col-md-2">
                                          <select class="form-control select" name="lead_status" id="lead_status">
                                             <option value="">Select Lead Status</option>
                                             @foreach ($leadStatus as $status)
                                                <option {{ isset($_GET['lead_status']) && $_GET['lead_status'] == $status->value ? 'selected':'' }} value="{{ $status->value }}">{{ $status->label() }}</option>
                                             @endforeach
                                          </select>
                                       </div>
                                       <div class="col-md-2 pt-2">
                                          <select class="form-control select" name="source_of_entry" id="source_of_entry">
                                             <option value="">Select Source of Entry</option>
                                             @foreach ($sourceOfEntries as $entry)
                                                <option {{ isset($_GET['source_of_entry']) && $_GET['source_of_entry'] == $entry->value ? 'selected':'' }} value="{{ $entry->value }}">{{ $entry->label() }}</option>
                                             @endforeach
                                          </select>
                                       </div>
                                       <div class="col-md-2 pt-2">
                                          <select class="form-control select" name="vendor_id" id="vendor_id">
                                             <option value="">Select Vendor</option>
                                             @foreach ($vendors as $vendor)
                                                <option {{ isset($_GET['vendor_id']) && $_GET['vendor_id'] == $vendor->id ? 'selected':'' }} value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                             @endforeach
                                          </select>
                                       </div>
                                       <div class="col-md-2 pt-2">
                                          <button class="btn btn-info" title="Search" type="submit"><i class="fas fa-search"></i> </button>
                                          <a href="{{ url('admin/leads') }}" title="Refresh" class="btn btn-primary"><i class="fas fa-sync-alt"></i> </a>
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
                                        <th>Lead ID</th>
                                        <th>Full Name</th>
                                        <th>Phone</th>
                                        <th>Inquiry For</th>
                                        <th>Lead Type</th>
                                        <th>Vendor</th>
                                        <th>Status</th>
                                        <th>Created At</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($leads as $key => $lead)
                                        <tr>
                                            <td>{{ ++$key }}</td>
                                            <td>{{ $lead->lead_uid }}</td>
                                            <td>{{ $lead->full_name }}</td>
                                            <td>{{ $lead->phone_number }}</td>
                                            <td>{{ $lead->inquiry_for_label }}</td>
                                            <td>{{ $lead->lead_type_label }}</td>
                                            <td>{{ optional($lead->vendor)->name }}</td>
                                            <td>
                                                <span class="badge bg-{{ $lead->status_color }}">
                                                    {{ $lead->status_label }}
                                                </span>
                                            </td>
                                            <td>{{ $lead->created_at->format('d M Y, h:i A') }}</td>
                                            <td>
                                                <div class="d-flex flex-wrap justify-content-center">
                                                    @can('permission', 'lead_list.show')
                                                        <a href="{{ route('leads.show', $lead->id) }}"
                                                            class="btn btn-info btn-sm mr-1">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    @endcan
                                                    @if ($lead->lead_status != 'won')
                                                        @can('permission', 'lead_list.edit')
                                                            <a href="{{ route('leads.edit', $lead->id) }}"
                                                                class="btn btn-success btn-sm mr-1">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        @endcan
                                                        @can('permission', 'lead_list.delete')
                                                            <form action="{{ route('leads.destroy', $lead->id) }}" method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger btn-sm"
                                                                    onclick="confirmDelete(event)">
                                                                    <i class="far fa-trash-alt"></i>
                                                                </button>
                                                            </form>
                                                        @endcan
                                                    @endif
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

    <div class="modal fade" id="importLeads" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Leads</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form action="{{ url('admin/leads-import') }}" enctype="multipart/form-data" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Excel File Format:</strong>
                            <ul class="mb-0 pl-3">
                                <li>File types: CSV, XLSX, XLS</li>
                                <li>Max file size: 10MB</li>
                            </ul>
                            <hr class="my-2">
                            <strong class="mt-2 d-block">Required Columns:</strong>
                            <ul class="mb-0 pl-3">
                                <li>full_name - Lead's full name (min 2 characters)</li>
                                <li>phone_number - Phone number (unique, max 20 characters)</li>
                                <li>notes - Notes or remarks (required)</li>
                            </ul>
                            <hr class="my-2">
                            <strong class="mt-2 d-block">Optional Personal Information:</strong>
                            <ul class="mb-0 pl-3">
                                <li>email - Email address (unique, max 20 characters)</li>
                                <li>age - Age (1-150)</li>
                                <li>gender - Gender (male/female/other)</li>
                                <li>district_id - District ID (must exist in database)</li>
                                <li>address - Address (max 1000 characters)</li>
                            </ul>
                            <hr class="my-2">
                            <strong class="mt-2 d-block">Optional Contact Person:</strong>
                            <ul class="mb-0 pl-3">
                                <li>contact_person_name - Contact person name</li>
                                <li>relation - Relationship (e.g., father, mother, spouse)</li>
                                <li>contact_person_phone - Contact person phone</li>
                            </ul>
                            <hr class="my-2">
                            <strong class="mt-2 d-block">Optional Lead Details:</strong>
                            <ul class="mb-0 pl-3">
                                <li>inquiry_for - Inquiry type(s) (comma-separated)</li>
                                <li>lead_status - Lead status</li>
                                <li>interest_brand_product - Brand or product of interest</li>
                                <li>hearing_loss - Hearing loss type(s) (comma-separated)</li>
                                <li>approx_budget - Approximate budget (numeric)</li>
                                <li>ref_by - Referred by</li>
                                <li>loss_to_whom - Loss to whom</li>
                                <li>loss_reason - Reason for loss</li>
                            </ul>
                            <hr class="my-2">
                            <strong class="mt-2 d-block">Optional Source Information:</strong>
                            <ul class="mb-0 pl-3">
                                <li>source_of_entry - Source of entry</li>
                                <li>lead_type - Lead type</li>
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

@push('scripts')
  <script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
@endpush
