@extends('admin.layouts.master')
@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote.min.css" rel="stylesheet">
    <style>
        #description-editor {
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
                                    <h5>Add Task</h5>
                                </div>
                                @can('permission', 'task_list.index')
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/task') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-align-justify mr-1"></i>Task List
                                    </a>
                                </div>
                                @endcan
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/task') }}" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        {{-- Subject --}}
                                        <div class="form-group row">
                                            <label for="subject" class="col-sm-3 col-form-label">Subject <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <input type="text"
                                                       class="form-control"
                                                       id="subject"
                                                       placeholder="Task Subject"
                                                       name="subject"
                                                       value="{{ old('subject') }}"
                                                       autocomplete="off"
                                                       required>
                                            </div>
                                        </div>

                                        {{-- Description --}}
                                        <div class="form-group row">
                                            <label for="description" class="col-sm-3 col-form-label">Description:</label>
                                            <div class="col-sm-6">
                                                <input type="hidden" name="description" id="description">
                                                <div id="description-editor"></div>
                                                <small class="form-text text-muted">Rich text editor for task description</small>
                                            </div>
                                        </div>

                                        {{-- Customer --}}
                                        <div class="form-group row">
                                            <label for="customer_id" class="col-sm-3 col-form-label">Customer:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select2" id="customer_id" name="customer_id">
                                                    <option value="">Select Customer</option>
                                                    @foreach ($customers as $customer)
                                                        <option value="{{ $customer->id }}"
                                                            {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                                                            {{ $customer->lead->full_name ?? '-' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        {{-- Assigned To --}}
                                        <div class="form-group row">
                                            <label for="assigned_to" class="col-sm-3 col-form-label">Assigned To:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select2" id="assigned_to" name="assigned_to">
                                                    <option value="">Select User</option>
                                                    @foreach ($users as $user)
                                                        <option value="{{ $user->id }}"
                                                            {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                                            {{ $user->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        {{-- Status --}}
                                        <div class="form-group row">
                                            <label for="status" class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select2" id="status" name="status" required>
                                                    @foreach ($statuses as $status)
                                                        <option value="{{ $status->value }}"
                                                            {{ old('status') == $status->value || $status->value == 'pending' ? 'selected' : '' }}>
                                                            {{ $status->label() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        {{-- Priority --}}
                                        <div class="form-group row">
                                            <label for="priority" class="col-sm-3 col-form-label">Priority <i class="text-danger">*</i>:</label>
                                            <div class="col-sm-6">
                                                <select class="form-control select2" id="priority" name="priority" required>
                                                    @foreach ($priorities as $priority)
                                                        <option value="{{ $priority->value }}"
                                                            {{ old('priority') == $priority->value || $priority->value == 'low' ? 'selected' : '' }}>
                                                            {{ $priority->label() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        {{-- Due Date --}}
                                        <div class="form-group row">
                                            <label for="due_date" class="col-sm-3 col-form-label">Due Date:</label>
                                            <div class="col-sm-6">
                                                <input type="date"
                                                       class="form-control"
                                                       id="due_date"
                                                       name="due_date"
                                                       value="{{ old('due_date') }}">
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
@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <!-- Summernote Library (jQuery already loaded in master layout) -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2();

            // Initialize Summernote Editor
            $('#description-editor').summernote({
                placeholder: 'Compose your task description here...',
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
                        $('#description').val(contents);
                    }
                }
            });

            // Update hidden input when form submits
            $('form').on('submit', function(e) {
                var content = $('#description-editor').summernote('code');
                $('#description').val(content);
            });
        });
    </script>
@endpush
@endsection
