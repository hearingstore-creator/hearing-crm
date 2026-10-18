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
        'calendar_view.index',
        'appointment_today.index',
        'appointment_today.view',
        'appointment_today.edit',
        'roster.index',
        'roster.view',

        // Lead permissions
        'lead_list.index',
        'add_lead.create',
        'lead_list.edit',
        'lead_list.delete',

        // Deals board (only deals they own)
        'deal_board.index',
        'deal_board.move',
        'deal_board.won',
        'deal_board.lost',

        // Customer permissions
        'customer_list.index',
        'add_customer.create',
        'customer_list.edit',
        'customer_list.delete',
    ]
];
