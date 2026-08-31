<?php

/**
 * Readable permission labels for the Roles & permissions screen.
 *
 * This group is deliberately kept out of the frontend translation bundle: it
 * weighs tens of kilobytes and is only read by PermissionLabels, server side,
 * while building the roles screen.
 *
 * 'names'        : what the permission allows, in one line.
 * 'descriptions' : the help text shown behind the (i) icon.
 */
return [
    'resources' => [
        'dashboard' => 'Dashboard',
        'orders' => 'Orders',
        'pickup_requests' => 'Pickups',
        'transfers' => 'Inter-city transfers',
        'returns' => 'Returns',
        'invoices' => 'Seller invoices',
        'driver_invoices' => 'Driver payouts',
        'stock' => 'Stock & catalogue',
        'support' => 'Support tickets',
        'stores' => 'Shops',
        'team' => 'Seller team',
        'team_roles' => 'Seller team roles',
        'cities' => 'Cities',
        'sectors' => 'Delivery sectors',
        'driver_zones' => 'Driver coverage',
        'alerts' => 'Announcements',
        'notifications' => 'Notifications',
        'partners' => 'Partner integrations',
        'integrations' => 'E-commerce shops',
        'users' => 'Users',
        'roles' => 'Roles',
        'permissions' => 'Permission catalogue',
    ],

    /**
     * The reach of a permission, shown as a pill next to the label. Unknown
     * scopes — notification permissions park the notification topic in that
     * column — get no pill: the topic is already in the label.
     */
    'scopes' => [
        'own' => 'their own',
        'all' => 'everyone',
        'assigned' => 'assigned to them',
    ],

    /**
     * Templates for the status-change permissions, too numerous to spell out
     * one by one: the status names come from the same enums as the tracking
     * screens, so the vocabulary stays consistent.
     */
    'transitions' => [
        'workflow' => [
            'orders' => [
                'label' => 'Move to “:status”',
                'description' => 'Allows moving an order to “:status”, one at a time or in bulk.',
            ],
            'returns' => [
                'label' => 'Move to “:status”',
                'description' => 'Allows moving a return to “:status”, one at a time or in bulk.',
            ],
        ],
        'pair' => [
            'orders' => [
                'label' => ':from → :to',
                'description' => 'Allows moving an order from “:from” to “:to” in the bulk status screen.',
            ],
            'returns' => [
                'label' => ':from → :to',
                'description' => 'Allows moving a return from “:from” to “:to” in the bulk status screen.',
            ],
        ],
    ],

    'names' => [
        // Dashboard
        'dashboard.view' => 'Open the dashboard',
        'dashboard.view_financials' => 'See amounts and revenue',
        'dashboard.view_operations' => 'See order flow and pending work',
        'dashboard.view_performance' => 'See success rates and delays',
        'dashboard.view_customers' => 'See top customers',
        'dashboard.view_network' => 'See active sellers and drivers',

        // Orders
        'orders.create' => 'Create an order',
        'orders.create_with_stock' => 'Create an order from stock',
        'orders.read.own' => 'See their own shops orders',
        'orders.read.assigned' => 'See the orders assigned to them',
        'orders.read.all' => 'See every order',
        'orders.update.own' => 'Edit their own shops orders',
        'orders.update.assigned' => 'Report the outcome of assigned orders',
        'orders.update.all' => 'Edit every order',
        'orders.delete.own' => 'Delete their own shops orders',
        'orders.delete.all' => 'Delete any order',
        'orders.export' => 'Export orders to Excel',
        'orders.print' => 'Print shipping labels',

        // Pickups
        'pickup_requests.create' => 'Request a pickup',
        'pickup_requests.read.own' => 'See their own pickup requests',
        'pickup_requests.read.all' => 'See every pickup request',
        'pickup_requests.read.assigned' => 'See the pickups assigned to them',
        'pickup_requests.assign' => 'Assign a pickup to a driver',
        'pickup_requests.change_status' => 'Change a pickup status',
        'pickup_requests.pickup' => 'Confirm the pickup in the field',

        // Transfers
        'transfers.create' => 'Create a transfer',
        'transfers.read' => 'See transfers',
        'transfers.read.assigned' => 'See the transfers entrusted to them',
        'transfers.update' => 'Edit a transfer',
        'transfers.dispatch' => 'Send a transfer on its way',
        'transfers.receive' => 'Receive a transfer',

        // Returns
        'returns.create_request' => 'Request a parcel back',
        'returns.create' => 'Open a return in the field',
        'returns.read.own' => 'See their own shops returns',
        'returns.read.all' => 'See every return',
        'returns.manage' => 'Manage returns (full access)',
        'returns.update_status' => 'Change a return status',
        'returns.edit_customer_data' => 'Correct the hand-back address',

        // Seller invoices
        'invoices.read.own' => 'See their own invoices',
        'invoices.read.all' => 'See every seller invoice',
        'invoices.generate' => 'Generate seller invoices',
        'invoices.pay' => 'Mark an invoice as paid',
        'invoices.cancel' => 'Cancel an invoice',
        'invoices.delete' => 'Delete a cancelled invoice',
        'invoices.print' => 'Print an invoice',

        // Driver payouts
        'driver_invoices.read.own' => 'See their own payout statements',
        'driver_invoices.read.all' => 'See every driver payout',
        'driver_invoices.generate' => 'Generate driver payouts',
        'driver_invoices.pay' => 'Mark a payout as paid',
        'driver_invoices.cancel' => 'Cancel a payout',
        'driver_invoices.delete' => 'Delete a cancelled payout',
        'driver_invoices.print' => 'Print a payout statement',
        'driver_invoices.adjust' => 'Record a bonus or a deduction',
        'driver_invoices.assign_driver' => 'Assign a driver to orders',

        // Stock & catalogue
        'stock.view' => 'See the catalogue and stock levels',
        'stock.create_product' => 'Add and edit products',
        'stock.import_products' => 'Import products in bulk (Excel/CSV)',
        'stock.create_inbound' => 'Create stock shipments',
        'stock.adjust' => 'Run stock counts and correct quantities',
        'stock.collect_inbound' => 'Collect stock at the seller',
        'stock.receive_inbound' => 'Receive stock at the warehouse',
        'stock.admin_override' => 'Audit and block stock (all sellers)',

        // Support
        'support.create' => 'Open a ticket',
        'support.read.own' => 'See their own tickets',
        'support.read.all' => 'See every ticket',
        'support.reply' => 'Reply to tickets',
        'support.assign' => 'Assign a ticket to an agent',
        'support.update_status' => 'Change a ticket status',
        'support.close' => 'Close a ticket',
        'support.manage' => 'Manage support (full access)',

        // Shops
        'stores.read' => 'See the shops',
        'stores.create' => 'Create a shop',
        'stores.update' => 'Edit a shop',
        'stores.delete' => 'Delete a shop',

        // Seller team
        'team.read' => 'See the team',
        'team.create' => 'Add a team member',
        'team.update' => 'Edit a team member',
        'team.suspend' => 'Suspend a team member',
        'team_roles.manage' => 'Manage the team roles',

        // Cities
        'cities.read' => 'See the cities',
        'cities.create' => 'Open a city for delivery',
        'cities.update' => 'Edit a city',
        'cities.delete' => 'Delete a city',

        // Sectors
        'sectors.read' => 'See the sectors',
        'sectors.create' => 'Create a sector',
        'sectors.update' => 'Edit a sector',
        'sectors.delete' => 'Delete a sector',
        'sectors.read_driver_price' => 'See the driver payout rate',

        // Driver coverage
        'driver_zones.read' => 'See which sectors drivers cover',
        'driver_zones.assign' => 'Attach a driver to a sector',
        'driver_zones.remove' => 'Detach a driver from a sector',

        // Announcements
        'alerts.read' => 'See the announcements',
        'alerts.create' => 'Publish an announcement',
        'alerts.update' => 'Edit an announcement',
        'alerts.delete' => 'Delete an announcement',

        // Notifications
        'notifications.invoice_generated' => 'Be told when an invoice is issued',
        'notifications.ticket_created' => 'Be told about new tickets',
        'notifications.ticket_message' => 'Be told about ticket replies',
        'notifications.ticket_closed' => 'Be told when a ticket is closed',
        'notifications.return_requested' => 'Be told about return requests',
        'notifications.stock_pickup_requested' => 'Be told about stock ready for collection',
        'notifications.seller_registered' => 'Be told about new seller sign-ups',
        'notifications.system_notifications' => 'Be told about service messages',

        // Partners
        'partners.create' => 'Create a partner',
        'partners.read' => 'See the partners',
        'partners.update' => 'Edit a partner',
        'partners.delete' => 'Delete a partner',
        'partners.sync' => 'Force a synchronisation',
        'partners.deliveries.manage' => 'Handle partner deliveries',

        // E-commerce shops
        'integrations.read' => 'See the connected e-commerce shops',
        'integrations.manage' => 'Connect and configure an e-commerce shop',

        // Users
        'users.read' => 'See the users',
        'users.create' => 'Create a user',
        'users.update' => 'Edit a user',
        'users.delete' => 'Delete a user',
        'users.roles.assign' => 'Assign a role to a user',

        // Roles
        'roles.read' => 'See the roles',
        'roles.create' => 'Create a role',
        'roles.update' => 'Edit the permissions of a role',
        'roles.delete' => 'Delete a role',

        // Permission catalogue
        'permissions.read' => 'Read the permission catalogue (API)',
        'permissions.create' => 'Add a permission to the catalogue (API)',
        'permissions.update' => 'Edit a catalogue permission (API)',
        'permissions.delete' => 'Delete a catalogue permission (API)',
    ],

    'descriptions' => [
        // Dashboard
        'dashboard.view' => 'Open the dashboard. Figures stay limited to the active shop and to the orders the user is allowed to read.',
        'dashboard.view_financials' => 'See cash to collect, collected amounts, revenue and average basket. Worth removing from a role that packs parcels without needing to know the shop takings.',
        'dashboard.view_operations' => 'See the split of orders by status and by city, pending transfers and outstanding work.',
        'dashboard.view_performance' => 'See the delivery success rate, average delays and the driver ranking.',
        'dashboard.view_customers' => 'See the top customers and how many new ones the period brought in.',
        'dashboard.view_network' => 'See the volume per seller as well as the number of active sellers and drivers.',

        // Orders
        'orders.create' => 'Register an order by entering the customer, the delivery address and the amount to collect.',
        'orders.create_with_stock' => 'Build an order from catalogue products: stock is decremented and the amount computed automatically.',
        'orders.read.own' => 'See the orders of the shops the user belongs to, and nothing beyond that.',
        'orders.read.assigned' => 'See only the orders assigned to the signed-in driver.',
        'orders.read.all' => 'See every seller order, with no shop restriction. An operations permission.',
        'orders.update.own' => 'Correct the content of an order while it has not left yet: past the "Created" status a seller can no longer edit it.',
        'orders.update.assigned' => 'Report the outcome of a delivery on the orders assigned to the driver. Does not allow editing the order itself.',
        'orders.update.all' => 'Edit any order at any stage, including the address, the amounts and the assigned driver.',
        'orders.delete.own' => 'Delete an order belonging to their own shops.',
        'orders.delete.all' => 'Delete an order whoever the seller is. Sensitive permission: deletion is final.',
        'orders.export' => 'Download the list as an Excel workbook (.xlsx): tracking, status, failure motive, customer, amounts, driver and seller. The export follows exactly the filters applied on screen.',
        'orders.print' => 'Produce the shipping labels as a PDF, one at a time or in batch, to stick on the parcels.',

        // Pickups
        'pickup_requests.create' => 'Ask for a driver to call at the shop and collect the parcels that are ready.',
        'pickup_requests.read.own' => 'See only the pickups requested by their own shops.',
        'pickup_requests.read.all' => 'See the pickup requests of every seller.',
        'pickup_requests.read.assigned' => 'See only the pickups entrusted to the signed-in driver.',
        'pickup_requests.assign' => 'Name the driver who will call at the seller to collect the parcels.',
        'pickup_requests.change_status' => 'Move a request along from the back office: waiting, picked up, in the warehouse or cancelled.',
        'pickup_requests.pickup' => 'The driver action at the seller: scan the parcels and declare the pickup done.',

        // Transfers
        'transfers.create' => 'Build a manifest of parcels and returns to move from one city to another.',
        'transfers.read' => 'See the inter-city manifests, what they carry and how far along they are.',
        'transfers.read.assigned' => 'See only the manifests the user is carrying.',
        'transfers.update' => 'Add or remove parcels, change the carrier or cancel a manifest, as long as it has not left.',
        'transfers.dispatch' => 'Declare the manifest gone: its parcels move into transit towards the destination city.',
        'transfers.receive' => 'Record the manifest arriving at destination: its parcels become available for local delivery.',

        // Returns
        'returns.create_request' => 'Seller side: ask for a parcel to come back, whether it is still in circulation or already delivered.',
        'returns.create' => 'Driver side: turn a parcel he could not hand over into a return.',
        'returns.read.own' => 'See only the returns of the shops the user belongs to.',
        'returns.read.all' => 'See the returns of every seller.',
        'returns.manage' => 'Full access to returns: create them, move them along and hand them back to the seller. Meant for operations.',
        'returns.update_status' => 'Move a return along its journey without holding full access to return management.',
        'returns.edit_customer_data' => 'Change the name, phone, address or city the parcel must be handed back to, while the return is still open.',

        // Seller invoices
        'invoices.read.own' => 'See the invoices of their own shops and the detail of the parcels billed.',
        'invoices.read.all' => 'See the invoices of every seller.',
        'invoices.generate' => 'Settle a seller over a period: one invoice per shop, covering the delivered and returned parcels not billed yet.',
        'invoices.pay' => 'Record the payment made to the seller and attach the transfer receipt.',
        'invoices.cancel' => 'Cancel an issued invoice. The orders it carried become billable again on the next period.',
        'invoices.delete' => 'Permanently erase an already cancelled invoice. Has no effect on a live one.',
        'invoices.print' => 'Download the seller invoice as a PDF.',

        // Driver payouts
        'driver_invoices.read.own' => 'Driver side: see their own payout statements and the runs that make them up.',
        'driver_invoices.read.all' => 'See the payout statements of every driver. Exposes what each one is paid.',
        'driver_invoices.generate' => 'Settle a driver over a period, from the deliveries made and the bonuses recorded.',
        'driver_invoices.pay' => 'Record the payment made to the driver and attach the receipt.',
        'driver_invoices.cancel' => 'Cancel an issued statement. The lines it carried become available for a later payout.',
        'driver_invoices.delete' => 'Permanently erase an already cancelled payout statement.',
        'driver_invoices.print' => 'Download the driver payout statement as a PDF.',
        'driver_invoices.adjust' => 'Post a bonus, a penalty or a correction on a driver ledger, outside of any statement.',
        'driver_invoices.assign_driver' => 'Name the driver who takes an order on, one at a time or in bulk from the sector dispatch and the partner queue. Despite its technical name this permission is about dispatch, not billing.',

        // Stock & catalogue
        'stock.view' => 'See the product catalogue, stock levels and receiving notes of their shop.',
        'stock.create_product' => 'Create, edit and archive product records one by one.',
        'stock.import_products' => 'Create product records in bulk from an Excel/CSV file. Distinct from creating them one by one: an import can replace a whole catalogue in a single operation.',
        'stock.create_inbound' => 'Prepare a manifest and declare a stock shipment towards our warehouse.',
        'stock.adjust' => 'Correct stock quantities during a count. Every discrepancy demands a reason and stays in an immutable audit trail.',
        'stock.collect_inbound' => 'Travel to the sellers of their cities, count the loaded stock in front of them and ship it to the warehouse. That count becomes the reference for the rest of the journey but credits no stock. A hub-side permission: it cannot be delegated to a seller team, since the whole point of the count is that somebody other than the seller performs it.',
        'stock.receive_inbound' => 'Physically count the stock arriving at the warehouse and credit the quantities actually received. Limited to shipments addressed to the warehouse of their cities. A hub-side permission: it cannot be delegated to a seller team.',
        'stock.admin_override' => 'Audit every stock movement across all shops and block a faulty product. Sensitive permission, reserved for administration.',

        // Support
        'support.create' => 'Open a ticket about an order, an invoice or a pickup.',
        'support.read.own' => 'See only the tickets raised by the signed-in user.',
        'support.read.all' => 'See every ticket in the support centre.',
        'support.reply' => 'Post messages on the tickets they can reach.',
        'support.assign' => 'Assign or reassign a ticket to a support agent.',
        'support.update_status' => 'Change a ticket status: open, in progress, waiting on seller, resolved or closed.',
        'support.close' => 'Close a ticket. Sellers can close their own; staff can close any ticket.',
        'support.manage' => 'Full access to support: see every ticket, assign, change status, reply and close. Meant for support agents.',

        // Shops
        'stores.read' => 'See the shops on the account, their details and the city they belong to.',
        'stores.create' => 'Open an extra shop on the seller account. Each shop keeps its own orders, stock and billing separate.',
        'stores.update' => 'Change the name, address or city of an existing shop.',
        'stores.delete' => 'Close a shop on the account. Reserved for the account holder: a team role can never be granted this permission.',

        // Seller team
        'team.read' => 'See the members attached to the seller account, their role and the shops they can reach.',
        'team.create' => 'Create an access for a team member and give them a role and their shops.',
        'team.update' => 'Change the role, the shops or the details of a team member.',
        'team.suspend' => 'Cut a team member access. Their open sessions are closed at once and sign-in is refused.',
        'team_roles.manage' => 'Create tailored roles for the team (packer, customer care…) and tick their permissions. A team role can never exceed the rights of the seller account itself, nor grant itself shop or team management.',

        // Cities
        'cities.read' => 'See the cities served, their code, their region and the warehouses they hold.',
        'cities.create' => 'Open a new city for delivery. It becomes selectable on orders, shops and transfers.',
        'cities.update' => 'Rename a city, change its region or deactivate it. A deactivated city drops out of the dropdowns without touching existing orders.',
        'cities.delete' => 'Remove a city from the network. Refused as long as it still holds active sectors.',

        // Sectors
        'sectors.read' => 'See the delivery rounds of each city, their seller rates and their announced lead times.',
        'sectors.create' => 'Split a city into a new round and set its delivery rate, return rate and lead time.',
        'sectors.update' => 'Change the scope, the rates or the announced lead time of an existing round.',
        'sectors.delete' => 'Remove a round from the delivery network.',
        'sectors.read_driver_price' => 'Show what the driver is paid for a delivery in the sector. Confidential figure: without this permission it appears neither on screen, nor in the API, nor in the forms, and a value sent anyway is ignored.',

        // Driver coverage
        'driver_zones.read' => 'See which rounds each driver covers.',
        'driver_zones.assign' => 'Add a round to a driver beat. He then becomes a candidate for automatic assignment and for the sector dispatch of that round.',
        'driver_zones.remove' => 'Detach a round from a driver: he stops being offered the orders of that sector.',

        // Announcements
        'alerts.read' => 'Open the list of announcements published on the platform, with their audience and end date.',
        'alerts.create' => 'Publish a message as a banner at the top of every page or as a window at sign-in, targeted by role, by city or at named people.',
        'alerts.update' => 'Change the wording, the audience or the end date of an announcement already published.',
        'alerts.delete' => 'Permanently remove an announcement; its recipients stop seeing it at once.',

        // Notifications
        'notifications.invoice_generated' => 'Be notified every time a seller invoice is issued. Useful to the seller and to accounting, pointless for a driver.',
        'notifications.ticket_created' => 'Be notified when a support ticket is opened.',
        'notifications.ticket_message' => 'Be notified of every new message on a ticket being followed.',
        'notifications.ticket_closed' => 'Be notified when a ticket is closed.',
        'notifications.return_requested' => 'Be notified when a seller asks for a parcel back. Meant for operations.',
        'notifications.stock_pickup_requested' => 'Be notified when a seller declares stock ready for collection. Meant for the collectors of the city concerned.',
        'notifications.seller_registered' => 'Be notified every time a seller account is created and waits for approval. Best kept to the desk that approves sign-ups.',
        'notifications.system_notifications' => 'Receive the service messages that fall into no other topic, such as a ticket being assigned or a platform announcement.',

        // Partners
        'partners.create' => 'Register a new B2B partner and its API credentials.',
        'partners.read' => 'See partner configurations, city mappings and API call logs.',
        'partners.update' => 'Change the settings, credentials, cities and mappings of a partner.',
        'partners.delete' => 'Delete a partner integration.',
        'partners.sync' => 'Run a "sync now" ingestion to pull a partner deliveries immediately.',
        'partners.deliveries.manage' => 'Work the partner parcel queue: scan, change statuses in bulk and assign drivers.',

        // E-commerce shops
        'integrations.read' => 'See the e-commerce shops linked to the account and their sync health.',
        'integrations.manage' => 'Link, reconfigure or disconnect a Shopify, YouCan, WooCommerce or PrestaShop store. Grants access to the shop API keys.',

        // Users
        'users.read' => 'Reach the list and the records of platform accounts, including sign-ups awaiting approval.',
        'users.create' => 'Create user accounts: sellers, drivers or internal staff.',
        'users.update' => 'Change the details and the state of a user account.',
        'users.delete' => 'Delete a user account.',
        'users.roles.assign' => 'Grant or remove a role on a user. Sensitive permission: it allows privilege escalation.',

        // Roles
        'roles.read' => 'See the platform roles and the permissions each one carries.',
        'roles.create' => 'Define a new role and pick the permissions it carries.',
        'roles.update' => 'Add or remove permissions on an existing role. The change applies at once to everyone holding it. Sensitive permission.',
        'roles.delete' => 'Remove a role from the platform.',

        // Permission catalogue
        'permissions.read' => 'Read the technical permission list through the API. Day-to-day granting happens on the roles screen, not here.',
        'permissions.create' => 'Create an entry in the permission catalogue through the API. A technical operation with no dedicated screen: the catalogue is normally fed by migrations.',
        'permissions.update' => 'Edit an entry of the permission catalogue through the API. A technical operation with no dedicated screen.',
        'permissions.delete' => 'Delete a catalogue entry through the API. Removing a permission revokes it for every role that carried it.',
    ],
];
