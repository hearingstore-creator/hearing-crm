<?php

return [
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
        // Purchase permissions
        'purchase_list.index',
        'add_purchase.create',
        'purchase_list.edit',
        'purchase_list.delete',
        'purchase_list.view',
        'purchase_report.index',

        // Product permissions
        'product.index',

        // Sale permissions
        'sale_list.index',
        'sale_list.edit',
        'sale_list.view',
        'sale_list.delete',
        'add_sale.create',

        // Doctor payment permissions
        'doctor_payment.index',
        'doctor_payment.create',
        'doctor_payment.edit',
        'doctor_payment.delete',

        // Doctor permissions
        'doctor_list.index',
        'add_doctor.create',
        'doctor_list.edit',
        'doctor_list.delete',

        // lead permissions
        'lead_list.index',
        'add_lead.create',
        'lead_list.edit',
        'lead_list.delete',
        'lead_list.import',
        'lead_list.export',

        // customer permissions
        'customer_list.index',
        'add_customer.create',
        'customer_list.edit',
        'customer_list.delete',
        'customer_ledger.view',
        'customer_payment.create',

        // Supplier permissions
        'add_supplier.create',
        'supplier_list.index',
        'supplier_list.edit',
        'supplier_list.delete',
        'supplier_ledger.view',
        'supplier_payment.create',

        'expense.index',
        'expense.create',
        'expense.delete',

        'ledger.index',
        'stock_report.index'
    ]
];
