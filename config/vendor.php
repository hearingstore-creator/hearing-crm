<?php

return [
    // Fixed rights of the shared branch login. It deliberately has no delete rights on sales,
    // purchases, payments, doctors, leads, customers, suppliers or expenses: the login is shared,
    // so the audit log cannot tell who deleted. Give staff their own user (users.vendor_id) instead.
    'allowed_permissions' => [
        'dashboard.index',
        'dashboard.todays_sales',
        'dashboard.total_products',
        'dashboard.total_leads',
        'dashboard.total_customers',
        // 'dashboard.total_tickets',
        // 'dashboard.total_tasks',
        // 'dashboard.appointments',
        'dashboard.net_balance',
        'dashboard.total_sales',
        'dashboard.total_purchases',
        'dashboard.suppliers',
        'dashboard.daily_lookout',
        'dashboard.follow_ups',
        'dashboard.warranty_alerts',
        'dashboard.revenue_chart',
        'dashboard.lead_pipeline',
        'dashboard.lead_charts',
        'dashboard.inventory',
        'dashboard.marketing_insights',
        // Phase 1 analytics (vendor sees own branch only)
        'dashboard.smart_alerts',
        'dashboard.top_customers',
        'dashboard.new_vs_returning',
        'dashboard.dormant_customers',
        'dashboard.upgrade_due',
        'dashboard.binaural_opportunity',
        'dashboard.top_products',
        'dashboard.receivables_aging',
        'dashboard.top_doctors',
        'dashboard.supplier_aging',
        'dashboard.cash_today',
        // Phase 2 analytics (own branch only)
        'dashboard.birthdays',
        'dashboard.service_due',
        'dashboard.test_to_sale_funnel',
        'dashboard.lost_reasons',
        // Purchase permissions
        'purchase_list.index',
        'add_purchase.create',
        'purchase_list.edit',
        'purchase_list.view',
        'purchase_report.index',
        'purchase_return.index',
        'purchase_return.create',
        'purchase_return.view',

        // Product permissions
        'product.index',

        // Sale permissions
        'sale_list.index',
        'sale_list.edit',
        'sale_list.view',
        'add_sale.create',
        'sale_return.index',
        'sale_return.create',
        'sale_return.view',

        // Doctor payment permissions
        'doctor_payment.index',
        'doctor_payment.create',
        'doctor_payment.edit',

        // Doctor permissions
        'doctor_list.index',
        'add_doctor.create',
        'doctor_list.edit',

        // lead permissions
        'lead_list.index',
        'add_lead.create',
        'lead_list.edit',

        // deals board (own branch only; transfer and pipeline settings stay with Head Office)
        'deal_board.index',
        'deal_board.move',
        'deal_board.won',
        'deal_board.lost',
        'deal_reports.index',

        // customer permissions
        'customer_list.index',
        'add_customer.create',
        'customer_list.edit',
        'customer_ledger.view',
        'customer_payment.create',

        // Supplier permissions
        'add_supplier.create',
        'supplier_list.index',
        'supplier_list.edit',
        'supplier_payment.create',

        'expense.index',
        'expense.create',

        'ledger.index',
        'stock_report.index',
        'purchase_&_stock.index',

        // Business reports (vendor sees own branch only)
        'supplier_statement.index',
        'purchase_report.index',
        'vendor_monthly_report.index',

        // Appointment permissions (scoped to own branch)
        'dashboard.appointments',
        'appointment_list.index',
        'add_appointment.create',
        'appointment_list.edit',
        'appointment_list.delete',
        'calendar_view.index',
        'appointment_today.index',
        'appointment_today.view',
        'appointment_today.edit',
        'roster.index',
        'roster.view',
        'roster.create',
        'roster.edit',
        'pending_services.index',
        'pending_services.view',
        'pending_services.edit',

        // Repair Module
        'repair_jobs_board.index',
        'repair_jobs_board.view',
        'repair_jobs_board.edit',
        'repair_jobs_board.payment',
        'new_repair_intake.index',
        'new_repair_intake.create',
        'quick_walk-in_service.index',
        'quick_walk-in_service.create',
        'warranty_&_serial_check.index',
        'supplier_rma_&_replacements.index',
        'supplier_rma_&_replacements.edit',
        'supplier_reconciliation.index',
        'repair_history.index',
        'repair_history.view',
    ]
];

