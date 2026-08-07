<!-- Templates Tab -->
<div class="tab-pane fade p-3" id="templates-tab">
   <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0"><i class="fas fa-file-alt mr-1"></i> Message Templates</h6>
      @can('permission', 'templates.create')
      <a href="{{ url('admin/sms-templates/create') }}" class="btn btn-sm btn-primary">
         <i class="fas fa-plus mr-1"></i> Add Template
      </a>
      @endcan
   </div>

   <div class="row">
      @foreach ($templates as $template)
      <div class="col-md-4 col-sm-6 mb-3">
         <div class="card sms-template-card h-100" data-body="{{ $template->body }}" data-variables="{{ $template->variables ? json_encode($template->variables) : '[]' }}">
            <div class="card-body">
               <div class="d-flex justify-content-between align-items-start mb-2">
                  <h6 class="mb-0">{{ $template->name }}</h6>
                  <span class="sms-badge sms-badge-{{ $template->category_color ?? 'gray' }}">
                     {{ $template->category_label }}
                  </span>
               </div>
               <div class="sms-template-body">
                  {{ Str::limit($template->body, 150) }}
               </div>
               @if($template->variables)
               <div class="mt-2">
                  @foreach($template->variables as $var)
                  <small class="sms-template-var">{!! '{{' . $var . '}}' !!}</small>
                  @endforeach
               </div>
               @endif
            </div>
            <div class="card-footer sms-template-footer">
               <span class="sms-template-use-btn" data-template-id="{{ $template->id }}">
                  <i class="fas fa-arrow-right mr-1"></i> Use in Composer
               </span>
            </div>
         </div>
      </div>
      @endforeach

      @if($templates->isEmpty())
      <div class="col-md-12">
         <div class="text-center py-5 text-muted">
            <i class="fas fa-file-alt fa-3x mb-3"></i>
            <p>No templates yet. Create one to reuse messages!</p>
         </div>
      </div>
      @endif
   </div>
</div>
