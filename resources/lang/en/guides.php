<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Help center
    |--------------------------------------------------------------------------
    */
    'page_title' => 'Help',
    'title' => 'User guide',
    'subtitle' => 'Guided walkthroughs, right inside the app. Pick a guide: we take you to the right screen and walk you through it step by step.',
    'search' => 'Search a guide…',
    'empty' => 'No guide matches your search.',
    'empty_catalog' => 'No guide is available for your role yet.',
    'available' => '{count} guide available|{count} guides available',
    'completed_count' => '{completed} of {total} completed',

    'card' => [
        'steps' => '{count} step|{count} steps',
        'minutes' => '{count} min',
        'start' => 'Start the guide',
        'resume' => 'Resume at step {step}',
        'replay' => 'Replay the guide',
        'reset' => 'Reset',
        'reset_confirm' => 'Forget your progress on this guide?',
    ],

    'status' => [
        'new' => 'New',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'completed_times' => 'Taken {count} times',
    ],

    'categories' => [
        'orders' => 'Orders',
        'pickups' => 'Pickups',
        'returns' => 'Returns',
        'invoices' => 'Billing',
        'finance' => 'Cash',
        'stores' => 'Stores',
        'team' => 'Team',
        'settings' => 'Settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tour controls
    |--------------------------------------------------------------------------
    */
    'tour' => [
        'progress' => 'Step {current} of {total}',
        'start' => 'Start',
        'next' => 'Next',
        'previous' => 'Previous',
        'finish' => 'Finish',
        'quit' => 'Quit the guide',
        'quit_short' => 'Quit',
        'quit_confirm_title' => 'Quit the guide?',
        'quit_confirm_text' => 'Your progress is saved: you can resume from the help center.',
        'quit_confirm_yes' => 'Yes, quit',
        'quit_confirm_no' => 'Keep going',
        'waiting' => 'Your turn',
        'loading' => 'Looking for the element…',
        'lost_title' => 'Element not found',
        'lost_body' => 'The screen changed since the guide started. Restart it from the help center.',
        'lost_restart' => 'Go back to the right screen',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guides per role (administration screen)
    |--------------------------------------------------------------------------
    */
    'access' => [
        'title' => 'Guides per role',
        'subtitle' => 'Choose which roles are offered each interactive guide in the help center.',
        'back_to_roles' => 'Back to roles',
        'guide_column' => 'Guide',
        'toggle_column' => 'Check / uncheck every guide for this role',
        'unrestricted' => 'All roles',
        'not_playable' => 'No walkthrough',
        'unrestricted_help' => 'A guide with no role checked stays offered to every role: silence means "no restriction", never "hidden from everyone".',
        'permission_help' => 'The permissions shown still win: a role that cannot open the screen will not see the guide, even when checked.',
        'saved' => 'Guide access updated.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog — one entry per guide key (dashes become underscores)
    |--------------------------------------------------------------------------
    */
    'catalog' => [
        'orders_create' => [
            'title' => 'Create an order with the form',
            'summary' => 'Enter an order end to end: customer details, city and sector, package options, payment method and amounts.',
            'audience' => 'Seller, Team',
        ],
        'orders_import' => [
            'title' => 'Bulk order import',
            'summary' => 'Import dozens of orders from an Excel or CSV file: template, column mapping, error fixing and final validation.',
            'audience' => 'Seller, Administrator',
        ],
        'pickups_create' => [
            'title' => 'Create a pickup request',
            'summary' => 'Group your ready orders into a pickup request: order selection, pickup address and instructions for the driver.',
            'audience' => 'Seller, Team',
        ],
        'returns_request' => [
            'title' => 'Request a return',
            'summary' => 'Send a parcel back to your store: eligible orders, return reason and request tracking.',
            'audience' => 'Seller',
        ],
        'invoices_read' => [
            'title' => 'Read your invoices',
            'summary' => 'Understand an invoice line by line: period, delivered amounts, delivery and return fees, net payout and PDF export.',
            'audience' => 'Seller, Administrator',
        ],
        'stores_manage' => [
            'title' => 'Add and switch stores',
            'summary' => 'Create a store and learn to switch between them: every order, invoice and pickup belongs to the active store.',
            'audience' => 'Seller',
        ],
        'team_member' => [
            'title' => 'Add a member to your team',
            'summary' => 'Create a sub-user on your seller account: custom roles, accessible stores and login password.',
            'audience' => 'Seller',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tour steps — guides.tours.<key>.<step id>.{title,body,hint}
    |--------------------------------------------------------------------------
    */
    'tours' => [
        'orders_import' => [
            'welcome' => [
                'title' => 'Welcome to the bulk import guide!',
                'body' => 'Let\'s go through it together. In under 3 minutes you will know how to turn a spreadsheet into orders ready to be picked up.',
            ],
            'template' => [
                'title' => 'Download the template',
                'body' => 'Click here to get the exact required format. The file ships with a sample row: keep the header line and replace the data.',
            ],
            'dropzone' => [
                'title' => 'Drop your file',
                'body' => 'Drag your filled file here, or click to browse. We read it in your browser: nothing is sent until you validate.',
                'hint' => 'Pick a .xlsx or .csv file to continue.',
            ],
            'mapping' => [
                'title' => 'Check the column mapping',
                'body' => 'Make sure your spreadsheet columns (Name, Phone, Address, Price) line up with the system fields. Auto-detected matches carry a star — override the ones that are wrong.',
                'hint' => 'Click "Next" to open the column mapping.',
            ],
            'review' => [
                'title' => 'Preview and fix',
                'body' => 'Fix any error live before validating: red rows are blocking, orange rows flag a duplicate. Every cell is editable right here.',
                'hint' => 'Click "Validate mapping" to open the preview.',
            ],
            'save' => [
                'title' => 'Run the import',
                'body' => 'Verify the list first, then save. The button stays grey while an error remains or an edit has not been re-verified.',
                'hint' => 'Save the list to finish the guide.',
            ],
            'done' => [
                'title' => 'Congratulations!',
                'body' => 'Your orders are imported and now show up in your list. You can replay this guide any time from the help center.',
                'cta' => 'See my orders',
            ],
        ],

        'orders_create' => [
            'welcome' => [
                'title' => 'Let\'s create your first order',
                'body' => 'The form is three blocks: the customer, the parcel, then the payment. We go through them in that order.',
            ],
            'customer' => [
                'title' => 'Customer details',
                'body' => 'Name, phone, then city and sector: the sector is what sets the delivery price, which is why it is required. Make the address precise enough for the driver to find it first time.',
            ],
            'package' => [
                'title' => 'The parcel and its options',
                'body' => 'Flag a fragile parcel here, allow or forbid opening before payment, and tick exchange when the driver has to leave with another parcel. Notes are read by the driver.',
            ],
            'payment' => [
                'title' => 'Payment and amounts',
                'body' => 'On cash on delivery, enter the amount to collect from the customer. For an order already paid, only declare the parcel value — it is what counts in a dispute.',
            ],
            'submit' => [
                'title' => 'Save the order',
                'body' => 'Save to open the order sheet, or use "Create and new" to chain a second entry without going back to the list.',
                'hint' => 'Save the order to continue.',
            ],
            'done' => [
                'title' => 'Order created!',
                'body' => 'It now sits in "Created" and waits for a pickup. The natural next step: attach it to a pickup request.',
                'cta' => 'See my orders',
            ],
        ],

        'pickups_create' => [
            'welcome' => [
                'title' => 'Request a pickup',
                'body' => 'A pickup request groups the orders a driver will come and collect from you. Let\'s create one.',
            ],
            'open' => [
                'title' => 'Open the form',
                'body' => 'Every past request is on this page with its status. Let\'s start a new one.',
                'hint' => 'Click "New request" to open the form.',
            ],
            'orders' => [
                'title' => 'Pick the orders',
                'body' => 'Only created orders not already attached to a pickup show up here. Tick the ones the driver will take.',
                'hint' => 'Select at least one order.',
            ],
            'address' => [
                'title' => 'Set the pickup address',
                'body' => 'This is where the driver will show up. Your store address is offered by default; you can enter another one for this pickup only.',
                'hint' => 'Click "Next" to move on to the address.',
            ],
            'summary' => [
                'title' => 'Check and add your instructions',
                'body' => 'The summary recalls the number of parcels. Notes are visible to the driver: preferred time slot, floor, person to ask for.',
                'hint' => 'Click "Next" to open the summary.',
            ],
            'submit' => [
                'title' => 'Send the request',
                'body' => 'Once sent, the request waits for assignment: a driver will be put on it, and you follow its progress from the list.',
                'hint' => 'Send the request to finish the guide.',
            ],
            'done' => [
                'title' => 'Request sent!',
                'body' => 'You are on its tracking sheet. The status will move on until your parcels are actually collected.',
            ],
        ],

        'returns_request' => [
            'welcome' => [
                'title' => 'Request a parcel return',
                'body' => 'A return brings a parcel back to your store — customer refusal, wrong item, address not found. Here is how.',
            ],
            'open' => [
                'title' => 'Open the form',
                'body' => 'This page lists your ongoing returns and their status. Let\'s create a new one.',
                'hint' => 'Click "New request" to open the form.',
            ],
            'order' => [
                'title' => 'Pick the order',
                'body' => 'Only orders already in circulation are eligible, and an order can only have one active return at a time. If yours is missing, it is one of those two reasons.',
                'hint' => 'Select the order concerned.',
            ],
            'reason' => [
                'title' => 'State the reason',
                'body' => 'The reason drives how the parcel is handled on arrival, and your notes are read by the logistics team: be precise, it saves a round trip.',
                'hint' => 'Choose a return reason.',
            ],
            'submit' => [
                'title' => 'Send the request',
                'body' => 'The request goes out for approval. Once accepted, the parcel is scheduled to come back to your store.',
                'hint' => 'Send the request to finish the guide.',
            ],
            'done' => [
                'title' => 'Return requested!',
                'body' => 'You will follow its progress from this sheet, and from the returns list.',
            ],
        ],

        'invoices_read' => [
            'welcome' => [
                'title' => 'Understanding your invoices',
                'body' => 'An invoice groups the orders delivered over a period and works out what you are owed. Let\'s break it down.',
            ],
            'filters' => [
                'title' => 'Finding an invoice',
                'body' => 'Filter by number, by status or by generation period. The status tells you where the payment stands: pending, paid or cancelled.',
            ],
            'table' => [
                'title' => 'Reading the list',
                'body' => 'Each line sums up an invoice: period covered, number of orders and net payout. Numeric columns sort on click.',
            ],
            'open' => [
                'title' => 'Open an invoice',
                'body' => 'The detail is where it all happens: the amount summary and the list of orders behind it.',
                'hint' => 'Open an invoice to continue.',
            ],
            'summary' => [
                'title' => 'The summary',
                'body' => 'Delivered amount minus delivery fees, minus return fees: the result is the net payout, what will be paid to you. Return fees cover the parcels that came back.',
            ],
            'orders' => [
                'title' => 'The per-order detail',
                'body' => 'Every billed order appears with its final amount. This is where to look when a total surprises you.',
            ],
            'pdf' => [
                'title' => 'Export the invoice',
                'body' => 'View the PDF or download it for your accounting. The document carries exactly the amounts shown here.',
            ],
            'done' => [
                'title' => 'You can read an invoice!',
                'body' => 'If an amount looks wrong, open a support ticket quoting the invoice number and the order concerned.',
            ],
        ],

        'stores_manage' => [
            'welcome' => [
                'title' => 'Managing your stores',
                'body' => 'A store holds its own orders, invoices and pickups. Let\'s create one, then see how to switch between them.',
            ],
            'create' => [
                'title' => 'Create a store',
                'body' => 'You can run several stores from a single account — one per brand, per city or per activity.',
                'hint' => 'Click "Create store" to continue.',
            ],
            'identity' => [
                'title' => 'The store identity',
                'body' => 'The name is what your customers and the logistics team will see. The category helps sorting when you run several; an inactive store stops accepting new orders.',
            ],
            'branding' => [
                'title' => 'The logo',
                'body' => 'The logo shows in the store switcher and on your documents. Optional, but it is what makes switching readable at a glance.',
            ],
            'contact' => [
                'title' => 'Contact and address',
                'body' => 'This address is offered by default as the pickup point of your requests: fill it carefully, it saves you time on every single one.',
            ],
            'submit' => [
                'title' => 'Save',
                'body' => 'Your store will be available immediately in the switcher, at the top of the screen.',
                'hint' => 'Save the store to continue.',
            ],
            'switcher' => [
                'title' => 'Switching the active store',
                'body' => 'Here is the switcher: the active store decides what you see and what you create. An order entered here belongs to the store displayed — check it before you type.',
            ],
            'done' => [
                'title' => 'All set!',
                'body' => 'Your store exists and you know how to switch. A useful next step: bring your team in.',
            ],
        ],

        'team_member' => [
            'welcome' => [
                'title' => 'Add a member to your team',
                'body' => 'A sub-user works on your account with their own credentials, and only on what you allow them to see.',
            ],
            'roles' => [
                'title' => 'Roles first',
                'body' => 'A role is a set of permissions you define once and reuse: "order entry", "delivery follow-up". Create it before the member and you can assign it straight away.',
            ],
            'create' => [
                'title' => 'Create the member',
                'body' => 'Every member has their own account: actions stay traced to their name, and you can suspend one without touching the others.',
                'hint' => 'Click "Add member" to continue.',
            ],
            'identity' => [
                'title' => 'Their identity',
                'body' => 'The email is the login: it must be unique and belong to them. The phone number lets the logistics team reach them.',
            ],
            'access' => [
                'title' => 'Their access',
                'body' => 'Choose the stores they may open and the roles that define their rights. At least one store and one role are required — otherwise they would log into an empty screen.',
            ],
            'security' => [
                'title' => 'Their password',
                'body' => 'You set the initial password and hand it over. They can change it from their profile after the first login.',
            ],
            'submit' => [
                'title' => 'Save',
                'body' => 'The account is active straight away: your colleague can log in as soon as you give them the credentials.',
                'hint' => 'Save the member to finish the guide.',
            ],
            'done' => [
                'title' => 'Member added!',
                'body' => 'You can change their access any time, or suspend the account from the team list.',
            ],
        ],
    ],
];
