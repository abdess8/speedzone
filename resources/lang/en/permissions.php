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
        'partners' => 'Partner Integrations',
        'users' => 'Users',
        'roles' => 'Roles',
        'permissions' => 'Permissions',
    ],

    'names' => [
        'partners.create' => 'Create partners',
        'partners.read' => 'View partners',
        'partners.update' => 'Update partners',
        'partners.delete' => 'Delete partners',
        'partners.sync' => 'Force partner sync',
        'partners.deliveries.manage' => 'Manage partner deliveries',

        'orders.read.assigned' => 'View assigned orders',
        'orders.update.assigned' => 'Update status of assigned orders',

        'users.read' => 'View users',
        'users.create' => 'Create users',
        'users.update' => 'Update users',
        'users.delete' => 'Delete users',
        'users.roles.assign' => 'Assign roles',

        'support.create' => 'Create tickets',
        'support.read.own' => 'View own tickets',
        'support.read.all' => 'View all tickets',
        'support.reply' => 'Reply to tickets',
        'support.assign' => 'Assign tickets',
        'support.update_status' => 'Update ticket status',
        'support.close' => 'Close tickets',
        'support.manage' => 'Manage support (full access)',
    ],

    'scopes' => [
        'own' => 'own',
        'all' => 'all',
        'assigned' => 'affected',
    ],

    'descriptions' => [
        'partners.create' => 'Register a new B2B delivery partner and its API credentials.',
        'partners.read' => 'View partner configurations, status mappings, and API logs.',
        'partners.update' => 'Edit partner settings, credentials, cities, and status mappings.',
        'partners.delete' => 'Remove a partner integration.',
        'partners.sync' => 'Trigger an on-demand "Force Sync Now" ingestion for a partner.',
        'partners.deliveries.manage' => 'Update and mass-scan deliveries belonging to assigned partners.',
        'orders.read.assigned' => 'View only orders assigned to the current user (driver).',
        'orders.update.assigned' => 'Advance the status of orders assigned to the driver. Does not allow editing the order content.',
        'users.read' => 'Access the user list and user detail pages.',
        'users.create' => 'Create user accounts (sellers, drivers, staff).',
        'users.update' => 'Edit a user account.',
        'users.delete' => 'Delete a user account.',
        'users.roles.assign' => 'Grant or revoke a user role. Sensitive: it enables privilege escalation.',
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
