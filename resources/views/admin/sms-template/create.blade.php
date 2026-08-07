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
                        <h5><i class="fas fa-plus mr-2"></i>Add SMS Template</h5>
                     </div>
                     @can('permission', 'templates.index')
                     <div class="col-md-6 text-right">
                        <a href="{{ url('admin/sms-templates') }}" class="btn-primary btn-cs-primary btn-sm">
                           <i class="fas fa-align-justify mr-1"></i>Template List
                        </a>
                     </div>
                     @endcan
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                  <form action="{{ url('admin/sms-templates') }}" method="post">
                     @csrf
                     <div class="row">
                        <div class="col-md-12">
                           <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Template Name <i class="text-danger">*</i>:</label>
                              <div class="col-sm-6">
                                 <input type="text" class="form-control" name="name" placeholder="e.g., Warranty Expiry Reminder" required>
                              </div>
                           </div>
                           <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Category <i class="text-danger">*</i>:</label>
                              <div class="col-sm-6">
                                 <select name="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                    <option value="{{ $category->value }}">{{ $category->label() }}</option>
                                    @endforeach
                                 </select>
                              </div>
                           </div>
                           <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Message Body <i class="text-danger">*</i>:</label>
                              <div class="col-sm-6">
                                 <textarea name="body" class="form-control" rows="5"
                                    placeholder="{!! 'Type your template with {{variable}} placeholders.\ne.g., Dear {{name}}, your warranty expires on {{warranty_date}}.' !!}"
                                    required maxlength="1000"></textarea>
                                 <small class="text-muted">Use {!! '{name}' !!}, {!! '{phone}' !!}, {!! '{customer_id}' !!}, {!! '{tier}' !!}, {!! '{points}' !!}, {!! '{email}' !!} as variables.</small>
                              </div>
                           </div>
                           <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                              <div class="col-sm-3">
                                 <label class="radio-inline my-2">
                                    <input type="radio" name="is_active" value="1" checked> Active
                                 </label>
                                 <label class="radio-inline my-2">
                                    <input type="radio" name="is_active" value="0"> Inactive
                                 </label>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <div class="col-md-6 text-right"></div>
                        <div class="col-md-6 text-right">
                           <button type="submit" class="btn btn-success">
                              <i class="fas fa-save mr-1"></i> Save
                           </button>
                        </div>
                     </div>
                  </form>
               </div>
            </div>
         </div>
      </div>
   </div>
</section>
@endsection
