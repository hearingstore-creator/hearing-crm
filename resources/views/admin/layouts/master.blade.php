<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $setting->company_name ?? null }}</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/google-font.css') }}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome-free/css/all.min.css') }}">

    @stack('css')
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">

    <link rel="stylesheet" href="{{ asset('assets/dist/css/adminlte.min.css') }}">
    <!-- overlayScrollbars -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/overlayScrollbars/css/OverlayScrollbars.min.css') }}">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i
                            class="fas fa-bars"></i></a>
                </li>
            </ul>

            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                <!-- Navbar Search -->
                <li class="nav-item">
                    <div class="navbar-search-block">
                    </div>
                </li>
                <li class="nav-item">
                    <a href="{{ route('logout') }}" id="logout-link">Logout</a>

                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="logout-form">
                        @csrf
                    </form>
                </li>

                <!-- Messages Dropdown Menu -->
                <li class="nav-item dropdown">

                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">

                        <div class="dropdown-divider"></div>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item">

                        </a>
                        <div class="dropdown-divider"></div>
                    </div>
                </li>
                <!-- Notifications Dropdown Menu -->

                <li class="nav-item">
                    <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                        <i class="fas fa-expand-arrows-alt"></i>
                    </a>
                </li>
                <li class="nav-item">
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

                <a href="" class="brand-link">
                    @if (!empty($setting->logo))
                        <img src="{{ asset($setting->logo) }}" alt="..." class="img-circle elevation-3"
                            height="60px" width="80px;">
                    @endif
                    <span class="brand-text font-weight-light">{{ Auth::user()->name }}</span>
                </a>

                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                        data-accordion="false">
                        @include('admin.partials.sidebar-menu', ['menus' => $menus])
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
                    Developed By <strong>{{ $setting->company_name }}</strong>
                </div>
                <div class="col-md-6">
                    <div class="float-right d-none d-sm-inline-block">
                        <b>Version</b> 1.0.0
                    </div>
                </div>
            </div>
        </footer>

        <!-- Control Sidebar -->
        <aside class="control-sidebar control-sidebar-dark">
            <!-- Control sidebar content goes here -->
        </aside>
        <!-- /.control-sidebar -->

        <!-- Modal -->
        {{-- <div class="modal fade" id="eBookModal" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"></h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div> --}}
                    {{-- <div class="modal-body">
                        <div class="mb-4">
                            <h4 class="text-center">Date Expired</h4>
                        </div>
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th class="text-center"> Name</th>
                                    <th class="text-center">Manufacturer Name</th>
                                    <th class="text-center">Batch No</th>
                                    <th class="text-center">Expiry Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                
                            </tbody>
                        </table>

                        @if (isset($stockOut))
                            <div class="mt-4 mb-4">
                                <h4 class="text-center">Out of Stock Medicine</h4>
                            </div>

                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th class="text-center">Medicine Name</th>
                                        <th class="text-center">Manufacturer Name</th>
                                        <th class="text-center">Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($stockOut as $item)
                                        <tr>
                                            <td class="text-center">
                                                {{ $item->medicine_name }} ({{ $item->strength }})
                                            </td>
                                            <td class="text-center">{{ $item->manufacturer_name }}</td>
                                            <td class="text-center">
                                                <span class="text-danger">{{ $item->stock }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div> --}}
                {{-- </div>
            </div>
        </div> --}}
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
    <script src="{{ asset('assets/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('assets/dist/js/adminlte.js') }}"></script>
    <script src="{{ asset('assets/custom.js') }}"></script>
</body>
</html>
