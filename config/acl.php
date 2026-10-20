<?php

/**
 * ACL Menu Configuration
 *
 * 'name'     — Internal key used for permission name generation (DO NOT rename without updating controllers/views)
 * 'label'    — Display name shown in sidebar and role permission UI (safe to rename anytime)
 * 'category' — Visual group header in sidebar (AdminLTE nav-header)
 */

return [
    'menus' => [
        // ═══════════════════════════════════════════════
        //   CRM
        // ═══════════════════════════════════════════════
        [
            'name' => 'Dashboard',
            'route' => 'admin/dashboard',
            'icon' => 'fa-tachometer-alt',
            'order' => 1,
            'category' => 'CRM',
            'children' => [],
            // Each action = one dashboard widget, toggled per role in Roles → Manage Permissions.
            // Existing names are preserved so current role assignments keep working.
            'actions' => [
                'index',                // access the dashboard page
                'view_all_data',        // see company-wide numbers (otherwise: own records only)
                'todays_sales', 'total_sales', 'total_leads', 'total_customers', 'total_products',
                'total_tickets', 'total_tasks',
                'daily_lookout', 'follow_ups', 'appointments', 'warranty_alerts',
                'marketing_insights', 'inventory', 'revenue_chart', 'lead_pipeline', 'lead_charts',
                'net_balance', 'total_purchases', 'performance',
                'suppliers', 'membership',
                // Phase 1 analytics widgets (new — off by default for every role except Super Admin)
                'smart_alerts',
                'top_customers', 'customer_rfm', 'new_vs_returning', 'dormant_customers', 'membership_breakdown', 'points_liability',
                'upgrade_due', 'binaural_opportunity',
                'top_products', 'gross_profit', 'aov_trend', 'receivables_aging', 'discount_leakage', 'payment_mix', 'branch_leaderboard',
                'audience_builder',
                'top_doctors', 'inactive_doctors', 'referral_trend',
                'dead_stock', 'branch_stock', 'supplier_aging', 'appointment_utilisation', 'ticket_sla', 'expense_revenue', 'cash_today',
                // Phase 2
                'birthdays', 'customer_geography', 'service_due', 'test_to_sale_funnel', 'lead_response_time', 'lost_reasons', 'commission_due',
            ]
        ],
        [
            'name' => 'Leads',
            'icon' => 'fa-user-tie',
            'order' => 2,
            'category' => 'CRM',
            'children' => [
                [
                    // Pipeline board (Person + Deal). transfer = hand a deal to a branch (Head Office only)
                    'name' => 'Deal Board',
                    'label' => 'Deals Board',
                    'route' => 'admin/deals',
                    'actions' => ['index', 'move', 'won', 'lost', 'transfer']
                ],
                [
                    'name' => 'Add Lead',
                    'label' => 'New Lead',
                    'route' => 'admin/leads/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Lead List',
                    'label' => 'All Leads',
                    'route' => 'admin/leads',
                    'actions' => ['index', 'view', 'edit', 'delete', 'import', 'export']
                ],
                [
                    // Stages, gates, lost reasons, default owner
                    'name' => 'Pipeline Settings',
                    'label' => 'Pipeline Settings',
                    'route' => 'admin/pipeline-settings',
                    'actions' => ['manage']
                ],
                [
                    // Website leads inbox (index); capture forms, secrets and blocklist (manage). Head Office only.
                    'name' => 'Web Leads',
                    'label' => 'Website Leads',
                    'route' => 'admin/web-leads',
                    'actions' => ['index', 'manage']
                ],
                [
                    // Funnel, time in stage, speed-to-lead, lost reasons, campaign / branch / owner, forecast
                    'name' => 'Deal Reports',
                    'label' => 'Deal Reports',
                    'route' => 'admin/deal-reports',
                    'actions' => ['index']
                ],
            ]
        ],
        [
            'name' => 'Customer',
            'label' => 'Customers',
            'icon' => 'fa-users',
            'order' => 3,
            'category' => 'CRM',
            'children' => [
                [
                    'name' => 'Add Customer',
                    'label' => 'New Customer',
                    'route' => 'admin/customers/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Customer List',
                    'label' => 'All Customers',
                    'route' => 'admin/customers',
                    'actions' => ['index', 'edit', 'delete', 'import']
                ],
                [
                    // Hotline: read-only customer 360 (invoices, devices, warranty, tickets) across branches
                    'name' => 'Customer Lookup',
                    'label' => 'Customer Lookup',
                    'route' => 'admin/customer-lookup',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Customer Ledger',
                    'label' => 'Customer Ledger',
                    'route' => 'admin/customer-ledger/create',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Customer Payment',
                    'label' => 'Customer Payment',
                    'route' => 'admin/customer-payment',
                    'actions' => ['create']
                ],
            ]
        ],
        [
            'name' => 'Doctor',
            'label' => 'Doctors & Referrals',
            'icon' => 'fa-user-md',
            'order' => 4,
            'category' => 'CRM',
            'children' => [
                [
                    'name' => 'Add Doctor',
                    'label' => 'New Doctor',
                    'route' => 'admin/doctor/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Doctor List',
                    'label' => 'All Doctors',
                    'route' => 'admin/doctor',
                    'actions' => ['index', 'edit', 'delete']
                ],
                [
                    'name' => 'Doctor Payment',
                    'label' => 'Doctor Payments',
                    'route' => 'admin/doctors-payment',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Doctor & Referral',
                    'label' => 'Referral Report',
                    'route' => 'admin/report/doctor-referral',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Doctor & Referral Statement',
                    'label' => 'Doctor Statement',
                    'route' => 'admin/report/doctor-statement',
                    'icon' => 'fa-file-invoice',
                    'permission' => 'doctor_statement',
                    'actions' => ['index']
                ],
            ]
        ],
        [
            'name' => 'Vendor',
            'label' => 'Branches',
            'icon' => 'fa-truck',
            'order' => 5,
            'category' => 'CRM',
            'children' => [
                [
                    'name' => 'Add Vendor',
                    'label' => 'New Branch',
                    'route' => 'admin/vendors/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Vendor List',
                    'label' => 'All Branches',
                    'route' => 'admin/vendors',
                    // view_all_branches = Head-Office user can READ every branch's sales/purchases (read-only)
                    'actions' => ['index', 'edit', 'delete', 'view_all_branches']
                ],
            ]
        ],
        [
            'name' => 'Supplier',
            'label' => 'Suppliers',
            'icon' => 'fa-building',
            'order' => 6,
            'category' => 'CRM',
            'children' => [
                [
                    'name' => 'Add Supplier',
                    'label' => 'New Supplier',
                    'route' => 'admin/suppliers/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Supplier List',
                    'label' => 'All Suppliers',
                    'route' => 'admin/suppliers',
                    'actions' => ['index', 'edit', 'delete']
                ],
                [
                    'name' => 'Supplier Payment',
                    'label' => 'Supplier Payments',
                    'route' => 'admin/suppliers-payment',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Supplier Statement',
                    'label' => 'Supplier Statement',
                    'route' => 'admin/report/supplier-statement',
                    'actions' => ['index']
                ],
            ]
        ],

        // ═══════════════════════════════════════════════
        //   SALES & COMMERCE
        // ═══════════════════════════════════════════════
        [
            'name' => 'Sales',
            'icon' => 'fa-file-invoice',
            'order' => 7,
            'category' => 'SALES & COMMERCE',
            'children' => [
                [
                    'name' => 'Add Sale',
                    'label' => 'New Sale',
                    'route' => 'admin/sale/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Sale List',
                    'label' => 'All Sales',
                    'route' => 'admin/sale',
                    'actions' => ['index', 'view', 'edit', 'delete']
                ],
                [
                    'name' => 'Patient Due List',
                    'label' => 'Patient Due List',
                    'route' => 'admin/sales/due-list',
                    'actions' => ['index', 'create']
                ],
                [
                    'name' => 'Report & Delivery Hub',
                    'label' => 'Report & Delivery Hub',
                    'route' => 'admin/sales/fulfillment',
                    'icon' => 'fa-clipboard-check',
                    'permission' => 'sale_fulfillment',
                    'actions' => ['index', 'view', 'edit']
                ],
                [
                    'name' => 'Pending Services',
                    'label' => 'Pending Services',
                    'route' => 'admin/sales/pending-services',
                    'icon' => 'fa-clock',
                    'actions' => ['index', 'view', 'edit']
                ],
                [
                    'name' => 'Service Type',
                    'label' => 'Service Types',
                    'route' => 'admin/service-type',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Sale Return',
                    'label' => 'Sales Returns (Credit Notes)',
                    'route' => 'admin/sale-returns',
                    'actions' => ['index', 'create', 'view', 'delete', 'policy_override']
                ],
            ]
        ],
        [
            'name' => 'Proposals',
            'icon' => 'fa-file-contract',
            'order' => 8,
            'category' => 'SALES & COMMERCE',
            'children' => [
                [
                    'name' => 'Template',
                    'label' => 'Templates',
                    'route' => 'admin/template',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Proposals',
                    'label' => 'All Proposals',
                    'route' => 'admin/proposal',
                    'actions' => ['index', 'create', 'view', 'edit', 'delete']
                ]
            ]
        ],
        [
            'name' => 'Purchase',
            'label' => 'Purchases',
            'icon' => 'fa-shopping-cart',
            'order' => 9,
            'category' => 'SALES & COMMERCE',
            'children' => [
                [
                    'name' => 'Add Purchase',
                    'label' => 'New Purchase',
                    'route' => 'admin/purchase/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Purchase List',
                    'label' => 'All Purchases',
                    'route' => 'admin/purchase',
                    'actions' => ['index', 'view', 'edit', 'delete']
                ],
                [
                    'name' => 'Purchase Return',
                    'label' => 'Purchase Returns (Debit Notes)',
                    'route' => 'admin/purchase-returns',
                    'actions' => ['index', 'create', 'view', 'edit', 'delete']
                ],
            ]
        ],

        // ═══════════════════════════════════════════════
        //   OPERATIONS
        // ═══════════════════════════════════════════════
        [
            'name' => 'Inventory',
            'icon' => 'fa-warehouse',
            'order' => 12,
            'category' => 'OPERATIONS',
            'children' => [
                [
                    'name' => 'Product',
                    'label' => 'Products',
                    'route' => 'admin/product',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Category',
                    'label' => 'Categories',
                    'route' => 'admin/category',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Type',
                    'label' => 'Types',
                    'route' => 'admin/type',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Brand',
                    'label' => 'Brands',
                    'route' => 'admin/brand',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Stock Transfer',
                    'label' => 'Stock Transfers',
                    'route' => 'admin/stock-transfers',
                    'actions' => ['index', 'create', 'view', 'cancel']
                ],
                [
                    'name' => 'Stock Adjustment',
                    'label' => 'Stock Adjustments',
                    'route' => 'admin/stock-adjustments',
                    'actions' => ['index', 'create', 'view', 'cancel']
                ],
            ]
        ],
        [
            'name' => 'Appointment',
            'label' => 'Appointments',
            'icon' => 'fa-calendar',
            'order' => 10,
            'category' => 'OPERATIONS',
            'children' => [
                [
                    'name' => 'Add Appointment',
                    'label' => 'New Appointment',
                    'route' => 'admin/appointments/create',
                    'actions' => ['create']
                ],
                [
                    'name' => "Today's Queue",
                    'label' => "Today's Queue",
                    'route' => 'admin/appointments/today',
                    'icon' => 'fa-tasks',
                    'permission' => 'appointment_today',
                    'actions' => ['index', 'view', 'edit']
                ],
                [
                    'name' => 'Appointment List',
                    'label' => 'All Appointments',
                    'route' => 'admin/appointments',
                    'actions' => ['index', 'edit', 'delete']
                ],
                [
                    'name' => 'Calendar View',
                    'route' => 'admin/appointments/calendar',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Staff Rosters & Leave',
                    'label' => 'Rosters & Leave',
                    'route' => 'admin/roster',
                    'icon' => 'fa-user-clock',
                    'permission' => 'roster',
                    'actions' => ['index', 'view', 'create', 'edit']
                ],
                [
                    'name' => 'Professional List',
                    'label' => 'All Professionals',
                    'route' => 'admin/professionals',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Service List',
                    'label' => 'All Services',
                    'route' => 'admin/appointment-services',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Time Slot',
                    'label' => 'Time Slots',
                    'route' => 'admin/time-slots',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Sound Booths',
                    'label' => 'Sound Booths',
                    'route' => 'admin/sound-booths',
                    'icon' => 'fa-door-closed',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Support',
            'icon' => 'fa-headset',
            'order' => 13,
            'category' => 'OPERATIONS',
            'children' => [
                [
                    'name' => 'Add Ticket',
                    'label' => 'New Ticket',
                    'route' => 'admin/ticket/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Ticket List',
                    'label' => 'All Tickets',
                    'route' => 'admin/ticket',
                    'actions' => ['index', 'edit', 'delete']
                ],
                [
                    'name' => 'Add Task',
                    'label' => 'New Task',
                    'route' => 'admin/task/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Task List',
                    'label' => 'All Tasks',
                    'route' => 'admin/task',
                    'actions' => ['index', 'edit', 'delete']
                ],
                [
                    'name' => 'Support Settings',
                    'label' => 'Support Settings',
                    'route' => 'admin/support-settings',
                    'actions' => ['index', 'edit']
                ],
            ]
        ],
        [
            'name' => 'repair',
            'label' => 'Repair & Service',
            'icon' => 'fa-tools',
            'order' => 11,
            'category' => 'OPERATIONS',
            'children' => [
                [
                    'name' => 'Repair Jobs Board',
                    'label' => 'Repair Jobs Board',
                    'route' => 'admin/repairs',
                    'icon' => 'fa-columns',
                    'order' => 1,
                    'actions' => ['index', 'view', 'edit', 'payment', 'void_warranty', 'replacement']
                ],
                [
                    'name' => 'New Repair Intake',
                    'label' => 'New Repair Intake',
                    'route' => 'admin/repairs/create',
                    'icon' => 'fa-plus-circle',
                    'order' => 2,
                    'actions' => ['index', 'create']
                ],
                [
                    'name' => 'Quick Walk-in Service',
                    'label' => 'Quick Walk-in Service',
                    'route' => 'admin/repairs/quick',
                    'icon' => 'fa-bolt',
                    'order' => 3,
                    'actions' => ['index', 'create']
                ],
                [
                    'name' => 'Warranty & Serial Check',
                    'label' => 'Warranty & Serial Check',
                    'route' => 'admin/repairs/lookup',
                    'icon' => 'fa-barcode',
                    'order' => 4,
                    'actions' => ['index']
                ],
                [
                    'name' => 'Supplier RMA & Replacements',
                    'label' => 'Supplier RMA & Replacements',
                    'route' => 'admin/repairs/rma',
                    'icon' => 'fa-exchange-alt',
                    'order' => 5,
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Supplier Reconciliation',
                    'label' => 'Supplier Reconciliation',
                    'route' => 'admin/repairs/supplier-reconciliation',
                    'icon' => 'fa-file-invoice-dollar',
                    'order' => 6,
                    'actions' => ['index']
                ],
                [
                    'name' => 'Repair History',
                    'label' => 'Repair History',
                    'route' => 'admin/repairs/history',
                    'icon' => 'fa-history',
                    'order' => 7,
                    'actions' => ['index', 'view']
                ],
            ]
        ],

        // ═══════════════════════════════════════════════
        //   FINANCE
        // ═══════════════════════════════════════════════
        [
            'name' => 'Accounts',
            'icon' => 'fa-money-check-alt',
            'order' => 14,
            'category' => 'FINANCE',
            'children' => [
                [
                    'name' => 'Ledger',
                    'route' => 'admin/report/ledger',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Income Expense Head',
                    'label' => 'Income & Expense Heads',
                    'route' => 'admin/income-expense-head',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Expense',
                    'label' => 'Expenses',
                    'route' => 'admin/expense',
                    'actions' => ['index', 'create', 'delete']
                ],
                [
                    'name' => 'Payment Reconciliation',
                    'label' => 'Payment Reconciliation (POS / MFS)',
                    'route' => 'admin/payment-reconciliation',
                    'icon' => 'fa-balance-scale',
                    'actions' => ['index', 'create', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Membership',
            'label' => 'Membership & Rewards',
            'icon' => 'fa-crown',
            'order' => 15,
            'category' => 'FINANCE',
            'children' => [
                [
                    'name' => 'Membership Tiers',
                    'route' => 'admin/membership/tiers',
                    'actions' => ['index', 'edit']
                ],
                [
                    'name' => 'Milestones',
                    'route' => 'admin/membership/milestones',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Loyalty Settings',
                    'route' => 'admin/membership/settings',
                    'actions' => ['index', 'edit']
                ],
                [
                    'name' => 'Reward Wallets',
                    'route' => 'admin/membership/wallets',
                    'actions' => ['index', 'view']
                ],
                [
                    'name' => 'Reward Ledger',
                    'route' => 'admin/membership/reward-ledger',
                    'actions' => ['index', 'view']
                ],
                [
                    'name' => 'Referrals',
                    'route' => 'admin/membership/referrals',
                    'actions' => ['index', 'view', 'delete']
                ],
                [
                    'name' => 'Tier Upgrades',
                    'route' => 'admin/membership/upgrades',
                    'actions' => ['index', 'view']
                ],
            ]
        ],

        // ═══════════════════════════════════════════════
        //   COMMUNICATION
        // ═══════════════════════════════════════════════
        [
            'name' => 'SMS Centre',
            'icon' => 'fa-comment-dots',
            'order' => 16,
            'category' => 'COMMUNICATION',
            'children' => [
                [
                    'name' => 'Compose & Send',
                    'route' => 'admin/sms',
                    'actions' => ['index', 'create']
                ],
                [
                    'name' => 'Templates',
                    'route' => 'admin/sms-templates',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Send Log',
                    'route' => 'admin/sms-log',
                    'actions' => ['index', 'view']
                ],
                [
                    'name' => 'Analytics',
                    'route' => 'admin/sms-analytics',
                    'actions' => ['index']
                ],
                [
                    'name' => 'SMS Automations',
                    'label' => 'Automations',
                    'route' => 'admin/sms-automations',
                    'permission' => 'sms_automation',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
            ]
        ],

        // ═══════════════════════════════════════════════
        //   REPORTS & ADMINISTRATION
        // ═══════════════════════════════════════════════
        [
            'name' => 'Report',
            'label' => 'Reports',
            'icon' => 'fa-chart-pie',
            'order' => 17,
            'category' => 'REPORTS & ADMINISTRATION',
            'children' => [
                [
                    'name' => 'Reports Hub',
                    'label' => 'All Reports',
                    'route' => 'admin/reports',
                    'icon' => 'fa-th-large',
                    'order' => 0,
                    'actions' => ['index']
                ],
                [
                    'name' => 'Profit Statement',
                    'label' => 'Profit Statement',
                    'route' => 'admin/report/profit-statement',
                    'actions' => ['index', 'budget']
                ],
                [
                    'name' => 'Stock',
                    'label' => 'Stock Report',
                    'route' => 'admin/report/stock',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Purchase & Stock',
                    'label' => 'Purchase vs Stock Report',
                    'route' => 'admin/report/purchase-stock',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Purchase Report',
                    'label' => 'Purchase Report',
                    'route' => 'admin/report/purchase-summary',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Supplier Statement',
                    'label' => 'Supplier Statement',
                    'route' => 'admin/report/supplier-statement',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Vendor Monthly Report',
                    'label' => 'Branch Monthly Report',
                    'route' => 'admin/report/vendor-monthly',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Warranty Expiry',
                    'label' => 'Warranty Expiry Report',
                    'route' => 'admin/report/warranty-expiry',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Battery Usage Expiry',
                    'label' => 'Battery Expiry Report',
                    'route' => 'admin/report/battery-usage-expiry',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Sales Performance',
                    'label' => 'Sales Performance Report',
                    'route' => 'admin/report/sales-performance',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Membership & Loyalty',
                    'label' => 'Membership & Loyalty Report',
                    'route' => 'admin/report/membership-loyalty',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Lead Funnel',
                    'label' => 'Lead Funnel Report',
                    'route' => 'admin/report/lead-funnel',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Advances Report',
                    'label' => 'Advances Report',
                    'route' => 'admin/report/advances',
                    'icon' => 'fa-hand-holding-usd',
                    'actions' => ['index', 'view']
                ],
                [
                    'name' => 'Services Status',
                    'label' => 'Services Status Report',
                    'route' => 'admin/report/services-status',
                    'icon' => 'fa-stethoscope',
                    'permission' => 'services_status_report',
                    'actions' => ['index', 'view']
                ],
            ]
        ],
        [
            'name' => 'Utilities',
            'label' => 'Administration',
            'icon' => 'fa-cog',
            'order' => 18,
            'category' => 'REPORTS & ADMINISTRATION',
            'children' => [
                [
                    'name' => 'Settings Hub',
                    'label' => 'All Settings',
                    'route' => 'admin/settings-hub',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Setting',
                    'label' => 'General Settings',
                    'route' => 'admin/setting/create',
                    'actions' => ['edit']
                ],
                [
                    'name' => 'Roles',
                    'label' => 'Roles & Permissions',
                    'route' => 'admin/roles',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Users',
                    'route' => 'admin/user',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Activity Log',
                    'route' => 'admin/activity-log',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Database Backup',
                    'route' => 'admin/database-backup',
                    'actions' => ['index']
                ]
            ]
        ]
    ],

    /**
     * Friendly labels for permissions in the Role → Manage Permissions screen.
     * Keyed by full permission name. Anything not listed falls back to the action name.
     */
    'action_labels' => [
        'dashboard.index'              => 'Access Dashboard',
        'dashboard.view_all_data'      => 'View All Users\' Data',
        'dashboard.todays_sales'       => 'KPI: Today\'s Sales',
        'dashboard.total_sales'        => 'KPI: Total Revenue',
        'dashboard.total_leads'        => 'KPI: Total Leads',
        'dashboard.total_customers'    => 'KPI: Total Customers',
        'dashboard.total_products'     => 'KPI: Stock',
        'dashboard.total_tickets'      => 'Lookout: Tickets',
        'dashboard.total_tasks'        => 'Lookout: Tasks',
        'dashboard.daily_lookout'      => 'Daily Lookout Panel',
        'dashboard.follow_ups'         => 'Lookout: Follow-ups',
        'dashboard.appointments'       => 'Lookout: Appointments',
        'dashboard.warranty_alerts'    => 'Lookout: Warranty & Battery',
        'dashboard.marketing_insights' => 'Marketing Insights',
        'dashboard.inventory'          => 'Inventory Alerts',
        'dashboard.revenue_chart'      => 'Revenue vs Purchases Chart',
        'dashboard.lead_pipeline'      => 'Lead Pipeline',
        'dashboard.lead_charts'        => 'Lead Charts (Type/Source/Inquiry)',
        'dashboard.net_balance'        => 'Financial Summary',
        'dashboard.total_purchases'    => 'Purchases in Financials',
        'dashboard.performance'        => 'Staff Performance',
        'dashboard.suppliers'          => 'Suppliers (reserved)',
        'dashboard.membership'         => 'Membership (reserved)',
        // Phase 1 analytics
        'dashboard.smart_alerts'            => 'Smart Alerts Strip',
        'dashboard.top_customers'           => 'Analytics: Top / Frequent Customers',
        'dashboard.customer_rfm'            => 'Analytics: Customer Value Segments (RFM)',
        'dashboard.new_vs_returning'        => 'Analytics: New vs Returning Buyers',
        'dashboard.dormant_customers'       => 'Analytics: Dormant Customers',
        'dashboard.membership_breakdown'    => 'Analytics: Membership Breakdown',
        'dashboard.points_liability'        => 'Analytics: Loyalty Points Liability',
        'dashboard.upgrade_due'             => 'Analytics: Device Upgrade Due',
        'dashboard.binaural_opportunity'    => 'Analytics: Second-Ear (Binaural) Opportunity',
        'dashboard.top_products'            => 'Analytics: Top Products / Brands',
        'dashboard.gross_profit'            => 'Analytics: Gross Profit & Margin',
        'dashboard.aov_trend'               => 'Analytics: Average Order Value Trend',
        'dashboard.receivables_aging'       => 'Analytics: Customer Dues Aging',
        'dashboard.discount_leakage'        => 'Analytics: Discount Leakage',
        'dashboard.payment_mix'             => 'Analytics: Payment Method Mix',
        'dashboard.branch_leaderboard'      => 'Analytics: Branch Leaderboard (all branches)',
        'dashboard.audience_builder'        => 'Analytics: Marketing Audience Builder (export)',
        'dashboard.top_doctors'             => 'Analytics: Top Referring Doctors',
        'dashboard.inactive_doctors'        => 'Analytics: Inactive Doctors',
        'dashboard.referral_trend'          => 'Analytics: Referral Trend',
        'dashboard.dead_stock'              => 'Analytics: Dead / Slow Stock',
        'dashboard.branch_stock'            => 'Analytics: Stock Value by Branch (all branches)',
        'dashboard.supplier_aging'          => 'Analytics: Supplier Payables Aging',
        'dashboard.appointment_utilisation' => 'Analytics: Appointment Utilisation',
        'dashboard.ticket_sla'              => 'Analytics: Ticket SLA',
        'dashboard.expense_revenue'         => 'Analytics: Expense vs Revenue',
        'dashboard.cash_today'              => 'Analytics: Cash Flow Today',
        'dashboard.birthdays'               => 'Analytics: Birthdays This Week',
        'dashboard.customer_geography'      => 'Analytics: Customer Geography',
        'dashboard.service_due'             => 'Analytics: Service / Cleaning Due',
        'dashboard.test_to_sale_funnel'     => 'Analytics: Hearing Test to Sale Funnel',
        'dashboard.lead_response_time'      => 'Analytics: Lead Response Time',
        'dashboard.lost_reasons'            => 'Analytics: Lost Lead Reasons',
        'dashboard.commission_due'          => 'Analytics: Referral Commission Due',

        // Repair & Service
        'repair_jobs_board.view'            => 'Open Job Card & Prints',
        'repair_jobs_board.edit'            => 'Update Job (status, items, estimate, lab, files)',
        'repair_jobs_board.payment'         => 'Collect Payment & Sync Lab Bill',
        'repair_jobs_board.void_warranty'   => 'Void Warranty',
        'repair_jobs_board.replacement'     => 'Authorise Replacement',
        'supplier_rma_&_replacements.delete' => 'Delete / Clear Settled RMA',
    ],

    /**
     * Dashboard permission sections shown in Roles → Manage Permissions.
     * Mirrors the dashboard layout. Display only — permission names are unchanged.
     * Any dashboard permission not listed here appears under "Other".
     */
    'dashboard_groups' => [
        'access'    => ['label' => 'Access & Data Scope',              'icon' => 'fa-key',              'color' => '#475569', 'hint' => 'Open the dashboard. "View All Users\' Data" shows company-wide numbers; without it a user sees only records they created.',
                        'actions' => ['index', 'view_all_data']],
        'kpi'       => ['label' => 'KPI Cards (top row)',               'icon' => 'fa-th-large',         'color' => '#2563eb', 'hint' => 'Headline number cards.',
                        'actions' => ['todays_sales', 'total_sales', 'total_leads', 'total_customers', 'total_products']],
        'lookout'   => ['label' => 'Daily Lookout',                     'icon' => 'fa-sun',              'color' => '#d97706', 'hint' => 'Tick the panel plus the tabs it should show. Birthdays, Upgrade Due and Service Due tabs follow their analytics widgets below.',
                        'actions' => ['daily_lookout', 'follow_ups', 'appointments', 'total_tasks', 'total_tickets', 'warranty_alerts']],
        'panels'    => ['label' => 'Charts, Alerts & Panels',           'icon' => 'fa-chart-area',       'color' => '#7c3aed', 'hint' => 'Main charts and summary panels.',
                        'actions' => ['smart_alerts', 'revenue_chart', 'lead_pipeline', 'lead_charts', 'marketing_insights', 'inventory', 'net_balance', 'total_purchases', 'performance']],
        'customers' => ['label' => 'Analytics · Customer Intelligence', 'icon' => 'fa-users',            'color' => '#6d28d9', 'hint' => '',
                        'actions' => ['top_customers', 'customer_rfm', 'new_vs_returning', 'dormant_customers', 'membership_breakdown', 'points_liability', 'birthdays', 'customer_geography']],
        'hearing'   => ['label' => 'Analytics · Hearing Care',          'icon' => 'fa-assistive-listening-systems', 'color' => '#0f766e', 'hint' => '',
                        'actions' => ['upgrade_due', 'binaural_opportunity', 'service_due', 'test_to_sale_funnel']],
        'sales'     => ['label' => 'Analytics · Sales & Revenue',       'icon' => 'fa-chart-line',       'color' => '#15803d', 'hint' => 'Branch Leaderboard compares all branches — give it only to company-level roles.',
                        'actions' => ['top_products', 'gross_profit', 'aov_trend', 'payment_mix', 'receivables_aging', 'discount_leakage', 'branch_leaderboard']],
        'marketing' => ['label' => 'Analytics · Marketing & Referrals', 'icon' => 'fa-bullhorn',         'color' => '#be123c', 'hint' => 'Audience Builder lets the user export customer phone lists.',
                        'actions' => ['audience_builder', 'top_doctors', 'inactive_doctors', 'referral_trend', 'lead_response_time', 'lost_reasons', 'commission_due']],
        'ops'       => ['label' => 'Analytics · Operations & Finance',  'icon' => 'fa-cogs',             'color' => '#0369a1', 'hint' => 'Stock Value by Branch compares all branches — company-level roles only.',
                        'actions' => ['cash_today', 'supplier_aging', 'dead_stock', 'branch_stock', 'appointment_utilisation', 'ticket_sla', 'expense_revenue']],
        'other'     => ['label' => 'Reserved',                          'icon' => 'fa-ellipsis-h',       'color' => '#94a3b8', 'hint' => 'Not used on the dashboard yet.',
                        'actions' => ['suppliers', 'membership']],
    ],

    /**
     * One-click presets for the dashboard section of a role (actions; '*' = all).
     * Presets only tick boxes on screen — nothing is saved until "Save Permissions".
     */
    'dashboard_presets' => [
        'full' => ['label' => 'Full (Owner)', 'icon' => 'fa-crown', 'hint' => 'Every dashboard widget', 'actions' => ['*']],
        'manager' => ['label' => 'Manager', 'icon' => 'fa-user-tie', 'hint' => 'Everything except company-wide finance & branch comparisons', 'actions' => [
            'index', 'view_all_data',
            'todays_sales', 'total_sales', 'total_leads', 'total_customers', 'total_products',
            'daily_lookout', 'follow_ups', 'appointments', 'total_tasks', 'total_tickets', 'warranty_alerts',
            'smart_alerts', 'revenue_chart', 'lead_pipeline', 'lead_charts', 'marketing_insights', 'inventory', 'net_balance', 'total_purchases', 'performance',
            'top_customers', 'customer_rfm', 'new_vs_returning', 'dormant_customers', 'membership_breakdown', 'points_liability', 'birthdays', 'customer_geography',
            'upgrade_due', 'binaural_opportunity', 'service_due', 'test_to_sale_funnel',
            'top_products', 'aov_trend', 'payment_mix', 'receivables_aging', 'discount_leakage',
            'audience_builder', 'top_doctors', 'inactive_doctors', 'referral_trend', 'lead_response_time', 'lost_reasons', 'commission_due',
            'cash_today', 'supplier_aging', 'dead_stock', 'appointment_utilisation', 'ticket_sla',
        ]],
        'sales' => ['label' => 'Sales / Front Desk', 'icon' => 'fa-headset', 'hint' => 'Own leads, follow-ups & customer care lists (own data only)', 'actions' => [
            'index',
            'todays_sales', 'total_leads', 'total_customers',
            'daily_lookout', 'follow_ups', 'appointments', 'total_tasks', 'total_tickets', 'warranty_alerts',
            'smart_alerts', 'lead_pipeline', 'lead_charts',
            'top_customers', 'dormant_customers', 'birthdays',
            'upgrade_due', 'binaural_opportunity', 'service_due', 'test_to_sale_funnel',
            'lead_response_time', 'lost_reasons', 'appointment_utilisation',
        ]],
        'accounts' => ['label' => 'Accounts', 'icon' => 'fa-calculator', 'hint' => 'Money in/out, dues, margins & stock value', 'actions' => [
            'index', 'view_all_data',
            'todays_sales', 'total_sales', 'total_products',
            'smart_alerts', 'revenue_chart', 'inventory', 'net_balance', 'total_purchases',
            'points_liability',
            'gross_profit', 'aov_trend', 'payment_mix', 'receivables_aging', 'discount_leakage',
            'commission_due',
            'cash_today', 'supplier_aging', 'dead_stock', 'expense_revenue',
        ]],
        'clear' => ['label' => 'Clear', 'icon' => 'fa-eraser', 'hint' => 'Untick all dashboard permissions', 'actions' => []],
    ],
];
