@extends('admin.layouts.master')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<!-- Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote.min.css" rel="stylesheet">
<style>
    #editor-container {
        min-height: 300px;
    }
    .note-editor {
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    .note-toolbar {
        background-color: #f8f9fa;
        border-radius: 4px 4px 0 0;
    }
    .note-editable {
        background-color: white;
    }
    /* Table styles */
    .note-editable table {
        border-collapse: collapse;
        width: 100%;
        margin: 10px 0;
    }
    .note-editable table td,
    .note-editable table th {
        border: 1px solid #ddd;
        padding: 8px;
        min-width: 2em;
    }
    .note-editable table th {
        background-color: #f2f2f2;
        font-weight: bold;
        text-align: left;
    }
</style>
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
                                    <h5>Edit Proposal</h5>
                                </div>
                                @can('permission', 'proposals.index')
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/proposal') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-align-justify mr-1"></i>Proposal List
                                    </a>
                                </div>
                                @endcan
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ route('proposal.update', $proposal->id) }}" enctype="multipart/form-data" method="post">
                                @csrf
                                @method('put')
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="template_id" class="col-sm-3 col-form-label">Template:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select" id="template_id" name="template_id">
                                                    <option value="">Select Template</option>
                                                    @foreach ($templates as $template)
                                                        <option value="{{ $template->id }}" {{ $proposal->template_id == $template->id ? 'selected' : '' }} data-content="{{ $template->content }}">{{ $template->title }}</option>
                                                    @endforeach
                                                </select>
                                                <small class="form-text text-muted">Content will be loaded from selected template</small>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label class="col-sm-3 col-form-label">Send Type <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="send_type" value="customer" id="send_type_customer" @if(!$proposal->email) checked @endif onchange="toggleSendType()">
                                                    Customer
                                                </label>
                                                <label class="radio-inline my-2 ml-3">
                                                    <input type="radio" name="send_type" value="email" id="send_type_email" @if($proposal->email) checked @endif onchange="toggleSendType()">
                                                    Custom Email
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-group row" id="customer_field" @if($proposal->email) style="display: none;" @endif>
                                            <label for="customer_id" class="col-sm-3 col-form-label">Customer <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select" id="customer_id" name="customer_id">
                                                    <option value="">Select Customer</option>
                                                    @foreach ($customers as $customer)
                                                        <option value="{{ $customer->id }}" {{ $proposal->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->lead->full_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row" id="email_field" @if(!$proposal->email) style="display: none;" @endif>
                                            <label for="email" class="col-sm-3 col-form-label">Email <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="email" class="form-control" id="email" name="email" value="{{ $proposal->email ?? '' }}" placeholder="Enter Email Address">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="title" class="col-sm-3 col-form-label">Title <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="title" value="{{ $proposal->title }}" placeholder="Proposal Title" name="title" autocomplete="off" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="proposal_date" class="col-sm-3 col-form-label">Proposal Date:</label>
                                            <div class="col-sm-6">
                                                <input type="date" class="form-control" id="proposal_date" name="proposal_date" value="{{ $proposal->proposal_date }}">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="valid_until" class="col-sm-3 col-form-label">Valid Until:</label>
                                            <div class="col-sm-6">
                                                <input type="date" class="form-control" id="valid_until" name="valid_until" value="{{ $proposal->valid_until }}">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="total_amount" class="col-sm-3 col-form-label">Total Amount:</label>
                                            <div class="col-sm-6">
                                                <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" value="{{ $proposal->total_amount }}" placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="content" class="col-sm-3 col-form-label">Content:</label>
                                            <div class="col-sm-9">
                                                <input type="hidden" name="content" id="content" value="{{ old('content', $proposal->content) }}">
                                                <div id="editor-container"></div>
                                                <small class="form-text text-muted">Rich text editor for proposal content</small>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="status" class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select2" id="status" name="status" required>
                                                    <option value="">Select Status</option>
                                                    @foreach(\App\Enums\ProposalStatus::cases() as $case)
                                                        <option value="{{ $case->value }}" {{ $proposal->status === $case->value ? 'selected' : '' }}>{{ $case->value }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <div>
                                            <button type="submit" class="btn btn-success">Update</button>
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

@push('scripts')
 <!-- Select2 -->
  <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
  <script>
    $(document).ready(function() {
      $('.select').select2({
        width: '100%'
      });
    });
  </script>
<!-- Summernote Library (jQuery already loaded in master layout) -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote.min.js"></script>
<script>
function toggleSendType() {
    const sendTypeCustomer = document.getElementById('send_type_customer');
    const sendTypeEmail = document.getElementById('send_type_email');
    const customerField = document.getElementById('customer_field');
    const emailField = document.getElementById('email_field');
    const customerSelect = document.getElementById('customer_id');
    const emailInput = document.getElementById('email');

    if (sendTypeCustomer.checked) {
        customerField.style.display = 'flex';
        emailField.style.display = 'none';
        customerSelect.required = true;
        emailInput.required = false;
        emailInput.value = '';
    } else {
        customerField.style.display = 'none';
        emailField.style.display = 'flex';
        customerSelect.required = false;
        emailInput.required = true;
        customerSelect.value = '';
    }
}

// Wait for DOM to load
$(document).ready(function() {
    // Get initial content from database
    var initialContent = $('#content').val() || '';
    var hasInitialContent = initialContent.length > 0;

    // Initialize Summernote Editor
    $('#editor-container').summernote({
        placeholder: 'Compose your proposal content here...',
        tabsize: 2,
        height: 300,
        toolbar: [
            // [groupName, [list of button]]
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link', 'table', 'hr']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        tableClassName: 'table table-bordered',
        callbacks: {
            onChange: function(contents, $editable) {
                // Update hidden input on content change
                $('#content').val(contents);
            }
        }
    });

    // If there's initial content from database, load it
    // Otherwise load from the selected template
    if (hasInitialContent) {
        $('#editor-container').summernote('code', initialContent);
    } else {
        // Load from selected template if no custom content
        var selectedOption = $('#template_id').find('option:selected');
        var templateContent = selectedOption.data('content');
        if (templateContent) {
            $('#editor-container').summernote('code', templateContent);
        }
    }

    // Load template content when dropdown changes
    $('#template_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var templateContent = selectedOption.data('content');

        if (templateContent) {
            // Load template content into editor
            $('#editor-container').summernote('code', templateContent);
            // Update hidden input
            $('#content').val(templateContent);
        } else {
            // Clear editor if no template selected
            $('#editor-container').summernote('code', '');
            $('#content').val('');
        }
    });

    // Update hidden input when form submits
    $('form').on('submit', function(e) {
        var content = $('#editor-container').summernote('code');
        $('#content').val(content);
    });
});
</script>
@endpush
