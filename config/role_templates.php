<?php

/**
 * Starter roles for the Roles screen, and the areas the plain-language access summary is built from.
 *
 * 'areas'     — area label => permission prefixes (the part before the dot, as stored in permissions.name)
 * 'templates' — role name => description + what it gets per area at a level:
 *                 view = see lists and records
 *                 work = view + add and edit day-to-day records (never delete / cancel / void)
 *                 full = everything in the area except seeing other branches
 *               'dashboard' is 'all' (every widget) or 'basic' (the everyday widgets).
 * Permissions that do not exist (menu renamed or removed) are skipped, so a template never breaks a deploy.
 * Applying a template only creates a role; it never changes an existing one.
 */
return [
    'areas' => [
        'Sales'        => ['sales', 'add_sale', 'sale_list', 'patient_due_list', 'sale_fulfillment', 'pending_services', 'service_type', 'sale_return', 'proposals', 'template'],
        'Patients'     => ['customer', 'add_customer', 'customer_list', 'customer_lookup', 'customer_ledger', 'customer_payment', 'advance'],
        'Leads'        => ['leads', 'deal_board', 'add_lead', 'lead_list', 'web_leads', 'deal_reports', 'pipeline_settings', 'sequences'],
        'Marketing'    => ['marketing', 'marketing_roi', 'marketing_campaigns', 'marketing_spend'],
        'Doctors'      => ['doctor', 'add_doctor', 'doctor_list', 'doctor_payment', 'doctor_&_referral', 'doctor_statement'],
        'Suppliers'    => ['supplier', 'add_supplier', 'supplier_list', 'supplier_payment', 'supplier_statement', 'supplier_reconciliation'],
        'Purchases'    => ['purchase', 'add_purchase', 'purchase_list', 'purchase_return'],
        'Stock'        => ['inventory', 'product', 'category', 'type', 'brand', 'stock_transfer', 'stock_adjustment'],
        'Appointments' => ['appointment', 'add_appointment', 'appointment_today', 'appointment_list', 'calendar_view', 'roster', 'professional_list', 'service_list', 'time_slot', 'sound_booths'],
        'Support'      => ['support', 'add_ticket', 'ticket_list', 'add_task', 'task_list', 'support_settings'],
        'Repairs'      => ['repair', 'repair_jobs_board', 'new_repair_intake', 'quick_walk-in_service', 'warranty_&_serial_check', 'supplier_rma_&_replacements', 'repair_history'],
        'Accounts'     => ['accounts', 'ledger', 'income_expense_head', 'expense', 'payment_reconciliation'],
        'Membership'   => ['membership', 'membership_tiers', 'milestones', 'loyalty_settings', 'reward_wallets', 'reward_ledger', 'referrals', 'tier_upgrades'],
        'SMS'          => ['sms_centre', 'compose_&_send', 'templates', 'send_log', 'analytics', 'sms_automation'],
        'Reports'      => ['report', 'reports_hub', 'profit_statement', 'stock', 'purchase_&_stock', 'purchase_report', 'vendor_monthly_report', 'warranty_expiry', 'battery_usage_expiry', 'sales_performance', 'membership_&_loyalty', 'lead_funnel', 'advances_report', 'services_status_report'],
        'Branches'     => ['vendor', 'add_vendor', 'vendor_list'],
        'Administration' => ['utilities', 'settings_hub', 'setting', 'roles', 'users', 'activity_log', 'database_backup'],
    ],

    'dashboard_basic' => ['index', 'daily_lookout', 'follow_ups', 'appointments', 'warranty_alerts', 'todays_sales', 'total_tickets', 'total_tasks', 'birthdays', 'service_due'],

    'templates' => [
        'Branch Manager' => [
            'description' => 'Runs a branch day to day: sales, patients, stock, appointments, support and repairs. No users or settings.',
            'dashboard' => 'all',
            'grants' => ['Sales' => 'full', 'Patients' => 'full', 'Leads' => 'full', 'Doctors' => 'work', 'Suppliers' => 'view', 'Purchases' => 'work',
                'Stock' => 'work', 'Appointments' => 'full', 'Support' => 'full', 'Repairs' => 'full', 'Accounts' => 'work', 'Membership' => 'work', 'SMS' => 'work', 'Reports' => 'view'],
        ],
        'Front Desk / Sales' => [
            'description' => 'Greets patients, books appointments, makes sales and opens tickets. Cannot delete or see accounts.',
            'dashboard' => 'basic',
            'grants' => ['Sales' => 'work', 'Patients' => 'work', 'Leads' => 'work', 'Appointments' => 'work', 'Support' => 'work', 'Membership' => 'view', 'Stock' => 'view', 'Repairs' => 'view'],
        ],
        'Audiologist' => [
            'description' => 'Sees own appointments and patient records, can book follow-ups. Sales and repairs are view only.',
            'dashboard' => 'basic',
            'grants' => ['Appointments' => 'work', 'Patients' => 'view', 'Sales' => 'view', 'Repairs' => 'view', 'Support' => 'view', 'Leads' => 'view'],
        ],
        'Accounts' => [
            'description' => 'Keeps the books: ledger, expenses, supplier and doctor payments. Sales and purchases are view only.',
            'dashboard' => 'basic',
            'grants' => ['Accounts' => 'full', 'Suppliers' => 'work', 'Doctors' => 'work', 'Patients' => 'view', 'Sales' => 'view', 'Purchases' => 'view', 'Reports' => 'view'],
        ],
        'Repair Technician' => [
            'description' => 'Works the repair board and warranty checks. Can see patients and stock but not change them.',
            'dashboard' => 'basic',
            'grants' => ['Repairs' => 'work', 'Patients' => 'view', 'Stock' => 'view', 'Support' => 'view'],
        ],
        'Support Agent' => [
            'description' => 'Handles tickets and tasks. Can look up patients, invoices, repairs and appointments but not change them.',
            'dashboard' => 'basic',
            'grants' => ['Support' => 'work', 'Patients' => 'view', 'Sales' => 'view', 'Repairs' => 'view', 'Appointments' => 'view'],
        ],
        'Store Keeper' => [
            'description' => 'Owns stock: products, adjustments and transfers, and records purchases. Suppliers and reports are view only.',
            'dashboard' => 'basic',
            'grants' => ['Stock' => 'full', 'Purchases' => 'work', 'Suppliers' => 'view', 'Reports' => 'view'],
        ],
    ],
];
