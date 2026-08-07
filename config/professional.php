<?php

return [
    'allowed_permissions' => [
        'dashboard.index',
        'dashboard.appointments',

        // Appointment permissions
        'appointment_list.index',
        'add_appointment.create',
        'appointment_list.edit',
        'appointment_list.delete',

        // Lead permissions
        'lead_list.index',
        'add_lead.create',
        'lead_list.edit',
        'lead_list.delete',

        // Customer permissions
        'customer_list.index',
        'add_customer.create',
        'customer_list.edit',
        'customer_list.delete',
    ]
];
