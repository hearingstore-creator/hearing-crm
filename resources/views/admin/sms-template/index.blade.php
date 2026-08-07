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
                        <h5><i class="fas fa-file-alt mr-2"></i>SMS Templates</h5>
                     </div>
                     <div class="col-md-6 text-right">
                        @can('permission', 'templates.create')
                        <a href="{{ url('admin/sms-templates/create') }}" class="btn-primary btn-cs-primary btn-sm">
                           <i class="fas fa-plus mr-1"></i> Add Template
                        </a>
                        @endcan
                        <a href="{{ url('admin/sms') }}" class="btn btn-sm btn-outline-primary ml-2">
                           <i class="fas fa-comment-dots mr-1"></i> SMS Centre
                        </a>
                     </div>
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                  <table class="table table-bordered table-striped">
                     <thead>
                        <tr>
                           <th>SL</th>
                           <th>Name</th>
                           <th>Category</th>
                           <th>Body</th>
                           <th>Variables</th>
                           <th>Status</th>
                           <th style="width:120px">Action</th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach ($templates as $key => $template)
                        <tr>
                           <td>{{ ++$key }}</td>
                           <td>{{ $template->name }}</td>
                           <td>
                              <span class="sms-badge sms-badge-{{ $template->category_color ?? 'gray' }}">
                                 {{ $template->category_label }}
                              </span>
                           </td>
                           <td>{{ Str::limit($template->body, 80) }}</td>
                           <td>
                              @php $vars = is_array($template->variables) ? $template->variables : json_decode($template->getRawOriginal('variables'), true) ?? []; @endphp
                              @if($vars)
                              @foreach($vars as $var)
                              @php $tag = '{{' . $var . '}}'; @endphp
                              <small class="sms-template-var">{{ $tag }}</small>
                              @endforeach
                              @else
                              <span class="text-muted">-</span>
                              @endif
                           </td>
                           <td>
                              @if($template->is_active)
                              <span class="badge badge-success">Active</span>
                              @else
                              <span class="badge badge-danger">Inactive</span>
                              @endif
                           </td>
                           <td class="text-center">
                              @can('permission', 'templates.edit')
                              <a href="{{ route('sms-templates.edit', $template->id) }}" class="btn btn-success btn-sm">
                                 <i class="fas fa-edit"></i>
                              </a>
                              @endcan
                              @can('permission', 'templates.delete')
                              <form action="{{ route('sms-templates.destroy', $template->id) }}" method="POST" class="d-inline">
                                 @csrf
                                 @method('DELETE')
                                 <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)">
                                    <i class="far fa-trash-alt"></i>
                                 </button>
                              </form>
                              @endcan
                           </td>
                        </tr>
                        @endforeach

                        @if($templates->isEmpty())
                        <tr>
                           <td colspan="7" class="text-center text-muted py-4">
                              <i class="fas fa-file-alt fa-2x mb-2"></i>
                              <p class="mb-0">No templates found.</p>
                           </td>
                        </tr>
                        @endif
                     </tbody>
                  </table>
               </div>
            </div>
         </div>
      </div>
   </div>
</section>
@endsection
