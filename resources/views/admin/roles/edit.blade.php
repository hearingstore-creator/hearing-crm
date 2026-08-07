@extends('admin.layouts.master')

@section('content')
    <section class="content role-permission pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="card-title">
                                        <i class="fas fa-user-shield mr-2"></i>
                                        Manage Permissions for Role: {{ $role->name }}
                                    </h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/roles') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-list mr-1"></i> Role List
                                    </a>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ route('roles.update', $role->id) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="check-all-container">
                                    <label class="check-all-label">
                                        <input type="checkbox" id="checkAll" class="mr-2">
                                        <i class="fas fa-check-double mr-2"></i>
                                        <strong>Check All Permissions</strong>
                                    </label>
                                </div>

                                <div class="accordion menu-accordion" id="menuAccordion">
                                    @foreach ($menus as $menu)
                                        <div class="card mb-2 border shadow-sm">
                                            <div class="card-header menu-header py-2 d-flex justify-content-between align-items-center bg-light"
                                                data-toggle="collapse" data-target="#collapse-{{ $menu->id }}"
                                                aria-expanded="false" aria-controls="collapse-{{ $menu->id }}">
                                                <div class="menu-title text-dark p-0 font-weight-bold">
                                                    <i class="fas fa-{{ $menu->icon }} mr-1"></i>
                                                    {{ $menu->name }}
                                                    <i class="fas fa-chevron-down collapse-icon ml-2"></i>
                                                </div>

                                                {{-- Parent menu "check all" --}}
                                                <label class="menu-select-all mb-0" onclick="event.stopPropagation()">
                                                    <input type="checkbox" class="menu-check-all mr-1"
                                                        data-menu="{{ $menu->id }}">
                                                    Select All
                                                </label>
                                            </div>

                                            <div id="collapse-{{ $menu->id }}" class="collapse"
                                                data-parent="#menuAccordion">
                                                <div class="card-body card-body-content bg-white">

                                                    {{-- If menu has children --}}
                                                    @if ($menu->children->count() > 0)
                                                        @foreach ($menu->children as $child)
                                                            <div class="child-menu-item border rounded p-2 mb-3">
                                                                <div
                                                                    class="child-menu-header d-flex justify-content-between align-items-center">
                                                                    <div class="child-menu-title">
                                                                        <strong>{{ $child->name }}</strong></div>
                                                                    {{-- Child menu "check all" --}}
                                                                    <label class="child-select-all mb-0">
                                                                        <input type="checkbox" class="child-check-all mr-1"
                                                                            data-child="{{ $child->id }}">
                                                                        Select All
                                                                    </label>
                                                                </div>

                                                                <div class="permissions-grid ml-4 row mt-2">
                                                                    @foreach ($child->permissions as $permission)
                                                                        <div class="permission-item col-md-2 mb-2"
                                                                            data-permission="{{ $permission->id }}">
                                                                            <label class="permission-label mb-0">
                                                                                <input type="checkbox" name="permissions[]"
                                                                                    value="{{ $permission->id }}"
                                                                                    class="permission-checkbox menu-{{ $menu->id }} child-{{ $child->id }}"
                                                                                    {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}>
                                                                                {{ ucfirst($permission->action) }}
                                                                            </label>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        {{-- No children: Show parent menu permissions --}}
                                                        <div class="permissions-grid row mt-2">
                                                            @foreach ($menu->permissions as $permission)
                                                                <div class="permission-item col-md-2 mb-2"
                                                                    data-permission="{{ $permission->id }}">
                                                                    <label class="permission-label mb-0">
                                                                        <input type="checkbox" name="permissions[]"
                                                                            value="{{ $permission->id }}"
                                                                            class="permission-checkbox menu-{{ $menu->id }}"
                                                                            {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}>
                                                                        {{ ucfirst($permission->action) }}
                                                                    </label>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="form-group row mt-3">
                                    <div class="col-md-12 text-center">
                                        <button type="submit" class="btn btn-success">
                                            Save Permissions
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

@push('scripts')
    <script src="{{ asset('assets/role-permission.js') }}"></script>
@endpush
