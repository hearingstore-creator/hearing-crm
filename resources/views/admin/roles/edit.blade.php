@extends('admin.layouts.master')
@section('page_title', 'Manage Permissions')
@section('breadcrumb_parent', 'Roles')
@section('breadcrumb_parent_url', url('admin/roles'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
/* Custom Toggle Switch */
.custom-toggle {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
    margin: 0;
}
.custom-toggle input { 
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: var(--gray-300);
    transition: .3s;
    border-radius: 34px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
input:checked + .toggle-slider {
    background-color: var(--accent-500);
}
input:checked + .toggle-slider:before {
    transform: translateX(18px);
}

/* Master Check All Box */
.master-check-all-box {
    background-color: var(--info-50, #f0f7ff);
    border: 1px solid var(--info-200, #bae6fd);
    border-radius: 10px;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    margin-bottom: 30px;
}
.master-check-all-box .title {
    font-weight: 700;
    font-size: 15px;
    margin-left: 14px;
    color: var(--page-text);
}

/* Category Title */
.menu-category-title {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--page-text-muted);
    margin-top: 30px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.menu-category-title::before {
    content: '';
    display: block;
    width: 12px;
    height: 12px;
    background: var(--gray-200);
    border-radius: 3px;
}

/* Menu Permission Card */
.menu-permission-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 10px;
    margin-bottom: 10px;
    transition: all 0.2s;
}
.menu-permission-card:hover {
    border-color: var(--gray-300);
}
.mpc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 20px;
    cursor: pointer;
    background: var(--gray-50);
    border-radius: 10px;
}
.mpc-header.collapsed {
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 10px;
}
.mpc-header:not(.collapsed) {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    border-bottom: 1px solid var(--gray-100);
}
.mpc-title {
    font-weight: 700;
    font-size: 14px;
    color: var(--page-text);
    display: flex;
    align-items: center;
}
.mpc-title i.collapse-icon {
    transition: transform 0.3s;
}
.mpc-header:not(.collapsed) .mpc-title i.collapse-icon {
    transform: rotate(180deg);
}
.mpc-action {
    display: flex;
    align-items: center;
    z-index: 2;
}

/* Child Menus */
.mpc-body {
    padding: 20px;
}
.child-menu-box {
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
    background: #fff;
}
.child-menu-box:last-child {
    margin-bottom: 0;
}
.child-menu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px dashed var(--gray-200);
}
.child-menu-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--page-text-secondary);
}

/* Permissions Grid */
.permissions-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
.permission-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    padding: 8px 16px;
    border-radius: 6px;
    transition: all 0.2s;
    min-width: 140px;
}
.permission-item:hover {
    background: var(--gray-100);
}
.permission-item.checked {
    background: var(--primary-50, #eff6ff);
    border-color: var(--primary-200, #bfdbfe);
}
.permission-item label {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    color: var(--page-text-secondary);
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}
.permission-item.checked label {
    color: var(--primary-700, #1d4ed8);
}

/* Custom Checkbox inside permissions */
.custom-cb {
    appearance: none;
    width: 18px;
    height: 18px;
    border: 2px solid var(--gray-300);
    border-radius: 4px;
    background-color: white;
    display: inline-block;
    position: relative;
    cursor: pointer;
    transition: all 0.2s;
}
.custom-cb:checked {
    background-color: var(--accent-500);
    border-color: var(--accent-500);
}
.custom-cb:checked::after {
    content: '';
    position: absolute;
    left: 5px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

/* Dashboard permission sections & presets */
.dash-preset-bar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px;
    background: linear-gradient(135deg, var(--accent-50, #eef2ff), var(--card-bg));
    border: 1px solid var(--accent-100, #e0e7ff); border-radius: 10px;
    padding: 12px 16px; margin-bottom: 16px;
}
.dash-preset-bar .dp-label { font-size: 13px; font-weight: 700; color: var(--page-text); }
.dash-preset-bar .dp-label small { font-weight: 500; color: var(--page-text-muted); }
.dash-preset-bar .dp-buttons { display: flex; flex-wrap: wrap; gap: 6px; }
.dp-btn {
    border: 1px solid var(--accent-200, #c7d2fe); background: var(--card-bg); color: var(--accent-700, #4338ca);
    border-radius: 999px; padding: 4px 12px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all .2s;
}
.dp-btn:hover { background: var(--accent-500); border-color: var(--accent-500); color: #fff; transform: translateY(-1px); }
.dp-btn.dp-clear { border-color: var(--gray-300); color: var(--page-text-muted); }
.dp-btn.dp-clear:hover { background: var(--danger-500, #ef4444); border-color: var(--danger-500, #ef4444); color: #fff; }
.dp-btn.applied { background: var(--accent-500); border-color: var(--accent-500); color: #fff; }
.dash-preset-bar .dp-count { margin-left: auto; font-size: 12px; color: var(--page-text-muted); }
.dash-preset-bar .dp-count b { color: var(--accent-600); font-size: 15px; }
.dash-group-box .dg-hint { font-size: 11.5px; color: var(--page-text-muted); margin-top: 3px; font-weight: 500; }
.dash-group-box .dg-count { font-size: 11px; font-weight: 700; color: var(--accent-700, #4338ca); background: var(--accent-50, #eef2ff); border-radius: 999px; padding: 1px 8px; margin-left: 6px; }

</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-shield" style="color: var(--accent-500); margin-right: 8px;"></i>Manage Permissions for Role: {{ $role->name }}</h1>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/roles') }}" class="btn btn-primary btn-sm" style="border-radius: 8px; padding: 8px 16px;">
                <i class="fas fa-list mr-1"></i> Role List
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1" style="border-radius: 12px; padding: 24px;">
        <form action="{{ route('roles.update', $role->id) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Check All -->
            <div class="master-check-all-box">
                <label class="custom-toggle" style="margin-bottom:0;">
                    <input type="checkbox" id="checkAll">
                    <span class="toggle-slider"></span>
                </label>
                <div class="title"><i class="fas fa-check-double mr-2" style="color:var(--accent-500);"></i>Check All Permissions</div>
            </div>

            <!-- Menus -->
            @php $currentCategory = null; @endphp
            @foreach ($menus as $menu)
                @if ($menu->category && $menu->category !== $currentCategory)
                    @php $currentCategory = $menu->category; @endphp
                    <div class="menu-category-title">
                        {{ $currentCategory }}
                    </div>
                @endif
                
                <div class="menu-permission-card">
                    <div class="mpc-header collapsed" data-toggle="collapse" data-target="#collapse-{{ $menu->id }}">
                        <div class="mpc-title">
                            <i class="fas fa-{{ $menu->icon }} mr-2" style="color:var(--gray-500); width:20px; text-align:center;"></i> 
                            {{ $menu->display_name }} 
                            <i class="fas fa-chevron-down collapse-icon ml-3" style="font-size:12px; color:var(--gray-400)"></i>
                        </div>
                        
                        <div class="mpc-action" onclick="event.stopPropagation()">
                            <label class="custom-toggle" style="margin: 0;">
                                <input type="checkbox" class="menu-check-all" data-menu="{{ $menu->id }}">
                                <span class="toggle-slider"></span>
                            </label>
                            <span style="font-size: 12px; font-weight: 700; margin-left: 10px; color: var(--page-text-muted); text-transform:uppercase;">Select All</span>
                        </div>
                    </div>

                    <div id="collapse-{{ $menu->id }}" class="collapse">
                        <div class="mpc-body">

                            @if ($menu->children->count() > 0)
                                @foreach ($menu->children as $child)
                                    <div class="child-menu-box">
                                        <div class="child-menu-header">
                                            <div class="child-menu-title">{{ $child->display_name }}</div>
                                            
                                            <div class="mpc-action">
                                                <label class="custom-toggle" style="margin: 0; transform: scale(0.8);">
                                                    <input type="checkbox" class="child-check-all" data-child="{{ $child->id }}">
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <span style="font-size: 11px; font-weight: 700; margin-left: 6px; color: var(--page-text-muted); text-transform:uppercase;">Select All</span>
                                            </div>
                                        </div>

                                        <div class="permissions-grid">
                                            @foreach ($child->permissions as $permission)
                                                <div class="permission-item {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}">
                                                    <label>
                                                        <input type="checkbox" name="permissions[]"
                                                            value="{{ $permission->id }}"
                                                            class="custom-cb permission-checkbox menu-{{ $menu->id }} child-{{ $child->id }}"
                                                            {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}>
                                                        {{ config('acl.action_labels.' . $permission->name, ucfirst(str_replace('_', ' ', $permission->action))) }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @elseif ($menu->name === 'Dashboard')
                                {{-- Dashboard: grouped by dashboard section, with presets --}}
                                @php
                                    $dPerms   = $menu->permissions->keyBy('action');
                                    $dGroups  = config('acl.dashboard_groups', []);
                                    $listed   = collect($dGroups)->pluck('actions')->flatten()->all();
                                    $unlisted = $dPerms->keys()->diff($listed)->values()->all();
                                    if ($unlisted) {
                                        $dGroups['other'] = $dGroups['other'] ?? ['label' => 'Other', 'icon' => 'fa-ellipsis-h', 'color' => '#94a3b8', 'hint' => '', 'actions' => []];
                                        $dGroups['other']['actions'] = array_merge($dGroups['other']['actions'], $unlisted);
                                    }
                                    $presets = collect(config('acl.dashboard_presets', []))->map(fn ($p) => $p['actions'] === ['*'] ? $dPerms->keys()->all() : $p['actions']);
                                @endphp

                                <div class="dash-preset-bar">
                                    <div class="dp-label"><i class="fas fa-magic mr-1"></i>Quick presets <small>(ticks boxes below — click Save to apply)</small></div>
                                    <div class="dp-buttons">
                                        @foreach (config('acl.dashboard_presets', []) as $pKey => $p)
                                            <button type="button" class="dp-btn {{ $pKey === 'clear' ? 'dp-clear' : '' }}" id="dash-preset-{{ $pKey }}"
                                                    data-menu="{{ $menu->id }}" data-actions='@json($presets[$pKey])' title="{{ $p['hint'] }}">
                                                <i class="fas {{ $p['icon'] }} mr-1"></i>{{ $p['label'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                    <div class="dp-count"><b id="dash-count-{{ $menu->id }}">0</b> / {{ $dPerms->count() }} enabled</div>
                                </div>

                                @foreach ($dGroups as $gKey => $g)
                                    @php $gPerms = collect($g['actions'])->map(fn ($a) => $dPerms->get($a))->filter(); @endphp
                                    @continue($gPerms->isEmpty())
                                    <div class="child-menu-box dash-group-box" style="border-left:3px solid {{ $g['color'] }};">
                                        <div class="child-menu-header">
                                            <div>
                                                <div class="child-menu-title"><i class="fas {{ $g['icon'] }} mr-2" style="color:{{ $g['color'] }};"></i>{{ $g['label'] }}
                                                    <span class="dg-count" data-dgroup-count="{{ $menu->id }}-{{ $gKey }}"></span></div>
                                                @if (!empty($g['hint']))<div class="dg-hint">{{ $g['hint'] }}</div>@endif
                                            </div>
                                            <div class="mpc-action">
                                                <label class="custom-toggle" style="margin: 0; transform: scale(0.8);">
                                                    <input type="checkbox" class="dgroup-check-all" data-dgroup="{{ $menu->id }}-{{ $gKey }}">
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <span style="font-size: 11px; font-weight: 700; margin-left: 6px; color: var(--page-text-muted); text-transform:uppercase;">Select All</span>
                                            </div>
                                        </div>
                                        <div class="permissions-grid">
                                            @foreach ($gPerms as $permission)
                                                @php
                                                    $lbl = config('acl.action_labels.' . $permission->name, ucfirst(str_replace('_', ' ', $permission->action)));
                                                    $lbl = preg_replace('/^(Analytics|KPI|Lookout):\s*/', '', $lbl);
                                                @endphp
                                                <div class="permission-item {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}">
                                                    <label>
                                                        <input type="checkbox" name="permissions[]"
                                                            value="{{ $permission->id }}"
                                                            data-action="{{ $permission->action }}"
                                                            class="custom-cb permission-checkbox menu-{{ $menu->id }} dgroup-{{ $menu->id }}-{{ $gKey }}"
                                                            {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}>
                                                        {{ $lbl }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="permissions-grid">
                                    @foreach ($menu->permissions as $permission)
                                        <div class="permission-item {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}">
                                            <label>
                                                <input type="checkbox" name="permissions[]"
                                                    value="{{ $permission->id }}"
                                                    class="custom-cb permission-checkbox menu-{{ $menu->id }}"
                                                    {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}>
                                                {{ config('acl.action_labels.' . $permission->name, ucfirst(str_replace('_', ' ', $permission->action))) }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <div style="margin-top: 30px; text-align: right;">
                <button type="submit" class="btn btn-primary" style="border-radius: 8px; padding: 10px 24px; font-weight: 600; font-size: 14px;">
                    <i class="fas fa-save mr-2"></i> Save Permissions
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    
    // Update permission item style
    function updatePermissionItemState(checkbox) {
        const $item = $(checkbox).closest('.permission-item');
        if ($(checkbox).is(':checked')) {
            $item.addClass('checked');
        } else {
            $item.removeClass('checked');
        }
    }

    $('.permission-checkbox').on('change', function() {
        updatePermissionItemState(this);
        updateParentToggles();
    });

    // Check All Master Toggle
    $('#checkAll').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.permission-checkbox, .menu-check-all, .child-check-all').prop('checked', isChecked);
        $('.permission-item').toggleClass('checked', isChecked);
    });

    // Menu Check All
    $('.menu-check-all').on('change', function() {
        const menuId = $(this).data('menu');
        const isChecked = $(this).is(':checked');
        
        $(`.menu-${menuId}`).prop('checked', isChecked);
        $(`.menu-${menuId}`).each(function() {
            updatePermissionItemState(this);
        });

        $(`[data-child]`).each(function() {
            const childId = $(this).data('child');
            const childCheckboxes = $(`.child-${childId}`);
            if (childCheckboxes.length > 0 && childCheckboxes.first().hasClass(`menu-${menuId}`)) {
                $(this).prop('checked', isChecked);
            }
        });
        
        updateMasterToggle();
    });

    // Child Check All
    $('.child-check-all').on('change', function() {
        const childId = $(this).data('child');
        const isChecked = $(this).is(':checked');

        $(`.child-${childId}`).prop('checked', isChecked);
        $(`.child-${childId}`).each(function() {
            updatePermissionItemState(this);
        });
        
        updateMenuToggleByChild(childId);
        updateMasterToggle();
    });

    // Smart logic to sync toggles upwards
    function updateParentToggles() {
        $('.child-check-all').each(function() {
            const childId = $(this).data('child');
            const cbs = $(`.child-${childId}`);
            if(cbs.length > 0) {
                $(this).prop('checked', cbs.filter(':checked').length === cbs.length);
            }
        });

        $('.menu-check-all').each(function() {
            const menuId = $(this).data('menu');
            const cbs = $(`.menu-${menuId}`);
            if(cbs.length > 0) {
                $(this).prop('checked', cbs.filter(':checked').length === cbs.length);
            }
        });

        updateMasterToggle();
    }
    
    function updateMenuToggleByChild(childId) {
        $('.menu-check-all').each(function() {
            const menuId = $(this).data('menu');
            const cbs = $(`.menu-${menuId}`);
            if(cbs.length > 0) {
                $(this).prop('checked', cbs.filter(':checked').length === cbs.length);
            }
        });
    }

    function updateMasterToggle() {
        const all = $('.permission-checkbox');
        $('#checkAll').prop('checked', all.filter(':checked').length === all.length && all.length > 0);
    }
    
    // Initial check on load
    updateParentToggles();

    // ── Dashboard sections ──
    function updateDashCounters() {
        $('.dgroup-check-all').each(function () {
            const g = $(this).data('dgroup');
            const cbs = $(`.dgroup-${g}`);
            const on = cbs.filter(':checked').length;
            $(this).prop('checked', cbs.length > 0 && on === cbs.length);
            $(`[data-dgroup-count="${g}"]`).text(on + ' / ' + cbs.length);
        });
        $('[id^="dash-count-"]').each(function () {
            const menuId = this.id.replace('dash-count-', '');
            $(this).text($(`.menu-${menuId}:checked`).length);
        });
    }

    $('.dgroup-check-all').on('change', function () {
        const g = $(this).data('dgroup');
        const isChecked = $(this).is(':checked');
        $(`.dgroup-${g}`).prop('checked', isChecked).each(function () { updatePermissionItemState(this); });
        updateParentToggles();
        updateDashCounters();
    });

    $('.dp-btn').on('click', function () {
        const menuId = $(this).data('menu');
        const actions = $(this).data('actions') || [];
        $(`.menu-${menuId}`).each(function () {
            $(this).prop('checked', actions.indexOf($(this).data('action')) !== -1);
            updatePermissionItemState(this);
        });
        $(this).siblings('.dp-btn').removeClass('applied');
        $(this).addClass('applied');
        updateParentToggles();
        updateDashCounters();
    });

    $('.permission-checkbox, .menu-check-all, #checkAll').on('change', function () {
        setTimeout(updateDashCounters, 0);
    });
    updateDashCounters();
});
</script>
@endpush
