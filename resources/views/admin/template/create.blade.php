@extends('admin.layouts.master')

@push('css')
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
                                    <h5>Add Template</h5>
                                </div>
                                @can('permission', 'template.index')
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/template') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-align-justify mr-1"></i>Template List
                                    </a>
                                </div>
                                @endcan
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/template') }}" enctype="multipart/form-data" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="title" class="col-sm-3 col-form-label">Title <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text" class="form-control" id="title" placeholder="Template Title" name="title" autocomplete="off" required>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="content" class="col-sm-3 col-form-label">Content:</label>
                                            <div class="col-sm-9">
                                                <input type="hidden" name="content" id="content">
                                                <div id="editor-container"></div>
                                                <small class="form-text text-muted">Rich text editor for template content</small>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="status" class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-3">
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="1" checked="checked" id="status">
                                                    Active
                                                </label>
                                                <label class="radio-inline my-2">
                                                    <input type="radio" name="status" value="0" id="status">
                                                    Inactive
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <div>
                                            <button type="submit" class="btn btn-success">Save</button>
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
<!-- Summernote Library (jQuery already loaded in master layout) -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote.min.js"></script>
<script>
// Wait for DOM to load
$(document).ready(function() {
    // Initialize Summernote Editor
    $('#editor-container').summernote({
        placeholder: 'Compose your template content here...',
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

    // Update hidden input when form submits
    $('form').on('submit', function(e) {
        var content = $('#editor-container').summernote('code');
        $('#content').val(content);
    });
});
</script>
@endpush
