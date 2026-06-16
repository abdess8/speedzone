<?php

return [
    'resources' => [
        'support' => 'Support Tickets',
        'orders' => 'Orders',
        'pickup_requests' => 'Pickup Requests',
        'invoices' => 'Seller Invoices',
        'driver_invoices' => 'Driver Invoices',
        'returns' => 'Returns',
        'transfers' => 'Transfers',
    ],

    'names' => [
        'support.create' => 'Create tickets',
        'support.read.own' => 'View own tickets',
        'support.read.all' => 'View all tickets',
        'support.reply' => 'Reply to tickets',
        'support.assign' => 'Assign tickets',
        'support.update_status' => 'Update ticket status',
        'support.close' => 'Close tickets',
        'support.manage' => 'Manage support (full access)',
    ],

    'descriptions' => [
        'support.create' => 'Allows sellers to open new support tickets linked to orders, invoices, or pickup requests.',
        'support.read.own' => 'View support tickets created by the current user only.',
        'support.read.all' => 'View every support ticket in the Support Center dashboard.',
        'support.reply' => 'Post messages on tickets the user can access.',
        'support.assign' => 'Assign or reassign tickets to support staff members.',
        'support.update_status' => 'Change ticket status (Open, In Progress, Waiting Seller, Resolved, Closed).',
        'support.close' => 'Close a ticket. Sellers can close their own tickets after resolution; staff can close any ticket.',
        'support.manage' => 'Full support operations: view all tickets, assign, change status, reply, and close. Intended for support agents.',
    ],
];
