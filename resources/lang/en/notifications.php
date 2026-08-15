<?php

return [
    'unknown_user' => 'Unknown user',

    'titles' => [
        'invoice_generated' => 'Invoice generated',
        'ticket_created' => 'New support ticket',
        'ticket_message' => 'New ticket message',
        'ticket_closed' => 'Ticket closed',
        'ticket_assigned' => 'Ticket assigned',
        'return_requested' => 'Return request',
        'stock_pickup_requested' => 'Stock to collect',
        'new_seller_registration' => 'New seller registration',
    ],

    'messages' => [
        'invoice_generated' => 'Your invoice :reference has been generated.',
        'ticket_created' => 'New support ticket :reference created by :seller.',
        'ticket_created_with_subject' => 'New support ticket :reference — :subject.',
        'ticket_message' => 'New message on ticket :reference.',
        'ticket_closed' => 'Your support ticket :reference has been closed.',
        'ticket_assigned' => 'You have been assigned to ticket :reference.',
        'return_requested' => 'A new return request has been created.',
        'stock_pickup_requested' => ':shop has stock ready for collection in :city.',
        'new_seller_registration' => 'New seller registration requires approval.',
    ],

    'types' => [
        'invoice_generated' => 'Invoice generated',
        'ticket_created' => 'New tickets',
        'ticket_message' => 'Ticket messages',
        'ticket_closed' => 'Ticket closed',
        'return_requested' => 'Return requests',
        'stock_pickup_requested' => 'Stock waiting at a vendor',
        'seller_registered' => 'New seller registrations',
        'system_notifications' => 'System notifications',
    ],

    'settings' => [
        'title' => 'Notification Settings',
        'description' => 'Choose which notifications you want to receive.',
        'master_toggle' => 'Enable notifications',
        'master_toggle_help' => 'Turn off to silence all notifications.',
        'saved' => 'Notification preferences saved.',
    ],

    'center' => [
        'mark_all_read' => 'Mark all as read',
        'no_notifications' => 'No notifications yet',
        'view_all' => 'View all notifications',
    ],

    'icons' => [
        'invoice_generated' => 'bx-receipt',
        'ticket_created' => 'bx-support',
        'ticket_message' => 'bx-message-dots',
        'ticket_closed' => 'bx-check-circle',
        'return_requested' => 'bx-undo',
        'stock_pickup_requested' => 'bx-package',
        'seller_registered' => 'bx-user-plus',
        'system_notifications' => 'bx-cog',
    ],
];
