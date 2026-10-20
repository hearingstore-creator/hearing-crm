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
                                    <h5>Setting </h5>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ route('setting.update', $setting->id) }}" enctype="multipart/form-data"
                                method="post">
                                @csrf
                                @method('put')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="company_name" class="col-sm-4 col-form-label">Company Name <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" id="company_name"
                                                    placeholder="Company Name" name="company_name"
                                                    value="{{ $setting->company_name }}" autocomplete="off" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="return_window_days" class="col-sm-4 col-form-label">Return Window (days):</label>
                                            <div class="col-sm-8">
                                                <input type="number" min="0" max="365" class="form-control" id="return_window_days"
                                                    name="return_window_days" value="{{ $setting->return_window_days ?? 7 }}">
                                                <small class="text-muted">Devices are sold as non-returnable. A return or exchange inside this many days of delivery is allowed; later ones need manager approval. 0 = approval always needed.</small>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="return_charge_percent" class="col-sm-4 col-form-label">Return Charge (%):</label>
                                            <div class="col-sm-8">
                                                <input type="number" min="0" max="100" step="0.01" class="form-control" id="return_charge_percent"
                                                    name="return_charge_percent" value="{{ $setting->return_charge_percent ?? 10 }}">
                                                <small class="text-muted">Minimum charge kept from the returned amount. Only a manager can waive it.</small>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="website_url" class="col-sm-4 col-form-label">Website Address:</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" id="website_url"
                                                    placeholder="e.g. www.hearingstore.bd" name="website_url"
                                                    value="{{ $setting->website_url ?? 'www.hearingstore.bd' }}">
                                                <small class="text-muted">Displayed on all branch invoices and credit notes</small>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="partner_slogan" class="col-sm-4 col-form-label">Partner Tagline:</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" id="partner_slogan"
                                                    placeholder="e.g. An Authorized Partner of Hearing Store" name="partner_slogan"
                                                    value="{{ $setting->partner_slogan ?? 'An Authorized Partner of Hearing Store' }}">
                                                <small class="text-muted">Endorsement badge shown alongside branch letterhead</small>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="phone" class="col-sm-4 col-form-label">Phone / Hotline: </label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" id="phone"
                                                    placeholder="Phone" name="phone" value="{{ $setting->phone }}">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="address" class="col-sm-4 col-form-label">Address:</label>
                                            <div class="col-sm-8">
                                                <textarea class="form-control" id="address" name="address" required>{{ $setting->address }} </textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="mobile_no" class="col-sm-4 col-form-label">Mobile No <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-8">
                                                <input type="text" class="form-control" id="mobile_no"
                                                    placeholder="Mobile No" value="{{ $setting->mobile }}" name="mobile"
                                                    autocomplete="off" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="email" class="col-sm-4 col-form-label">Email:</label>
                                            <div class="col-sm-8">
                                                <input type="email" class="form-control" id="email"
                                                    placeholder="Email" name="email" value="{{ $setting->email }}"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="logo" class="col-sm-4 col-form-label">Logo:</label>
                                            <div class="col-sm-8">
                                                <input type="file" class="form-control" id="logo" placeholder="logo"
                                                    name="logo" {{ empty($setting->logo) ? 'required' : '' }}>
                                                <div class="image pt-2">
                                                    @if (!empty($setting->logo))
                                                        <img src="{{ asset($setting->logo) }}" class="img-rounded"
                                                            width="150">
                                                    @endif
                                                </div>
                                            </div>

                                        </div>
                                        <div class="form-group row">
                                            <label for="favicon" class="col-sm-4 col-form-label">Favicon:</label>
                                            <div class="col-sm-8">
                                                <input type="file" class="form-control" id="favicon"
                                                    placeholder="Favicon" name="favicon">
                                                <div class="image pt-2">
                                                    @if (!empty($setting->favicon))
                                                        <img src="{{ asset($setting->favicon) }}" class="img-rounded"
                                                            width="150">
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <div class="">
                                            <button type="submit" class="btn btn-success">
                                                Update</button>
                                        </div>
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
