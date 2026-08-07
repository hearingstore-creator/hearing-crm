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
                  <h5>Add Customer</h5>
                </div>
                @can('permission', 'customer_list.index')
                <div class="col-md-6 text-right">
                  <a href="{{ url('admin/customers') }}" class="btn-primary btn-cs-primary btn-sm">
                    <i class="fas fa-align-justify mr-1"></i>Customer List
                  </a>
                </div>
                @endcan
              </div>
            </div>

            @include('admin.alert_message')

            <div class="card-body">
              <form action="{{ url('admin/customers') }}" enctype="multipart/form-data" method="post">
                @csrf
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group row">
                      <label for="full_name" class="col-sm-3 col-form-label">Full Name <i class="text-danger">*</i>:</label>
                      <div class="col-sm-9">
                        <input type="text" class="form-control" id="full_name" placeholder="Full Name" name="full_name" autocomplete="off" required>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label for="email" class="col-sm-3 col-form-label">Email:</label>
                      <div class="col-sm-9">
                        <input type="email" class="form-control" id="email" placeholder="Email" name="email">
                      </div>
                    </div>

                    <div class="form-group row">
                      <label for="district_id" class="col-sm-3 col-form-label">District:</label>
                      <div class="col-sm-9">
                        <select class="form-control select2" id="district_id" name="district_id">
                          <option value="">Select District</option>
                          @if(isset($districts))
                            @foreach($districts as $district)
                              <option value="{{ $district->id }}">{{ $district->name }}</option>
                            @endforeach
                          @endif
                        </select>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label for="address" class="col-sm-3 col-form-label">Address:</label>
                      <div class="col-sm-9">
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group row">
                      <label for="phone_number" class="col-sm-3 col-form-label">Phone Number <i class="text-danger">*</i>:</label>
                      <div class="col-sm-9">
                        <input type="text" class="form-control" id="phone_number" placeholder="Phone Number" name="phone_number" autocomplete="off" required>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label for="age" class="col-sm-3 col-form-label">Age <i class="text-danger">*</i>:</label>
                      <div class="col-sm-9">
                        <input type="number" class="form-control" id="age" placeholder="Age" name="age" min="0" required>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label for="gender" class="col-sm-3 col-form-label">Gender:</label>
                      <div class="col-sm-9">
                        <select class="form-control select2" id="gender" name="gender">
                          <option value="">Select Gender</option>
                          @foreach(\App\Enums\Gender::cases() as $gender)
                            <option value="{{ $gender->value }}">{{ $gender->label() }}</option>
                          @endforeach
                        </select>
                      </div>
                    </div>

                    <div class="form-group row">
                      <label for="membership_id" class="col-sm-3 col-form-label">Membership ID:</label>
                      <div class="col-sm-9">
                        <input type="text" class="form-control" id="membership_id" placeholder="Membership ID" name="membership_id" autocomplete="off">
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-group row">
                  <div class="col-md-6 text-right">
                  </div>
                  <div class="col-md-6 text-right">
                    <button type="submit" class="btn btn-success">Save</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- /.container-fluid -->
  </section>
  <!-- /.content -->
@endsection
