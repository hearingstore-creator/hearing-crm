<!-- Compose & Send Tab -->
<div class="tab-pane fade show active p-3" id="compose-tab">
   <!-- Stats Strip -->
   <div class="row mb-4">
      <div class="col-md-3 col-sm-6">
         <div class="sms-stat-card sms-stat-blue">
            <div class="sms-stat-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="sms-stat-value">{{ $stats['total_sent'] }}</div>
            <div class="sms-stat-label">Total Sent</div>
         </div>
      </div>
      <div class="col-md-3 col-sm-6">
         <div class="sms-stat-card sms-stat-green">
            <div class="sms-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="sms-stat-value">{{ $stats['today_sent'] }}</div>
            <div class="sms-stat-label">Sent Today</div>
         </div>
      </div>
      <div class="col-md-3 col-sm-6">
         <div class="sms-stat-card sms-stat-purple">
            <div class="sms-stat-icon"><i class="fas fa-file-alt"></i></div>
            <div class="sms-stat-value">{{ $stats['templates'] }}</div>
            <div class="sms-stat-label">Active Templates</div>
         </div>
      </div>
      <div class="col-md-3 col-sm-6">
         <div class="sms-stat-card sms-stat-orange">
            <div class="sms-stat-icon"><i class="fas fa-bullhorn"></i></div>
            <div class="sms-stat-value">{{ $stats['total_sent'] }}</div>
            <div class="sms-stat-label">Total Messages</div>
         </div>
      </div>
   </div>

   <form action="{{ url('admin/sms/send') }}" method="POST" id="smsComposeForm">
      @csrf
      <div class="row">
         <!-- Left Column: Filters + Recipients -->
         <div class="col-md-5">
            <!-- Campaign Name -->
            <div class="card card-outline card-info mb-3">
               <div class="card-header py-2">
                  <h6 class="mb-0"><i class="fas fa-tag mr-1"></i> Campaign Name</h6>
               </div>
               <div class="card-body">
                  <input type="text" name="campaign_name" class="form-control" placeholder="e.g., June Warranty Reminder" required>
               </div>
            </div>

            <!-- Select Template (drives audience + variables) -->
            <div class="card card-outline card-info mb-3">
               <div class="card-header py-2">
                  <h6 class="mb-0"><i class="fas fa-file-alt mr-1"></i> Select Template</h6>
               </div>
               <div class="card-body">
                  <select name="sms_template_id" id="smsTemplateSelect" class="form-control" required>
                     <option value="">-- Select a Template --</option>
                     @foreach($templates as $template)
                     <option value="{{ $template->id }}"
                        data-category="{{ $template->category }}"
                        data-variables="{{ $template->variables ? json_encode($template->variables) : '[]' }}"
                        data-body="{{ $template->body }}">
                        {{ $template->name }} — {{ $template->category_label }}
                     </option>
                     @endforeach
                  </select>
                  <input type="hidden" name="audience_type" id="audienceType" value="">
               </div>
            </div>

            <!-- Recipients Preview -->
            <div class="card card-outline card-secondary mb-3">
               <div class="card-header py-2 sms-recipient-header">
                  <h6 class="mb-0 text-white">
                     <i class="fas fa-list mr-1"></i> Recipients
                     <span class="badge badge-light ml-2" id="recipientCount">0</span>
                  </h6>
               </div>
               <div class="card-body p-0">
                  <div class="sms-recipient-list" id="recipientList">
                     <div class="sms-recipient-empty">
                        <i class="fas fa-user-friends"></i>
                        <p>Select a template and click "Load Recipients"</p>
                     </div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-info mt-2" id="loadRecipientsBtn" disabled>
                     <i class="fas fa-sync-alt mr-1"></i> Load Recipients
                  </button>
               </div>
            </div>
         </div>

         <!-- Right Column: Message Composer + Preview -->
         <div class="col-md-7">
            <!-- Message Composer -->
            <div class="card card-outline card-primary mb-3">
               <div class="card-header py-2">
                  <h6 class="mb-0"><i class="fas fa-edit mr-1"></i> Compose Message</h6>
               </div>
               <div class="card-body">
                  <!-- Variable Chips (dynamic — populated by JS based on selected template) -->
                  <div class="mb-2" id="variableChipsContainer" style="display:none;">
                     <small class="text-muted">Insert Variables:</small>
                     <div class="sms-variable-chips mt-1" id="smsVariableChips"></div>
                  </div>

                  <textarea name="message_body" id="messageBody" class="form-control sms-textarea"
                     rows="6" placeholder="{!! 'Type your message here... Use {{variable}} for personalization.' !!}"
                     required maxlength="1000"></textarea>

                  <div class="d-flex justify-content-between mt-1">
                     <small class="text-muted">
                        <span id="smsCharCount">0</span>/1000 characters
                        &middot; <span id="smsCount">1</span> SMS
                     </small>
                     <small class="text-muted">160 chars/SMS</small>
                  </div>
               </div>
            </div>

            <!-- Phone Preview -->
            <div class="card card-outline card-dark mb-3">
               <div class="card-header py-2 bg-dark text-white">
                  <h6 class="mb-0"><i class="fas fa-mobile-alt mr-1"></i> Message Preview</h6>
               </div>
               <div class="card-body sms-phone-preview">
                  <div class="sms-phone-bubble">
                     <p id="smsPreviewText" class="mb-0 sms-preview-placeholder">
                        Your message preview will appear here...
                     </p>
                  </div>
                  <div class="sms-phone-time">Just now</div>
               </div>
            </div>

            <!-- Send Button -->
            <div class="text-right">
               @can('permission', 'compose_&_send.create')
               <button type="submit" class="btn btn-lg btn-success" id="sendSmsBtn">
                  <i class="fas fa-paper-plane mr-2"></i> Send Now
               </button>
               @endcan
            </div>
         </div>
      </div>
   </form>
</div>
