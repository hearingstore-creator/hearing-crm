
@php
    $currentCategory = null;

    // Submenu icon map: matched by route when $child->icon is not set in database
    $submenuIcons = [
        // Leads
        'admin/deals'                           => 'fa-columns',
        'admin/leads/create'                    => 'fa-user-plus',
        'admin/leads'                           => 'fa-list',
        'admin/pipeline-settings'               => 'fa-sliders-h',
        'admin/web-leads'                       => 'fa-globe',
        'admin/deal-reports'                    => 'fa-chart-line',

        // Customers
        'admin/customers/create'                => 'fa-user-plus',
        'admin/customers'                       => 'fa-list',
        'admin/customer-ledger/create'          => 'fa-book',
        'admin/customer-payment'                => 'fa-money-bill-wave',

        // Doctors & Referrals
        'admin/doctor/create'                   => 'fa-user-plus',
        'admin/doctor'                          => 'fa-list',
        'admin/doctors-payment'                 => 'fa-money-bill-wave',
        'admin/report/doctor-referral'          => 'fa-chart-pie',

        // Branches (Vendors)
        'admin/vendors/create'                  => 'fa-plus-circle',
        'admin/vendors'                         => 'fa-list',

        // Suppliers
        'admin/suppliers/create'                => 'fa-plus-circle',
        'admin/suppliers'                       => 'fa-list',
        'admin/suppliers-payment'               => 'fa-money-bill-wave',
        'admin/report/supplier-statement'       => 'fa-file-invoice-dollar',

        // Sales
        'admin/sale/create'                     => 'fa-cart-plus',
        'admin/sale'                            => 'fa-list',
        'admin/sales/due-list'                  => 'fa-file-invoice-dollar',
        'admin/service-type'                    => 'fa-concierge-bell',
        'admin/sale-returns'                    => 'fa-undo',
        'admin/sales/pending-services'          => 'fa-clock',

        // Proposals
        'admin/template'                        => 'fa-file-alt',
        'admin/proposal'                        => 'fa-list',

        // Purchases
        'admin/purchase/create'                 => 'fa-plus-circle',
        'admin/purchase'                        => 'fa-list',
        'admin/purchase-returns'                => 'fa-undo',

        // Inventory
        'admin/product'                         => 'fa-box-open',
        'admin/category'                        => 'fa-tags',
        'admin/type'                            => 'fa-layer-group',
        'admin/brand'                           => 'fa-copyright',
        'admin/stock-adjustments'               => 'fa-balance-scale-left',
        'admin/stock-transfers'                 => 'fa-exchange-alt',

        // Appointments
        'admin/appointments/create'             => 'fa-calendar-plus',
        'admin/appointments/today'              => 'fa-tasks',
        'admin/appointments'                    => 'fa-list',
        'admin/appointments/calendar'           => 'fa-calendar-alt',
        'admin/roster'                          => 'fa-user-clock',
        'admin/professionals'                   => 'fa-user-md',
        'admin/appointment-services'            => 'fa-stethoscope',
        'admin/sound-booths'                    => 'fa-door-closed',

        // Support
        'admin/customer-lookup'                 => 'fa-search',
        'admin/ticket/create'                   => 'fa-plus-circle',
        'admin/ticket'                          => 'fa-ticket-alt',
        'admin/task/create'                     => 'fa-plus-circle',
        'admin/task'                            => 'fa-tasks',
        'admin/support-settings'                => 'fa-sliders-h',

        // Accounts
        'admin/report/ledger'                   => 'fa-book',
        'admin/income-expense-head'             => 'fa-sitemap',
        'admin/expense'                         => 'fa-receipt',
        'admin/payment-reconciliation'          => 'fa-balance-scale',

        // Repair & Service
        'admin/repairs'                         => 'fa-columns',
        'admin/repairs/create'                  => 'fa-plus-circle',
        'admin/repairs/quick'                   => 'fa-bolt',
        'admin/repairs/lookup'                  => 'fa-barcode',
        'admin/repairs/rma'                     => 'fa-exchange-alt',
        'admin/repairs/supplier-reconciliation' => 'fa-file-invoice-dollar',
        'admin/repairs/history'                 => 'fa-history',

        // Membership & Rewards
        'admin/membership/tiers'                => 'fa-crown',
        'admin/membership/milestones'           => 'fa-flag-checkered',
        'admin/membership/settings'             => 'fa-sliders-h',
        'admin/membership/wallets'              => 'fa-wallet',
        'admin/membership/reward-ledger'        => 'fa-book',
        'admin/membership/referrals'            => 'fa-user-friends',
        'admin/membership/upgrades'             => 'fa-arrow-circle-up',

        // SMS Centre
        'admin/sms'                             => 'fa-paper-plane',
        'admin/sms-templates'                   => 'fa-file-alt',
        'admin/sms-log'                         => 'fa-list-alt',
        'admin/sms-analytics'                   => 'fa-chart-bar',

        // Reports
        'admin/report/stock'                    => 'fa-boxes',
        'admin/report/purchase-stock'           => 'fa-shopping-cart',
        'admin/report/purchase-summary'         => 'fa-file-invoice',
        'admin/report/vendor-monthly'           => 'fa-chart-line',
        'admin/report/warranty-expiry'          => 'fa-shield-alt',
        'admin/report/battery-usage-expiry'     => 'fa-battery-half',
        'admin/report/sales-performance'        => 'fa-chart-pie',
        'admin/report/membership-loyalty'       => 'fa-star',
        'admin/report/lead-funnel'              => 'fa-filter',
        'admin/report/advances'                 => 'fa-hand-holding-usd',
        'admin/report/services-status'          => 'fa-stethoscope',

        // Administration / Utilities
        'admin/setting/create'                  => 'fa-sliders-h',
        'admin/roles'                           => 'fa-user-shield',
        'admin/user'                            => 'fa-users-cog',
        'admin/activity-log'                    => 'fa-history',
        'admin/database-backup'                 => 'fa-database',
    ];
@endphp
@foreach ($menus as $menu)
    {{-- Render category header when category changes (only for top-level menus) --}}
    @if ($menu->category && $menu->category !== $currentCategory)
        @php $currentCategory = $menu->category; @endphp
        <li class="nav-header">{{ $currentCategory }}</li>
    @endif

    @php
        // Check if this parent menu should be open
        $isActiveParent = false;

        // Check parent route wildcard
        if (!empty($menu->route) && request()->is(trim($menu->route, '/') . '*')) {
            $isActiveParent = true;
        }

        // Check if any child (recursively) is active
        if (!$isActiveParent && $menu->children->isNotEmpty()) {
            foreach ($menu->children as $child) {
                if (!empty($child->route) && request()->is(trim($child->route, '/') . '*')) {
                    $isActiveParent = true;
                    break;
                }
            }
        }
    @endphp

    <li class="nav-item {{ $isActiveParent ? 'menu-open' : '' }}">
        <a href="{{ $menu->route ? url($menu->route) : '#' }}" 
           class="nav-link {{ $isActiveParent ? 'active' : '' }}">
            <i class="nav-icon fas {{ $menu->icon }}"></i>
            <p>
                {{ $menu->display_name }}
                @if ($menu->children->isNotEmpty())
                    <i class="fas fa-angle-left right"></i>
                @endif
            </p>
        </a>

        @if ($menu->children->isNotEmpty())
            <ul class="nav nav-treeview">
                @foreach ($menu->children as $child)
                    @if ($child->children->isEmpty())
                        {{-- Child Menu (Exact Match) --}}
                        @php
                            $childRouteKey = trim($child->route ?? '', '/');
                            $childRawIcon = !empty($child->icon)
                                ? $child->icon
                                : ($submenuIcons[$childRouteKey] ?? ($submenuIcons[$child->route ?? ''] ?? null));

                            if (!empty($childRawIcon)) {
                                $childIconClass = (strpos($childRawIcon, 'fa-') !== false && strpos($childRawIcon, 'fa') === 0)
                                    ? 'fas ' . $childRawIcon
                                    : $childRawIcon;
                            } else {
                                $childIconClass = 'far fa-circle';
                            }
                        @endphp
                        <li class="nav-item">
                            <a href="{{ url($child->route) }}"
                               class="nav-link {{ request()->is(trim($child->route, '/')) ? 'active' : '' }}">
                                <i class="nav-icon {{ $childIconClass }}"></i>
                                <p>{{ $child->display_name }}</p>
                            </a>
                        </li>
                    @else
                        {{-- Recursive call for sub-children --}}
                        @include('admin.partials.sidebar-menu', ['menus' => collect([$child])])
                    @endif
                @endforeach
            </ul>
        @endif
    </li>
@endforeach
