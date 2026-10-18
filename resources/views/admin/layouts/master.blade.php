<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $setting->company_name ?? null }}</title>

    <!-- Google Font: Inter -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    @stack('css')
    
    <!-- Theme System (Loaded first to define variables) -->
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
    
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dist/css/adminlte.min.css') }}">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">
    <div class="wrapper">

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light" style="border-bottom: 1px solid #edf2f7; background: #fff; padding: 12px 24px; display: flex; align-items: center;">
            <!-- Left navbar links -->
            <ul class="navbar-nav align-items-center">
                <li class="nav-item">
                    <a class="nav-link pl-0" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
                <li class="nav-item d-none d-sm-inline-block ml-2">
                    <div class="navbar-breadcrumb">
                        <span class="breadcrumb-title">@yield('page_title', 'Overview')</span>
                        <nav class="breadcrumb-trail">
                            <a href="{{ url('admin/dashboard') }}" class="breadcrumb-link">
                                <i class="fas fa-home"></i>
                            </a>
                            <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
                            @hasSection('breadcrumb_parent')
                                <a href="@yield('breadcrumb_parent_url', '#')" class="breadcrumb-link">@yield('breadcrumb_parent')</a>
                                <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
                            @endif
                            <span class="breadcrumb-current">@yield('page_title', 'Overview')</span>
                        </nav>
                    </div>
                </li>
            </ul>

            <!-- Search Bar -->
            <div class="navbar-search mx-4 d-none d-md-block">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" placeholder="Search patients, phone, invoice, serial..." aria-label="Search across CRM" data-endpoint="{{ route('global.search') }}">
                <span class="search-shortcut">Ctrl+K</span>
            </div>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto" style="align-items: center; gap: 16px;">
                
                <!-- Live Clock -->
                <li class="nav-item">
                    <div class="live-clock" id="liveClock">
                        <i class="fa-regular fa-clock"></i>
                        <span>Loading time...</span>
                    </div>
                </li>

                <!-- Theme Toggle -->
                <li class="nav-item">
                    <button class="theme-toggle-btn" id="theme-toggle" aria-label="Toggle dark mode">
                        <i class="fa-solid fa-sun icon-sun"></i>
                        <i class="fa-solid fa-moon icon-moon"></i>
                    </button>
                </li>
                
                <!-- Notifications Dropdown -->
                @php
                    $bellUnread = auth()->user()?->unreadNotifications()->count() ?? 0;
                    $bellItems = auth()->user()?->notifications()->latest()->limit(8)->get() ?? collect();
                    $bellTones = ['info' => 'info', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'];
                @endphp
                <li class="nav-item dropdown">
                    <a class="notification-bell" data-toggle="dropdown" href="#" id="notif-bell" aria-label="Notifications">
                        <i class="fa-regular fa-bell"></i>
                        @if ($bellUnread)<span class="notif-badge">{{ $bellUnread > 9 ? '9+' : $bellUnread }}</span>@endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-right notification-dropdown">
                        <div class="notif-header">
                            <h6>Notifications</h6>
                            @if ($bellUnread)
                                <form method="POST" action="{{ route('notifications.read-all') }}" class="m-0">@csrf
                                    <button type="submit" id="notif-read-all" style="font-size: 12px; color: var(--accent-500); background: none; border: 0; padding: 0;">Mark all as read</button>
                                </form>
                            @endif
                            <a href="{{ route('notifications.settings') }}" id="notif-settings" title="Notification settings" aria-label="Notification settings" style="color: var(--page-text-muted); margin-left: 10px;"><i class="fa-solid fa-gear"></i></a>
                        </div>
                        @forelse ($bellItems as $n)
                            @php $tone = $bellTones[$n->data['tone'] ?? 'info'] ?? 'info'; @endphp
                            <a href="{{ route('notifications.open', $n->id) }}" class="notif-item" id="notif-{{ $n->id }}" style="{{ $n->read_at ? 'opacity: .62;' : '' }} text-decoration: none;">
                                <div class="notif-icon" style="background: var(--{{ $tone }}-50); color: var(--{{ $tone }}-600);">
                                    <i class="fa-solid {{ $n->data['icon'] ?? 'fa-bell' }}"></i>
                                </div>
                                <div class="notif-content">
                                    <p><strong>{{ $n->data['title'] ?? '' }}</strong> {{ $n->data['text'] ?? '' }}</p>
                                    <small>{{ $n->created_at->diffForHumans() }}</small>
                                </div>
                            </a>
                        @empty
                            <div class="notif-item"><div class="notif-content"><p style="color: var(--page-text-muted);">You're all caught up.</p></div></div>
                        @endforelse
                    </div>
                </li>

                <!-- User Dropdown Menu -->
                <li class="nav-item dropdown ml-2">
                    <a href="#" class="user-menu-trigger" data-toggle="dropdown">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'User') }}&background=6366f1&color=fff&rounded=true&bold=true" class="user-avatar" alt="User Avatar">
                        <div class="user-info">
                            <span class="user-name">{{ Auth::user()->name ?? 'Administrator' }}</span>
                            <span class="user-role">{{ Auth::user()->roles->first()->name ?? 'Admin' }}</span>
                        </div>
                        <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: var(--page-text-muted);"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right user-dropdown">
                        <div class="user-dropdown-header">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'User') }}&background=6366f1&color=fff&rounded=true&bold=true" alt="User Image">
                            <h6 class="user-name">{{ Auth::user()->name ?? 'Administrator' }}</h6>
                            <p class="user-email">{{ Auth::user()->email ?? 'admin@example.com' }}</p>
                        </div>
                        <div class="user-dropdown-body">
                            <a href="#"><i class="fa-regular fa-user"></i> My Profile</a>
                            <a href="#"><i class="fa-solid fa-sliders"></i> Account Settings</a>
                            <a href="#"><i class="fa-solid fa-chart-line"></i> Activity Log</a>
                        </div>
                        <div class="user-dropdown-footer">
                            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign out
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </div>
                    </div>
                </li>
            </ul>
        </nav>
        <!-- /.navbar -->

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <!-- Brand Logo -->

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user panel (optional) -->

                <a href="{{ url('admin/dashboard') }}" class="brand-link">
                    @if (!empty($setting->logo))
                        <img src="{{ asset($setting->logo) }}" alt="Logo" class="brand-image" style="height: 32px; width: 32px; object-fit: contain; filter: brightness(0) invert(1);">
                    @else
                        <img src="{{ asset('assets/images/logo-icon.png') }}" alt="Icon" class="brand-image" style="height: 32px; width: 32px; object-fit: contain; filter: brightness(0) invert(1);">
                    @endif
                    <span class="brand-text">{{ $setting->company_name ?? 'Hearing Store' }}</span>
                </a>

                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                        data-accordion="false">
                        @include('admin.partials.sidebar-menu', ['menus' => $menus ?? (auth()->check() ? auth()->user()->accessibleMenus() : collect())])
                    </ul>
                </nav>

                <!-- /.sidebar-menu -->
            </div>
            <!-- /.sidebar -->
        </aside>

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            @yield('content')
        </div>
        <!-- /.content-wrapper -->

        <footer class="main-footer">
            <div class="row">
                <div class="col-md-6">
                    &copy; {{ date('Y') }} <strong>{{ $setting->company_name ?? 'Hearing Store' }}</strong>. All rights reserved.
                </div>
                <div class="col-md-6">
                    <div class="float-right d-none d-sm-inline-block">
                        <b>Version</b> 2.0.0
                    </div>
                </div>
            </div>
        </footer>

        <!-- Control Sidebar -->
        <aside class="control-sidebar control-sidebar-dark">
            <!-- Control sidebar content goes here -->
        </aside>
        <!-- /.control-sidebar -->

    </div>
    <!-- ./wrapper -->

    <!-- Scripts -->
    <!-- jQuery -->
    <script src="{{ asset('assets/plugins/jquery/jquery.min.js') }}"></script>
    <!-- jQuery UI 1.11.4 -->
    <script src="{{ asset('assets/plugins/jquery-ui/jquery-ui.min.js') }}"></script>
    <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
    <script>
        $.widget.bridge('uibutton', $.ui.button)
    </script>
    <!-- Bootstrap 4 -->
    <script src="{{ asset('assets/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    @stack('scripts')
    @stack('js')
    <script src="{{ asset('assets/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('assets/dist/js/adminlte.js') }}"></script>
    <script src="{{ asset('assets/custom.js') }}"></script>
    <!-- Print paper size selector (A4 default) -->
    <script src="{{ asset('assets/js/print-size.js') }}"></script>
    
    <!-- Theme System Scripts -->
    <script src="{{ asset('assets/js/theme-toggle.js') }}"></script>
    <script src="{{ asset('assets/js/global-search.js') }}"></script>
    
    <!-- Live Clock Script -->
    <script>
        function updateLiveClock() {
            const clockEl = document.getElementById('liveClock');
            if(!clockEl) return;
            
            const now = new Date();
            const options = { 
                timeZone: 'Asia/Dhaka', 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true
            };
            
            const formatter = new Intl.DateTimeFormat('en-US', options);
            const timeString = formatter.format(now);
            
            clockEl.querySelector('span').textContent = timeString;
        }
        
        setInterval(updateLiveClock, 1000);
        updateLiveClock(); // initial call
    </script>

    <!-- Sidebar Scroll Position Persistence -->
    <script>
        (function() {
            var STORAGE_KEY = 'crm_sidebar_scroll';
            
            // Find the sidebar scrollable container
            function getSidebarEl() {
                // AdminLTE with OverlayScrollbars wraps content in .os-viewport
                var osViewport = document.querySelector('.main-sidebar .os-viewport');
                if (osViewport) return osViewport;
                // Fallback: the .sidebar element itself
                return document.querySelector('.main-sidebar .sidebar');
            }

            // Restore scroll position after page load
            function restoreScroll() {
                var saved = sessionStorage.getItem(STORAGE_KEY);
                if (saved) {
                    var el = getSidebarEl();
                    if (el) {
                        el.scrollTop = parseInt(saved, 10);
                    }
                }
            }

            // Save scroll position before navigating away
            function saveScroll() {
                var el = getSidebarEl();
                if (el) {
                    sessionStorage.setItem(STORAGE_KEY, el.scrollTop);
                }
            }

            // Save on any link click or before unload
            window.addEventListener('beforeunload', saveScroll);

            // Also save when any sidebar link is clicked
            document.addEventListener('click', function(e) {
                var link = e.target.closest('.nav-sidebar a');
                if (link) saveScroll();
            });

            // Restore after DOM + OverlayScrollbars have initialized
            // AdminLTE inits OverlayScrollbars on .sidebar, so wait a bit
            if (document.readyState === 'complete') {
                setTimeout(restoreScroll, 100);
            } else {
                window.addEventListener('load', function() {
                    setTimeout(restoreScroll, 100);
                });
            }
        })();
    </script>

    <!-- Sidebar: auto-scroll an opened group fully into view -->
    <script>
        (function ($) {
            if (!$) return;

            function sidebarViewport() {
                return document.querySelector('.main-sidebar .os-viewport') || document.querySelector('.main-sidebar .sidebar');
            }

            function revealGroup(li) {
                var vp = sidebarViewport();
                if (!vp || !li) return;
                var vpRect = vp.getBoundingClientRect();
                var liRect = li.getBoundingClientRect();
                var pad = 12;
                var overflowBottom = liRect.bottom - vpRect.bottom + pad;   // part of the group hidden below
                if (overflowBottom <= 0) return;                           // already fully visible
                var roomAbove = liRect.top - vpRect.top - pad;              // never push the group header out of view
                var delta = Math.min(overflowBottom, Math.max(roomAbove, 0));
                if (delta <= 0) return;

                var os = $('.main-sidebar .sidebar').overlayScrollbars ? $('.main-sidebar .sidebar').overlayScrollbars() : null;
                if (os && typeof os.scroll === 'function') {
                    os.scroll({ y: '+=' + Math.round(delta) + 'px' }, 300);
                } else if (vp.scrollTo) {
                    vp.scrollTo({ top: vp.scrollTop + delta, behavior: 'smooth' });
                } else {
                    vp.scrollTop += delta;
                }
            }

            $(document).on('click', '.nav-sidebar .nav-item > .nav-link', function () {
                var $li = $(this).parent('.nav-item');
                var $tree = $li.children('.nav-treeview');
                if (!$tree.length) return;
                // Let AdminLTE start its slide animation, then wait for it to finish
                setTimeout(function () {
                    $tree.promise().done(function () {
                        if ($li.hasClass('menu-open') || $li.hasClass('menu-is-opening')) {
                            revealGroup($li[0]);
                        }
                    });
                }, 0);
            });
        })(window.jQuery);
    </script>
</body>
</html>
