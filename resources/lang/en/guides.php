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
        'stock' => 'Stock',
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
        'stock_catalog' => [
            'title' => 'Manage your product catalog',
            'summary' => 'Create a reference end to end: name and barcode, selling and cost price, weight and dimensions, photo. You will also learn to read the out-of-stock indicators and to import a whole catalog from Excel.',
            'audience' => 'Seller, Team',
        ],
        'stock_shipment' => [
            'title' => 'Send stock to the depot',
            'summary' => 'Prepare a shipment slip: products and quantities, destination depot, dispatch date. You will then follow the collection at your shop and the count on arrival.',
            'audience' => 'Seller, Team',
        ],
        'stock_inventory' => [
            'title' => 'Count your stock',
            'summary' => 'Count your references in a single pass: keyboard or scanner entry, gaps computed live, a mandatory motive on every correction and a full audit trail on the product sheet.',
            'audience' => 'Seller, Team',
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

        'stock_catalog' => [
            'welcome' => [
                'title' => 'Your product catalog',
                'body' => 'The catalog is the list of what you sell. Once your references exist, you build orders by picking them instead of typing an amount by hand.',
            ],
            'summary' => [
                'title' => 'The state of your stock at a glance',
                'body' => 'References, available units, out-of-stock and low-stock counts, total value. This is the row to read on arrival: it tells you whether to restock before you even look at the table.',
            ],
            'list' => [
                'title' => 'Finding a reference',
                'body' => 'Search by name, SKU or barcode, or filter by category and stock level. An archived product leaves the sales flow without being deleted: its history stays readable.',
            ],
            'create' => [
                'title' => 'Create your first reference',
                'body' => 'Every product sold from your stock needs a sheet. That sheet carries the price, the barcode and the dimensions used everywhere else.',
                'hint' => 'Click "New product" to open the form.',
            ],
            'identity' => [
                'title' => 'The product identity',
                'body' => 'Only the name is required: leave the SKU empty and we generate one. The barcode is what lets the product be scanned at preparation and at inventory — fill it in if your items carry one.',
            ],
            'pricing' => [
                'title' => 'Selling price and cost price',
                'body' => 'The selling price is carried over automatically when you add the product to an order. The cost price stays private: it only feeds the margin shown just below.',
            ],
            'logistics' => [
                'title' => 'Fragility, weight and dimensions',
                'body' => 'A product flagged as fragile passes the warning on to the driver on every order containing it. Weight and dimensions are optional, but they are what lets a parcel be estimated before it ships.',
            ],
            'media' => [
                'title' => 'The photo and the product state',
                'body' => 'A photo makes the reference recognisable at a glance in lists and at preparation. The switch at the bottom pulls the product out of sales without touching its stock or its history.',
            ],
            'submit' => [
                'title' => 'Save the sheet',
                'body' => 'Note that the stock quantity is not entered here: it is the result of movements. A product is born at zero and fills up through a depot reception or an inventory count.',
                'hint' => 'Save the product to continue.',
            ],
            'import' => [
                'title' => 'Importing a whole catalog',
                'body' => 'To start with dozens of references, do not type them one by one: download the Excel template, fill it in and import it. Columns are matched automatically and you fix the errors before validating.',
            ],
            'done' => [
                'title' => 'Your catalog is live!',
                'body' => 'Next step: getting the goods in. The "Send stock to the depot" guide shows you how to declare a shipment and follow its arrival.',
            ],
        ],

        'stock_shipment' => [
            'welcome' => [
                'title' => 'Sending stock to the depot',
                'body' => 'Your products are warehoused with us before being delivered. A shipment slip declares what you are sending: it is the document the collector counts at your shop, then the depot on arrival.',
            ],
            'create' => [
                'title' => 'Create a shipment slip',
                'body' => 'This page lists your past and pending shipments, with what was declared, collected and actually received. Let us create a new one.',
                'hint' => 'Click "New shipment" to open the form.',
            ],
            'items' => [
                'title' => 'What the shipment contains',
                'body' => 'Search by name, SKU or barcode, then enter the quantity sent. Out-of-stock products show up too: sending stock in is exactly what you do about an empty shelf. The per-line note is for flagging a batch or a defect.',
            ],
            'shipping' => [
                'title' => 'Destination and date',
                'body' => 'The destination depot is where your goods will be warehoused. You only choose it on the first shipment: the following ones are attached to it automatically. Shipping notes are read by the collector.',
            ],
            'submit' => [
                'title' => 'Draft or collection request',
                'body' => 'Two exits: a draft stays editable, a collection request freezes the quantities because that is the document the collector will count in front of you.',
                'hint' => 'Save the shipment to continue.',
            ],
            'actions' => [
                'title' => 'Moving the shipment along',
                'body' => 'While it is a draft, the shipment can be edited and "Request collection" puts it in the queue. A driver will come and count the parcels at your shop before taking them to the depot.',
            ],
            'timeline' => [
                'title' => 'Following the arrival',
                'body' => 'Every step is timestamped and signed: collection at your shop, transport, count at the depot. Your stock is only credited on final validation, on the quantities actually counted on arrival — not on those declared at departure.',
            ],
            'done' => [
                'title' => 'Shipment recorded!',
                'body' => 'You will follow its progress from the shipment list. When declared and received differ, the sheet tells you exactly where the gap is.',
                'cta' => 'See my shipments',
            ],
        ],

        'stock_inventory' => [
            'welcome' => [
                'title' => 'Counting your stock',
                'body' => 'An inventory compares what the system believes it holds with what is actually on the shelf. It all happens on one sheet: count, justify the gaps, save.',
            ],
            'summary' => [
                'title' => 'The starting point',
                'body' => 'The references and units the system records today, and the value they represent. That is the figure your count will confirm or correct.',
            ],
            'filters' => [
                'title' => 'Count zone by zone',
                'body' => 'A full inventory is rarely done in one block. Filter on a category or a search to see only the shelf in front of you: counts already entered are kept when you change filter or turn the page.',
            ],
            'sheet' => [
                'title' => 'The counting sheet',
                'body' => 'Three columns: what is recorded, what you count, the gap computed live. On a computer, Enter and the arrow keys walk down the column — the whole sheet fills without leaving the keyboard or touching the mouse.',
            ],
            'match_all' => [
                'title' => 'Everything matches?',
                'body' => 'This button copies the recorded stock onto every line still empty. Handy to close a zone where nothing moved: a line counted without a gap is traced, but creates no stock movement.',
            ],
            'reason' => [
                'title' => 'A gap owes a motive',
                'body' => 'Enter a count different from the recorded stock: the "Reason" column opens and becomes mandatory. Breakage, theft, entry mistake, unrecorded return — that motive is what will make the gap readable six months from now.',
                'hint' => 'Enter a quantity different from the recorded stock on one line.',
            ],
            'save' => [
                'title' => 'Save the count',
                'body' => 'The bar stays within reach as long as lines are pending. On save, only the gaps correct the stock — and every counted line is traced on the product sheet with your name, the time, the machine used and, if your browser allows it, your position.',
            ],
            'done' => [
                'title' => 'You know how to count!',
                'body' => 'Find every count in the "Inventories" tab of the product sheet, and every correction in the movement history. Nothing can be edited afterwards: that is what gives the trail its value.',
                'cta' => 'See my catalog',
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
