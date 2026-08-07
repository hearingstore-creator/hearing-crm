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
                        <h5><i class="fas fa-edit mr-2"></i>Edit SMS Template</h5>
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
                  <form action="{{ route('sms-templates.update', $template->id) }}" method="post">
                     @csrf
                     @method('put')
                     <div class="row">
                        <div class="col-md-12">
                           <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Template Name <i class="text-danger">*</i>:</label>
                              <div class="col-sm-6">
                                 <input type="text" class="form-control" name="name" value="{{ $template->name }}" required>
                              </div>
                           </div>
                           <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Category <i class="text-danger">*</i>:</label>
                              <div class="col-sm-6">
                                 <select name="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                    <option value="{{ $category->value }}" {{ $template->category == $category->value ? 'selected' : '' }}>
                                       {{ $category->label() }}
                                    </option>
                                    @endforeach
                                 </select>
                              </div>
                           </div>
                           <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Message Body <i class="text-danger">*</i>:</label>
                              <div class="col-sm-6">
                                 <textarea name="body" class="form-control" rows="5"
                                    required maxlength="1000">{{ $template->body }}</textarea>
                                 <small class="text-muted">Use {!! '{name}' !!}, {!! '{phone}' !!}, {!! '{customer_id}' !!}, {!! '{tier}' !!}, {!! '{points}' !!}, {!! '{email}' !!} as variables.</small>
                              </div>
                           </div>
                           <div class="form-group row">
                              <label class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                              <div class="col-sm-3">
                                 <label class="radio-inline my-2">
                                    <input type="radio" name="is_active" value="1" {{ $template->is_active ? 'checked' : '' }}> Active
                                 </label>
                                 <label class="radio-inline my-2">
                                    <input type="radio" name="is_active" value="0" {{ !$template->is_active ? 'checked' : '' }}> Inactive
                                 </label>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <div class="col-md-6 text-right"></div>
                        <div class="col-md-6 text-right">
                           <button type="submit" class="btn btn-success">
                              <i class="fas fa-save mr-1"></i> Update
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
