@extends('admin.layouts.master')

@push('css')
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .dashboard-container {
            background: #f4f6f9;
            min-height: 100vh;
            padding: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-hover-shadow);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 12px 12px 0 0;
        }

        .stat-card.primary::before {
            background: var(--primary-gradient);
        }

        .stat-card.success::before {
            background: var(--success-gradient);
        }

        .stat-card.warning::before {
            background: var(--warning-gradient);
        }

        .stat-card.info::before {
            background: var(--info-gradient);
        }

        .stat-card.danger::before {
            background: var(--danger-gradient);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .stat-card.primary .stat-icon {
            background: var(--primary-gradient);
            color: white;
        }

        .stat-card.success .stat-icon {
            background: var(--success-gradient);
            color: white;
        }

        .stat-card.warning .stat-icon {
            background: var(--warning-gradient);
            color: white;
        }

        .stat-card.info .stat-icon {
            background: var(--info-gradient);
            color: white;
        }

        .stat-card.danger .stat-icon {
            background: var(--danger-gradient);
            color: white;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 6px;
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .stat-change {
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-change.positive {
            color: #28a745;
        }

        .stat-change.negative {
            color: #dc3545;
        }

        .quick-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            background: #f7fafc;
            color: #4a5568;
        }

        .quick-link-btn:hover {
            background: #e2e8f0;
            color: #2d3748;
            transform: translateY(-2px);
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 20px;
            border: none;
            height: 100%;
        }

        .chart-card .card-header {
            border: none;
            padding: 0 0 15px 0;
            background: transparent;
        }

        .chart-card .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        .chart-card .card-body {
            padding: 0;
        }

        /* Custom Chart Styles */
        .chart-container {
            position: relative;
            width: 100%;
            height: 300px;
        }

        .bar-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 100%;
            padding: 20px 0;
            gap: 10px;
        }

        .bar-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
        }

        .bar {
            width: 100%;
            max-width: 60px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .bar:hover {
            opacity: 0.8;
            transform: scaleY(1.02);
            transform-origin: bottom;
        }

        .bar-label {
            font-size: 11px;
            color: #718096;
            margin-top: 8px;
            text-align: center;
            font-weight: 500;
        }

        .bar-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            font-weight: 600;
            color: #2d3748;
            white-space: nowrap;
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Line Chart */
        .line-chart {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .line-chart svg {
            width: 100%;
            height: 100%;
            overflow: visible;
        }

        .line-chart-path {
            fill: none;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .line-chart-area {
            fill-opacity: 0.1;
        }

        .line-chart-point {
            fill: white;
            stroke-width: 3;
            r: 5;
            cursor: pointer;
            transition: r 0.2s ease;
        }

        .line-chart-point:hover {
            r: 7;
        }

        .chart-tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease;
            z-index: 1000;
            white-space: nowrap;
        }

        .chart-tooltip.active {
            opacity: 1;
        }

        /* Pie Chart */
        .pie-chart {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .pie-chart svg {
            width: 250px;
            height: 250px;
            transform: rotate(-90deg);
        }

        .pie-slice {
            transition: opacity 0.2s ease;
            cursor: pointer;
        }

        .pie-slice:hover {
            opacity: 0.8;
        }

        .pie-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #4a5568;
        }

        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease-out;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
    </style>
@endpush

@section('content')
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1" style="color: #2d3748; font-weight: 700;">Dashboard Overview</h1>
                <p class="text-muted mb-0">Welcome back! Here's what's happening with your business today.</p>
            </div>
            <div class="text-end">
                <p class="mb-0 text-muted" style="font-size: 13px;">
                    <i class="far fa-calendar-alt me-1"></i>
                    {{ now()->format('l, F j, Y') }}
                </p>
            </div>
        </div>

        <!-- Statistics Cards Row 1 -->
        <div class="row g-3 mb-4">
            <!-- Today's Sales -->
            @can('permission', 'dashboard.todays_sales')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-1">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-label">Today's Sales</div>
                    <div class="stat-value">{{ number_format($statistics['overview']['today_sales'], 2) }}</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>{{ $statistics['overview']['today_sales_count'] }} orders</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/sale') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Sales
                        </a>
                    </div>
                </div>
            </div>
            @endcan

            <!-- Total Products -->
            @can('permission', 'dashboard.total_products')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-2">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value">{{ $statistics['overview']['total_products'] }}</div>
                    <div class="stat-change">
                        <span style="color: #718096;">{{ $statistics['products']['in_stock'] }} in stock</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/product') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Products
                        </a>
                    </div>
                </div>
            </div>
            @endcan

            <!-- Total Leads -->
            @can('permission', 'dashboard.total_leads')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-3">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-label">Total Leads</div>
                    <div class="stat-value">{{ $statistics['overview']['total_leads'] }}</div>
                    <div class="stat-change positive">
                        <i class="fas fa-check-circle"></i>
                        <span>{{ $statistics['leads']['converted'] }} converted</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/leads') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Leads
                        </a>
                    </div>
                </div>
            </div>
            @endcan

            <!-- Total Customers -->
            @can('permission', 'dashboard.total_customers')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-4">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-label">Total Customers</div>
                    <div class="stat-value">{{ $statistics['overview']['total_customers'] }}</div>
                    <div class="stat-change">
                        <span style="color: #718096;">+{{ $statistics['customers']['this_month'] }} this month</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/customers') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Customers
                        </a>
                    </div>
                </div>
            </div>
            @endcan
        

        <!-- Statistics Cards Row 2 -->

            <!-- Tickets Summary -->
            @can('permission', 'dashboard.total_tickets')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-1 pt-3">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-label">Total Tickets</div>
                    <div class="stat-value">{{ $statistics['tickets']['total'] }}</div>
                    <div class="stat-change">
                        <span style="color: #718096;">{{ $statistics['tickets']['active'] }} active</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/ticket') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Tickets
                        </a>
                    </div>
                </div>
            </div>
            @endcan

            <!-- Tasks Summary -->
            @can('permission', 'dashboard.total_tasks')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-2 pt-3">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-label">Total Tasks</div>
                    <div class="stat-value">{{ $statistics['tasks']['total'] }}</div>
                    <div class="stat-change {{ $statistics['tasks']['overdue'] > 0 ? 'negative' : 'positive' }}">
                        <i class="fas fa-{{ $statistics['tasks']['overdue'] > 0 ? 'exclamation-circle' : 'check-circle' }}"></i>
                        <span>{{ $statistics['tasks']['overdue'] }} overdue</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/task') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Tasks
                        </a>
                    </div>
                </div>
            </div>
            @endcan

            <!-- Appointments Summary -->
            @can('permission', 'dashboard.appointments')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-3 pt-3">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-label">Appointments</div>
                    <div class="stat-value">{{ $statistics['appointments']['total'] }}</div>
                    <div class="stat-change">
                        <span style="color: #718096;">{{ $statistics['appointments']['today'] }} today</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/appointments') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Appointments
                        </a>
                    </div>
                </div>
            </div>
            @endcan

            <!-- Net Balance -->
            @can('permission', 'dashboard.net_balance')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-4 pt-3">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-label">Net Balance</div>
                    <div class="stat-value">{{ number_format($statistics['financials']['net_profit'], 2) }}</div>
                    <div class="stat-change {{ $statistics['financials']['net_profit'] >= 0 ? 'positive' : 'negative' }}">
                        <i class="fas fa-{{ $statistics['financials']['net_profit'] >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                        <span>This month</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/report/ledger') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Finance
                        </a>
                    </div>
                </div>
            </div>
            @endcan
        

        <!-- Statistics Cards Row 3 -->
        
            <!-- Total Sales -->
            @can('permission', 'dashboard.total_sales')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-1 pt-3">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value">{{ number_format($statistics['overview']['total_sales'], 2) }}</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>{{ $statistics['sales']['total']['count'] }} orders</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/sale') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Report
                        </a>
                    </div>
                </div>
            </div>
            @endcan

            <!-- Purchases -->
            @can('permission', 'dashboard.total_purchases')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-2 pt-3">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-label">Total Purchases</div>
                    <div class="stat-value">{{ number_format($statistics['purchases']['total']['amount'], 2) }}</div>
                    <div class="stat-change">
                        <span style="color: #718096;">Due: {{ number_format($statistics['purchases']['total']['due'], 2) }}</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/purchase') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Purchases
                        </a>
                    </div>
                </div>
            </div>
            @endcan

            <!-- Suppliers -->
            @can('permission', 'dashboard.suppliers')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-3 pt-3">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-label">Suppliers</div>
                    <div class="stat-value">{{ $statistics['suppliers']['total'] }}</div>
                    <div class="stat-change">
                        <span style="color: #718096;">{{ $statistics['suppliers']['pending'] }} pending</span>
                    </div>
                    <div class="mt-3">
                        <a href="{{ url('admin/suppliers') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Suppliers
                        </a>
                    </div>
                </div>
            </div>
            @endcan

            <!-- Membership & Referral Summary -->
            @can('permission', 'dashboard.membership')
            <div class="col-xl-3 col-md-6 animate-fadeInUp delay-4 pt-3">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-label">Membership & Referrals</div>
                    <div class="stat-value">{{ $statistics['membership']['referrals']['total'] }}</div>
                    <div class="stat-change">
                        <span style="color: #10b981;">{{ $statistics['membership']['referrals']['successful'] }} successful</span>
                    </div>
                    <div class="mt-3" style="font-size: 12px;">
                        <div class="mb-1">
                            <i class="fas fa-star text-warning"></i>
                            <strong>Points:</strong> {{ number_format($statistics['membership']['points']['issued']) }} / {{ number_format($statistics['membership']['points']['redeemed']) }}
                        </div>
                        @foreach($statistics['membership']['members_by_tier'] as $tier)
                        @if($tier->members_count > 0)
                        <div>
                            <i class="fas fa-user-check text-primary"></i>
                            {{ $tier->name }}: {{ number_format($tier->members_count) }}
                        </div>
                        @endif
                        @endforeach
                    </div>
                    <div class="mt-2">
                        <a href="{{ url('admin/report/membership-loyalty') }}" class="quick-link-btn">
                            <i class="fas fa-external-link-alt"></i> View Report
                        </a>
                    </div>
                </div>
            </div>
            @endcan
        </div>

        <!-- Charts Row -->
        <div class="row g-3 mb-4">
            <!-- Monthly Sales & Purchases Chart -->
            <div class="col-xl-8">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-chart-area me-2" style="color: #667eea;"></i>
                            Sales & Purchases Overview (Last 6 Months)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" id="salesPurchasesChart"></div>
                    </div>
                </div>
            </div>

            <!-- Lead Status Distribution -->
            <div class="col-xl-4">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-chart-pie me-2" style="color: #f5576c;"></i>
                            Lead Status Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" id="leadStatusChart"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Alerts Row -->
        @can('permission', 'dashboard.inventory')
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="alert-card">
                    <div class="card-header" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white; border-radius: 12px 12px 0 0;">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Inventory Alerts
                        </h5>
                    </div>
                    <div class="card-body" style="background: white; border: 1px solid #e2e8f0; border-radius: 0 0 12px 12px; padding: 20px;">
                        <div class="row">
                            <!-- Low Stock -->
                            <div class="col-md-4 mb-3">
                                <div class="alert-item" style="border-left: 4px solid #f59e0b; background: #fffbeb; padding: 15px; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                        <div style="width: 40px; height: 40px; background: #f59e0b; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <i class="fas fa-boxes" style="color: white; font-size: 18px;"></i>
                                        </div>
                                        <div>
                                            <h6 style="margin: 0; color: #92400e; font-weight: 600;">Low Stock</h6>
                                            <span style="font-size: 20px; font-weight: 700; color: #f59e0b;">{{ $statistics['inventory']['low_stock']['count'] }}</span>
                                        </div>
                                    </div>
                                    @if($statistics['inventory']['low_stock']['products']->count() > 0)
                                    <small style="color: #78716c;">
                                        @foreach($statistics['inventory']['low_stock']['products']->take(3) as $product)
                                        <div style="padding: 4px 0; border-bottom: 1px solid #fcd34d;">
                                            <strong>{{ $product['product_name'] }}</strong>
                                            <span class="badge badge-warning" style="float: right;">{{ $product['stock'] }} units</span>
                                        </div>
                                        @endforeach
                                        @if($statistics['inventory']['low_stock']['products']->count() > 3)
                                        <div style="padding: 4px 0; color: #92400e;">
                                            ...and {{ $statistics['inventory']['low_stock']['products']->count() - 3 }} more
                                        </div>
                                        @endif
                                    </small>
                                    @else
                                    <small style="color: #10b981;">No low stock items</small>
                                    @endif
                                </div>
                            </div>

                            <!-- Fast Moving -->
                            <div class="col-md-4 mb-3">
                                <div class="alert-item" style="border-left: 4px solid #10b981; background: #d1fae5; padding: 15px; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                        <div style="width: 40px; height: 40px; background: #10b981; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <i class="fas fa-rocket" style="color: white; font-size: 18px;"></i>
                                        </div>
                                        <div>
                                            <h6 style="margin: 0; color: #065f46; font-weight: 600;">Fast Moving</h6>
                                            <span style="font-size: 20px; font-weight: 700; color: #10b981;">{{ $statistics['inventory']['fast_moving']['count'] }}</span>
                                        </div>
                                    </div>
                                    @if($statistics['inventory']['fast_moving']['products']->count() > 0)
                                    <small style="color: #064e3b;">
                                        @foreach($statistics['inventory']['fast_moving']['products']->take(3) as $product)
                                        <div style="padding: 4px 0; border-bottom: 1px solid #6ee7b7;">
                                            <strong>{{ $product['product_name'] }}</strong>
                                            <span class="badge badge-success" style="float: right;">{{ $product['qty_sold'] }} sold</span>
                                        </div>
                                        @endforeach
                                        @if($statistics['inventory']['fast_moving']['products']->count() > 3)
                                        <div style="padding: 4px 0; color: #065f46;">
                                            ...and {{ $statistics['inventory']['fast_moving']['products']->count() - 3 }} more
                                        </div>
                                        @endif
                                    </small>
                                    @else
                                    <small style="color: #047857;">No sales data yet</small>
                                    @endif
                                </div>
                            </div>

                            <!-- Purchase Needed -->
                            <div class="col-md-4 mb-3">
                                <div class="alert-item" style="border-left: 4px solid #ef4444; background: #fee2e2; padding: 15px; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                        <div style="width: 40px; height: 40px; background: #ef4444; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                            <i class="fas fa-shopping-cart" style="color: white; font-size: 18px;"></i>
                                        </div>
                                        <div>
                                            <h6 style="margin: 0; color: #991b1b; font-weight: 600;">Purchase Needed</h6>
                                            <span style="font-size: 20px; font-weight: 700; color: #ef4444;">{{ $statistics['inventory']['purchase_needed']['count'] }}</span>
                                        </div>
                                    </div>
                                    @if($statistics['inventory']['purchase_needed']['products']->count() > 0)
                                    <small style="color: #7f1d1d;">
                                        @foreach($statistics['inventory']['purchase_needed']['products']->take(3) as $product)
                                        <div style="padding: 4px 0; border-bottom: 1px solid #fca5a5;">
                                            <strong>{{ $product['product_name'] }}</strong>
                                            @if($product['stock'] <= 0)
                                            <span class="badge badge-danger" style="float: right;">Out of stock</span>
                                            @else
                                            <span class="badge badge-warning" style="float: right;">{{ $product['stock'] }} left</span>
                                            @endif
                                        </div>
                                        @endforeach
                                        @if($statistics['inventory']['purchase_needed']['products']->count() > 3)
                                        <div style="padding: 4px 0; color: #b91c1c;">
                                            ...and {{ $statistics['inventory']['purchase_needed']['products']->count() - 3 }} more
                                        </div>
                                        @endif
                                    </small>
                                    @else
                                    <small style="color: #047857;">All products well stocked</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12 text-right">
                                <a href="{{ url('admin/product') }}" class="btn btn-sm" style="background: #ff6b6b; color: white; border-radius: 6px;">
                                    <i class="fas fa-list me-1"></i> View All Products
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endcan

        <!-- Performance Scoreboard Row -->
        @can('permission', 'dashboard.performance')
        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="alert-card">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px 12px 0 0;">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Performance Scoreboard
                        </h5>
                    </div>
                    <div class="card-body" style="background: white; border: 1px solid #e2e8f0; border-radius: 0 0 12px 12px; padding: 20px;">
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="performance-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; color: white;">
                                    <div class="d-flex align-items-center">
                                        <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                            <i class="fas fa-percentage" style="font-size: 24px;"></i>
                                        </div>
                                        <div>
                                            <h6 style="margin: 0; opacity: 0.9;">Avg Conversion Rate</h6>
                                            <h3 style="margin: 5px 0 0 0;">{{ number_format($statistics['performance']['averages']['conversion_rate'], 2) }}%</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="performance-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px; border-radius: 12px; color: white;">
                                    <div class="d-flex align-items-center">
                                        <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                            <i class="fas fa-dollar-sign" style="font-size: 24px;"></i>
                                        </div>
                                        <div>
                                            <h6 style="margin: 0; opacity: 0.9;">Total Revenue</h6>
                                            <h3 style="margin: 5px 0 0 0;">{{ number_format($statistics['performance']['averages']['total_revenue'], 2) }}</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="performance-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 20px; border-radius: 12px; color: white;">
                                    <div class="d-flex align-items-center">
                                        <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                            <i class="fas fa-tasks" style="font-size: 24px;"></i>
                                        </div>
                                        <div>
                                            <h6 style="margin: 0; opacity: 0.9;">Follow-up Compliance</h6>
                                            <h3 style="margin: 5px 0 0 0;">{{ number_format($statistics['performance']['averages']['follow_up_compliance'], 2) }}%</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Performer Section -->
                        @if($statistics['performance']['top_performer'])
                        <div class="row mb-4">
                            <div class="col-12">
                                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                        <div>
                                            <h6 style="margin: 0; color: #92400e; font-weight: 600;">
                                                <i class="fas fa-crown me-2"></i>Top Performer
                                            </h6>
                                            <h4 style="margin: 5px 0; color: #b45309;">
                                                {{ $statistics['performance']['top_performer']['staff_name'] }}
                                            </h4>
                                            <small style="color: #78716c;">
                                                Conversion: {{ number_format($statistics['performance']['top_performer']['conversion_rate'], 2) }}% |
                                                Revenue: {{ number_format($statistics['performance']['top_performer']['total_revenue'], 2) }} |
                                                Compliance: {{ number_format($statistics['performance']['top_performer']['follow_up_compliance'], 2) }}%
                                            </small>
                                        </div>
                                        <div style="text-align: right;">
                                            <span class="badge" style="background: #f59e0b; color: white; font-size: 14px; padding: 8px 16px;">
                                                <i class="fas fa-star me-1"></i> #1
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Staff Performance Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" style="border-radius: 8px; overflow: hidden;">
                                <thead>
                                    <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                        <th style="border: none;"><i class="fas fa-user me-2"></i>Staff</th>
                                        <th style="border: none; text-center;"><i class="fas fa-users me-2"></i>Leads</th>
                                        <th style="border: none; text-center;"><i class="fas fa-trophy me-2"></i>Won</th>
                                        <th style="border: none; text-center;"><i class="fas fa-percentage me-2"></i>Conversion %</th>
                                        <th style="border: none; text-right;"><i class="fas fa-dollar-sign me-2"></i>Revenue</th>
                                        <th style="border: none; text-center;"><i class="fas fa-check-circle me-2"></i>Follow-ups</th>
                                        <th style="border: none; text-center;"><i class="fas fa-clipboard-check me-2"></i>Compliance %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($statistics['performance']['staff_performance']->count() > 0)
                                        @foreach($statistics['performance']['staff_performance'] as $index => $staff)
                                        <tr style="{{ $index === 0 ? 'background: #f0fdf4;' : '' }}">
                                            <td>
                                                @if($index === 0)
                                                <i class="fas fa-crown text-warning me-2"></i>
                                                @endif
                                                <strong>{{ $staff['staff_name'] }}</strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge" style="background: #e2e8f0; color: #475569;">
                                                    {{ number_format($staff['total_leads']) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-success">
                                                    {{ number_format($staff['won_leads']) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 8px; margin-bottom: 5px;">
                                                    <div class="progress-bar {{ $staff['conversion_rate'] >= 50 ? 'bg-success' : ($staff['conversion_rate'] >= 25 ? 'bg-warning' : 'bg-danger') }}"
                                                         style="width: {{ min($staff['conversion_rate'], 100) }}%"></div>
                                                </div>
                                                <small>{{ number_format($staff['conversion_rate'], 2) }}%</small>
                                            </td>
                                            <td class="text-right">
                                                <strong style="color: #059669;">{{ number_format($staff['total_revenue'], 2) }}</strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge" style="background: #dbeafe; color: #1e40af;">
                                                    {{ number_format($staff['follow_ups_completed']) }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 8px; margin-bottom: 5px;">
                                                    <div class="progress-bar {{ $staff['follow_up_compliance'] >= 80 ? 'bg-success' : ($staff['follow_up_compliance'] >= 50 ? 'bg-warning' : 'bg-danger') }}"
                                                         style="width: {{ min($staff['follow_up_compliance'], 100) }}%"></div>
                                                </div>
                                                <small>{{ number_format($staff['follow_up_compliance'], 2) }}%</small>
                                            </td>
                                        </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <p class="text-muted mb-0">
                                                    <i class="fas fa-info-circle me-2"></i>No performance data available
                                                </p>
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
        @endcan

        <!-- Bottom Row -->
        <div class="row g-3">
            <!-- Top Selling Products -->
            <div class="col-xl-6">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-trophy me-2" style="color: #fbbf24;"></i>
                            Top Selling Products (This Month)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" id="topProductsChart"></div>
                    </div>
                </div>
            </div>

            <!-- Weekly Sales Chart -->
            <div class="col-xl-6">
                <div class="chart-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-chart-bar me-2" style="color: #10b981;"></i>
                            Weekly Sales Trend
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" id="weeklySalesChart"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Chart data from PHP
            const monthlySales = {!! json_encode($statistics['charts']['monthly_sales']) !!};
            const monthlyPurchases = {!! json_encode($statistics['charts']['monthly_purchases']) !!};
            const leadStatusData = {!! json_encode($statistics['charts']['lead_status_distribution']) !!};
            const topProducts = {!! json_encode($statistics['sales']['top_products']) !!};
            const weeklySales = {!! json_encode($statistics['charts']['weekly_sales']) !!};

            // Tooltip element
            const tooltip = document.createElement('div');
            tooltip.className = 'chart-tooltip';
            document.body.appendChild(tooltip);

            // Line Chart - Sales & Purchases
            function createLineChart(containerId, salesData, purchasesData) {
                const container = document.getElementById(containerId);
                if (!container || salesData.length === 0) return;

                const labels = [];
                const sales = [];
                const purchases = [];

                // Prepare data
                for (let i = 5; i >= 0; i--) {
                    const date = new Date();
                    date.setMonth(date.getMonth() - i);
                    const monthStr = date.toISOString().slice(0, 7);
                    labels.push(date.toLocaleString('default', { month: 'short', year: '2-digit' }));

                    const salesMonth = salesData.find(item => item.month === monthStr);
                    sales.push(salesMonth ? parseFloat(salesMonth.total) : 0);

                    const purchaseMonth = purchasesData.find(item => item.month === monthStr);
                    purchases.push(purchaseMonth ? parseFloat(purchaseMonth.total) : 0);
                }

                const maxValue = Math.max(...sales, ...purchases);
                const width = container.offsetWidth;
                const height = container.offsetHeight;
                const padding = { top: 20, right: 20, bottom: 40, left: 60 };

                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
                container.appendChild(svg);

                // Grid lines
                for (let i = 0; i <= 5; i++) {
                    const y = padding.top + (height - padding.top - padding.bottom) * (i / 5);
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', padding.left);
                    line.setAttribute('y1', y);
                    line.setAttribute('x2', width - padding.right);
                    line.setAttribute('y2', y);
                    line.setAttribute('stroke', '#e2e8f0');
                    line.setAttribute('stroke-width', '1');
                    svg.appendChild(line);

                    // Y-axis labels
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', padding.left - 10);
                    text.setAttribute('y', y + 4);
                    text.setAttribute('text-anchor', 'end');
                    text.setAttribute('font-size', '11');
                    text.setAttribute('fill', '#718096');
                    text.textContent = Math.round(maxValue * (5 - i) / 5);
                    svg.appendChild(text);
                }

                // X-axis labels
                labels.forEach((label, i) => {
                    const x = padding.left + (width - padding.left - padding.right) * (i / (labels.length - 1));
                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.setAttribute('x', x);
                    text.setAttribute('y', height - padding.bottom + 20);
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('font-size', '11');
                    text.setAttribute('fill', '#718096');
                    text.textContent = label;
                    svg.appendChild(text);
                });

                // Draw lines
                function createPath(data, color) {
                    if (data.length === 0) return '';

                    let path = '';
                    data.forEach((value, i) => {
                        const x = padding.left + (width - padding.left - padding.right) * (i / (data.length - 1));
                        const y = padding.top + (height - padding.top - padding.bottom) * (1 - value / maxValue);

                        if (i === 0) {
                            path += `M ${x} ${y}`;
                        } else {
                            path += ` L ${x} ${y}`;
                        }
                    });

                    return path;
                }

                // Sales line
                const salesPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                salesPath.setAttribute('d', createPath(sales, '#667eea'));
                salesPath.setAttribute('class', 'line-chart-path');
                salesPath.setAttribute('stroke', '#667eea');
                svg.appendChild(salesPath);

                // Purchases line
                const purchasesPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                purchasesPath.setAttribute('d', createPath(purchases, '#10b981'));
                purchasesPath.setAttribute('class', 'line-chart-path');
                purchasesPath.setAttribute('stroke', '#10b981');
                svg.appendChild(purchasesPath);

                // Draw points
                sales.forEach((value, i) => {
                    const x = padding.left + (width - padding.left - padding.right) * (i / (sales.length - 1));
                    const y = padding.top + (height - padding.top - padding.bottom) * (1 - value / maxValue);

                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('cx', x);
                    circle.setAttribute('cy', y);
                    circle.setAttribute('class', 'line-chart-point');
                    circle.setAttribute('stroke', '#667eea');
                    circle.addEventListener('mouseenter', (e) => {
                        tooltip.textContent = `Sales: ${value.toFixed(2)}`;
                        tooltip.classList.add('active');
                        tooltip.style.left = e.pageX + 10 + 'px';
                        tooltip.style.top = e.pageY - 30 + 'px';
                    });
                    circle.addEventListener('mouseleave', () => {
                        tooltip.classList.remove('active');
                    });
                    svg.appendChild(circle);
                });

                purchases.forEach((value, i) => {
                    const x = padding.left + (width - padding.left - padding.right) * (i / (purchases.length - 1));
                    const y = padding.top + (height - padding.top - padding.bottom) * (1 - value / maxValue);

                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('cx', x);
                    circle.setAttribute('cy', y);
                    circle.setAttribute('class', 'line-chart-point');
                    circle.setAttribute('stroke', '#10b981');
                    circle.addEventListener('mouseenter', (e) => {
                        tooltip.textContent = `Purchases: ${value.toFixed(2)}`;
                        tooltip.classList.add('active');
                        tooltip.style.left = e.pageX + 10 + 'px';
                        tooltip.style.top = e.pageY - 30 + 'px';
                    });
                    circle.addEventListener('mouseleave', () => {
                        tooltip.classList.remove('active');
                    });
                    svg.appendChild(circle);
                });

                // Legend
                const legend = document.createElement('div');
                legend.className = 'pie-legend';
                legend.innerHTML = `
                    <div class="legend-item">
                        <div class="legend-color" style="background: #667eea;"></div>
                        <span>Sales</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #10b981;"></div>
                        <span>Purchases</span>
                    </div>
                `;
                container.appendChild(legend);
            }

            // Pie Chart - Lead Status Distribution
            function createPieChart(containerId, data) {
                const container = document.getElementById(containerId);
                if (!container || data.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-chart-pie fa-3x mb-3"></i><p>No data available</p></div>';
                    return;
                }

                const total = data.reduce((sum, item) => sum + item.count, 0);
                const colors = ['#667eea', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
                const size = 250;
                const center = size / 2;
                const radius = 100;

                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svg.setAttribute('width', size);
                svg.setAttribute('height', size);
                svg.setAttribute('viewBox', `0 0 ${size} ${size}`);

                let currentAngle = 0;

                data.forEach((item, i) => {
                    const percentage = item.count / total;
                    const angle = percentage * Math.PI * 2;
                    const x1 = center + radius * Math.cos(currentAngle);
                    const y1 = center + radius * Math.sin(currentAngle);
                    const x2 = center + radius * Math.cos(currentAngle + angle);
                    const y2 = center + radius * Math.sin(currentAngle + angle);

                    const largeArcFlag = angle > Math.PI ? 1 : 0;

                    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    path.setAttribute('d', `M ${center} ${center} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArcFlag} 1 ${x2} ${y2} Z`);
                    path.setAttribute('fill', colors[i]);
                    path.setAttribute('class', 'pie-slice');

                    const status = item.lead_status.replace(/_/g, ' ');
                    const label = status.charAt(0).toUpperCase() + status.slice(1);
                    path.addEventListener('mouseenter', (e) => {
                        tooltip.textContent = `${label}: ${item.count} (${(percentage * 100).toFixed(1)}%)`;
                        tooltip.classList.add('active');
                        tooltip.style.left = e.pageX + 10 + 'px';
                        tooltip.style.top = e.pageY - 30 + 'px';
                    });
                    path.addEventListener('mouseleave', () => {
                        tooltip.classList.remove('active');
                    });

                    svg.appendChild(path);
                    currentAngle += angle;
                });

                const pieContainer = document.createElement('div');
                pieContainer.className = 'pie-chart';
                pieContainer.appendChild(svg);
                container.appendChild(pieContainer);

                // Legend
                const legend = document.createElement('div');
                legend.className = 'pie-legend';
                data.forEach((item, i) => {
                    const status = item.lead_status.replace(/_/g, ' ');
                    const label = status.charAt(0).toUpperCase() + status.slice(1);
                    legend.innerHTML += `
                        <div class="legend-item">
                            <div class="legend-color" style="background: ${colors[i]};"></div>
                            <span>${label} (${item.count})</span>
                        </div>
                    `;
                });
                container.appendChild(legend);
            }

            // Bar Chart - Top Products
            function createBarChart(containerId, data, horizontal = false) {
                const container = document.getElementById(containerId);
                if (!container || data.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-5"><i class="fas fa-box-open fa-3x mb-3"></i><p>No data available</p></div>';
                    return;
                }

                const maxValue = Math.max(...data.map(item => item.total_qty || parseFloat(item.total)));
                const colors = ['#667eea', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

                const chartDiv = document.createElement('div');
                chartDiv.className = 'bar-chart';

                data.forEach((item, i) => {
                    const value = item.total_qty || parseFloat(item.total);
                    const percentage = (value / maxValue) * 100;
                    const label = item.product_name || item.day;

                    const barGroup = document.createElement('div');
                    barGroup.className = 'bar-group';

                    const bar = document.createElement('div');
                    bar.className = 'bar';
                    bar.style.height = percentage + '%';
                    bar.style.background = `linear-gradient(180deg, ${colors[i]} 0%, ${colors[i]}dd 100%)`;

                    bar.addEventListener('mouseenter', (e) => {
                        tooltip.textContent = `${label}: ${value}`;
                        tooltip.classList.add('active');
                        tooltip.style.left = e.pageX + 10 + 'px';
                        tooltip.style.top = e.pageY - 30 + 'px';
                    });
                    bar.addEventListener('mouseleave', () => {
                        tooltip.classList.remove('active');
                    });

                    const valueSpan = document.createElement('div');
                    valueSpan.className = 'bar-value';
                    valueSpan.textContent = value;
                    bar.appendChild(valueSpan);

                    const labelDiv = document.createElement('div');
                    labelDiv.className = 'bar-label';
                    labelDiv.textContent = typeof label === 'string' && label.length > 15 ? label.substring(0, 15) + '...' : label;

                    barGroup.appendChild(bar);
                    barGroup.appendChild(labelDiv);
                    chartDiv.appendChild(barGroup);
                });

                container.appendChild(chartDiv);
            }

            // Create charts
            createLineChart('salesPurchasesChart', monthlySales, monthlyPurchases);
            createPieChart('leadStatusChart', leadStatusData);
            createBarChart('topProductsChart', topProducts.map(p => ({ product_name: p.product_name, total_qty: p.total_qty })));

            // Weekly sales chart
            const weeklySalesData = weeklySales.map(item => ({
                day: new Date(item.day).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }),
                total: parseFloat(item.total)
            }));
            createBarChart('weeklySalesChart', weeklySalesData);
        });
    </script>
@endpush
