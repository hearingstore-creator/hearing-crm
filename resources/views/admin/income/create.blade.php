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
                                    <h5> Add Income</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/income') }}" class="btn-primary btn-cs-primary  btn-sm"><i
                                            class="fas fa-align-justify mr-1"></i>Income List</a>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/income') }}" enctype="multipart/form-data" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="type_name" class="col-sm-3 col-form-label">Head Type <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control" name="income_expense_head_id" required>
                                                    <option value=""> Select Head Type </option>
                                                    @foreach ($heads as $key => $val)
                                                        <option value="{{ $val->id }}"> {{ $val->head_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="date" class="col-sm-3 col-form-label">Date <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-6">
                                                <input type="date" class="form-control" name="date"
                                                    value="{{ date('Y-m-d') }}" id="date" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="paid_amount" class="col-sm-3 col-form-label">Amount <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" name="paid_amount"
                                                    id="paid_amount" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="details" class="col-sm-3 col-form-label">Remarks </label>
                                            <div class="col-sm-6">
                                                <textarea type="text" class="form-control" name="details" id="details" required> </textarea>
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
                                                Save</button>
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
