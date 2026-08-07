<?php

return [
    'menus' => [
        [
            'name' => 'Dashboard',
            'route' => 'admin/dashboard',
            'icon' => 'fa-tachometer-alt',
            'order' => 1,
            'children' => [],
            'actions' => ['index', 'todays_sales', 'total_products', 'total_leads', 'total_customers', 'total_tickets', 'total_tasks', 'appointments', 'net_balance', 'total_sales', 'total_purchases', 'suppliers', 'membership', 'inventory', 'performance']
        ],
        [
            'name' => 'Report',
            'icon' => 'fa-chart-pie',
            'order' => 2,
            'children' => [
                [
                    'name' => 'Stock',
                    'route' => 'admin/report/stock',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Purchase & Stock',
                    'route' => 'admin/report/purchase-stock',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Doctor & Referral',
                    'route' => 'admin/report/doctor-referral',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Warranty Expiry',
                    'route' => 'admin/report/warranty-expiry',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Battery Usage Expiry',
                    'route' => 'admin/report/battery-usage-expiry',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Sales Performance',
                    'route' => 'admin/report/sales-performance',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Membership & Loyalty',
                    'route' => 'admin/report/membership-loyalty',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Lead Funnel',
                    'route' => 'admin/report/lead-funnel',
                    'actions' => ['index']
                ],
            ]
        ],
        [
            'name' => 'Activity Log',
            'route' => 'admin/activity-log',
            'icon' => 'fa-history',
            'order' => 3,
            'children' => [],
            'actions' => ['index']
        ],
        [
            'name' => 'Leads',
            'icon' => 'fa-user-tie',
            'order' => 4,
            'children' => [
                [
                    'name' => 'Add Lead',
                    'route' => 'admin/leads/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Lead List',
                    'route' => 'admin/leads',
                    'actions' => ['index', 'view', 'edit', 'delete', 'import', 'export']
                ],
            ]
        ],
                [
            'name' => 'Customer',
            'icon' => 'fa-users',
            'order' => 5,
            'children' => [
                [
                    'name' => 'Add Customer',
                    'route' => 'admin/customers/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Customer List',
                    'route' => 'admin/customers',
                    'actions' => ['index', 'edit', 'delete']
                ],
            ]
        ],
                [
            'name' => 'Sales',
            'icon' => 'fa-file-invoice',
            'order' => 6,
            'children' => [
                [
                    'name' => 'Service Type',
                    'route' => 'admin/service-type',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Add Sale',
                    'route' => 'admin/sale/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Sale List',
                    'route' => 'admin/sale',
                    'actions' => ['index', 'view', 'edit', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Proposals',
            'icon' => 'fa-file-contract',
            'order' => 7,
            'children' => [
                [
                    'name' => 'Template',
                    'route' => 'admin/template',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Proposals',
                    'route' => 'admin/proposal',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ]
            ]
        ],
               [
            'name' => 'Appointment',
            'icon' => 'fa-calendar',
            'order' => 8,
            'children' => [
                [
                    'name' => 'Add Appointment',
                    'route' => 'admin/appointments/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Appointment List',
                    'route' => 'admin/appointments',
                    'actions' => ['index', 'edit', 'delete']
                ],
                [
                    'name' => 'Calendar View',
                    'route' => 'admin/appointments/calendar',
                    'actions' => ['index']
                ],
                [
                    'name' => 'Add Professional',
                    'route' => 'admin/professionals/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Professional List',
                    'route' => 'admin/professionals',
                    'actions' => ['index', 'edit', 'delete']
                ],
                [
                    'name' => 'Add Service',
                    'route' => 'admin/appointment-services/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Service List',
                    'route' => 'admin/appointment-services',
                    'actions' => ['index', 'edit', 'delete']
                ],
                [
                    'name' => 'Time Slot',
                    'route' => 'admin/time-slots',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Ticket',
            'icon' => 'fa-ticket-alt',
            'order' => 9,
            'children' => [
                [
                    'name' => 'Add Ticket',
                    'route' => 'admin/ticket/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Ticket List',
                    'route' => 'admin/ticket',
                    'actions' => ['index', 'edit', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Task',
            'icon' => 'fa-tasks',
            'order' => 10,
            'children' => [
                [
                    'name' => 'Add Task',
                    'route' => 'admin/task/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Task List',
                    'route' => 'admin/task',
                    'actions' => ['index', 'edit', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Doctor',
            'icon' => 'fa-user-md',
            'order' => 11,
            'children' => [
                [
                    'name' => 'Add Doctor',
                    'route' => 'admin/doctor/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Doctor List',
                    'route' => 'admin/doctor',
                    'actions' => ['index', 'edit', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Inventory',
            'icon' => 'fa-warehouse',
            'order' => 12,
            'children' => [
                [
                    'name' => 'Category',
                    'route' => 'admin/category',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Type',
                    'route' => 'admin/type',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Brand',
                    'route' => 'admin/brand',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Product',
                    'route' => 'admin/product',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ]
            ]
        ],
        [
            'name' => 'Purchase',
            'icon' => 'fa-shopping-cart',
            'order' => 13,
            'children' => [
                [
                    'name' => 'Add Purchase',
                    'route' => 'admin/purchase/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Purchase List',
                    'route' => 'admin/purchase',
                    'actions' => ['index', 'view', 'edit', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Vendor',
            'icon' => 'fa-truck',
            'order' => 14,
            'children' => [
                [
                    'name' => 'Add Vendor',
                    'route' => 'admin/vendors/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Vendor List',
                    'route' => 'admin/vendors',
                    'actions' => ['index', 'edit', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Supplier',
            'icon' => 'fa-building',
            'order' => 15,
            'children' => [
                [
                    'name' => 'Add Supplier',
                    'route' => 'admin/suppliers/create',
                    'actions' => ['create']
                ],
                [
                    'name' => 'Supplier List',
                    'route' => 'admin/suppliers',
                    'actions' => ['index', 'edit', 'delete']
                ],
            ]
        ],
        [
            'name' => 'Accounts',
            'icon' => 'fa-money-check-alt',
            'order' => 16,
            'children' => [
                [
                    'name' => 'Income Expense Head',
                    'route' => 'admin/income-expense-head',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Doctor Payment',
                    'route' => 'admin/doctors-payment',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Supplier Payment',
                    'route' => 'admin/suppliers-payment',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Expense',
                    'route' => 'admin/expense',
                    'actions' => ['index', 'create', 'delete']
                ],
                [
                    'name' => 'Ledger',
                    'route' => 'admin/report/ledger',
                    'actions' => ['index']
                ]
            ]
        ],
        [
            'name' => 'Membership',
            'icon' => 'fa-crown',
            'order' => 17,
            'children' => [
                [
                    'name' => 'Membership Tiers',
                    'route' => 'admin/membership/tiers',
                    'actions' => ['index']
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
        [
            'name' => 'SMS Centre',
            'icon' => 'fa-comment-dots',
            'order' => 18,
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
            ]
        ],
        [
            'name' => 'Utilities',
            'icon' => 'fa-cog',
            'order' => 19,
            'children' => [
                [
                    'name' => 'Setting',
                    'route' => 'admin/setting/create',
                    'actions' => ['edit']
                ],
                [
                    'name' => 'Roles',
                    'route' => 'admin/roles',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Users',
                    'route' => 'admin/user',
                    'actions' => ['index', 'create', 'edit', 'delete']
                ],
                [
                    'name' => 'Database Backup',
                    'route' => 'admin/database-backup',
                    'actions' => ['index']
                ]
            ]
        ]
    ]
];
