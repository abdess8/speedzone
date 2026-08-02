<?php

return [
    'title' => 'API documentation',
    'subtitle' => 'Connect your shop, your ERP or your e-commerce platform to SpeedZone and manage your shipments programmatically.',
    'page_title' => 'API Integrations',

    'search' => [
        'placeholder' => 'Search an endpoint…',
        'empty' => 'No endpoint matches ":query".',
    ],

    'actions' => [
        'copy' => 'Copy',
        'copied' => 'Copied',
        'manage_tokens' => 'Manage my tokens',
        'back_to_top' => 'Back to top',
        'toggle_nav' => 'Contents',
        'download_postman' => 'Postman collection',
        'downloaded' => 'Downloaded',
    ],

    // Variable names are written without their braces on purpose: the Vue i18n
    // message compiler reads `{…}` as a placeholder.
    'postman' => [
        'collection_name' => 'SpeedZone API',
        'hint' => 'Import it into Postman to exercise every endpoint against your own account. The requests are chained, so one run of the collection creates an order, reads it back, edits it and books its pickup.',
        'token_embedded' => 'The token you pasted above will be written into the downloaded file. Treat that file like a password.',
        'description' => "Ready-to-run requests for the SpeedZone delivery API.\n\n### Before you start\n\n1. Open the collection variables and paste your personal token into `token`. You create one from your SpeedZone account, under API tokens.\n2. Check that `baseUrl` points at the environment you mean to hit.\n3. Multi-store account? Set `storeId` to the shop you want to act on. Leave it empty to use your default shop.\n\n### Running the whole collection\n\nThe folders are ordered so that a full run works end to end. The reference requests fill in `cityId` and `sectorId`, creating an order stores its `orderId` and `trackingNumber`, and every request after that reuses them.\n\nDelete an order is wired to its own `deletableOrderId` variable, left empty on purpose, so running the collection can never destroy the order the other requests are still using. Set it by hand when you want to test the deletion.\n\n### Limits\n\nCalls are capped at :limit requests per minute, counted per token. Every response carries the remaining quota in `X-RateLimit-Remaining`.",
    ],

    'console' => [
        'title' => 'Your credentials',
        'description' => 'Every call is authenticated with a personal token. Create one from your account, then paste it into the :header header.',
        'base_url' => 'Base URL',
        'auth_header' => 'Authentication header',
        'store_header' => 'Store header',
        'store_hint' => 'Only useful if your account holds several stores.',
        'no_store' => 'Your account has a single store — you can omit this header.',
        'token_placeholder' => 'YOUR_API_TOKEN',
        'token_notice' => 'A token is shown only once, when you create it. Store it somewhere safe: it grants access to all of your orders.',
    ],

    'nav' => [
        'getting_started' => 'Getting started',
        'orders' => 'Orders',
        'pickups' => 'Pickup requests',
        'reference' => 'Reference data',
    ],

    'labels' => [
        'endpoint' => 'Endpoint',
        'headers' => 'Headers',
        'query_params' => 'Query parameters',
        'path_params' => 'Path parameters',
        'body_params' => 'Body parameters',
        'responses' => 'Responses',
        'request_example' => 'Request',
        'response_example' => 'Response',
        'required' => 'required',
        'optional' => 'optional',
        'permission' => 'Permission',
        'default' => 'Default',
        'notes' => 'Good to know',
        'no_body' => 'This endpoint takes no body.',
        'no_params' => 'This endpoint takes no parameter.',
        'name' => 'Name',
        'type' => 'Type',
        'description' => 'Description',
        'status_code' => 'Code',
        'value' => 'Value',
        'label' => 'Label',
        'meaning' => 'Meaning',
    ],

    'sections' => [
        'introduction' => [
            'title' => 'Introduction',
            'lead' => 'The SpeedZone API is a REST API over HTTPS. It accepts and returns JSON, and lets you create shipments, follow them through the delivery workflow and pull back their status without ever opening the dashboard.',
            'conventions_title' => 'Conventions',
            'conventions' => [
                'json' => 'Every request body is JSON, and every response is JSON.',
                'wrapper' => 'A single resource comes back wrapped in a `data` object. A list adds `links` and `meta` for pagination.',
                // The example is injected rather than inlined: the Vue i18n bridge
                // rewrites Laravel's `:name` placeholders, and the colons of an ISO
                // time would be mistaken for one.
                'dates' => 'All timestamps are ISO 8601 strings in UTC, for example :example.',
                'amounts' => 'All amounts are numbers in Moroccan dirhams (MAD), never strings.',
                'ids' => 'Order identifiers in URLs are numeric. To look an order up by its tracking number, use the dedicated tracking endpoint.',
            ],
            'accept_title' => 'Always send the Accept header',
            'accept_body' => 'Without `Accept: application/json`, an unauthenticated call is redirected to the login page and you get HTML back instead of a 401. This is the single most common integration mistake.',
        ],

        'authentication' => [
            'title' => 'Authentication',
            'lead' => 'The API uses personal bearer tokens. A token belongs to your user account and inherits its permissions: it can never reach data you cannot see in the dashboard.',
            'create_title' => 'Create a token',
            'create_steps' => [
                'open' => 'Open :link in your account.',
                'name' => 'Give the token a name that says where it will be used, for example "Shopify production".',
                'abilities' => 'Tick the abilities it needs: `read`, `create`, `update`, `delete`.',
                'copy' => 'Copy the token immediately — it is displayed once and never shown again.',
            ],
            'usage_title' => 'Use the token',
            'usage_body' => 'Send it on every call in the `Authorization` header, prefixed with `Bearer`.',
            'abilities_title' => 'Abilities',
            'abilities_body' => 'A token restricted to `read` can list and show resources but is rejected on any write. Grant the narrowest set that gets the job done.',
            'revoke_title' => 'Revoking a token',
            'revoke_body' => 'Deleting a token from your account takes effect immediately: every call still using it starts returning 401.',
        ],

        'stores' => [
            'title' => 'Multi-store accounts',
            'lead' => 'If your account holds several stores, orders, pickups and invoices are isolated per store. A call that does not say which store it targets is served from your default store.',
            'header_body' => 'To target another store, send its identifier in the `X-Store-Id` header. A store you are not a member of is silently ignored and the call falls back to your default store.',
            'team_title' => 'Team members',
            'team_body' => 'A token created by a team member reads and writes on behalf of the vendor account he belongs to, narrowed to the stores he has access to.',
            'your_stores' => 'Stores reachable with your account',
            'store_id' => 'Store ID',
            'store_name' => 'Store',
            'store_default' => 'Default',
        ],

        'errors' => [
            'title' => 'Errors',
            'lead' => 'The API uses standard HTTP status codes. Any 4xx or 5xx response carries a `message`, and validation failures add an `errors` object keyed by field name.',
            'codes_title' => 'Status codes',
            // Keys are prefixed so they stay strings: PHP casts a numeric array key
            // to int, which makes the resulting i18n path awkward to resolve.
            'codes' => [
                'c200' => 'The call succeeded.',
                'c201' => 'The resource was created.',
                'c204' => 'The call succeeded and there is nothing to return.',
                'c401' => 'Missing, malformed or revoked token.',
                'c403' => 'The token is valid but your account lacks the permission, or the resource belongs to someone else.',
                'c404' => 'No such resource, or it is outside the store you are currently targeting.',
                'c422' => 'The payload failed validation. Read the `errors` object to know which field.',
                'c429' => 'Rate limit exceeded.',
                'c500' => 'Something broke on our side. Retry, then contact support if it persists.',
            ],
            'validation_title' => 'Validation errors',
            'validation_body' => 'A 422 lists every field that failed, each with one or more messages. Fields absent from `errors` were accepted.',
        ],

        'rate_limits' => [
            'title' => 'Rate limiting',
            'lead' => 'Calls are capped at :limit requests per minute, counted per token. Going over returns 429 with a `Retry-After` header telling you how many seconds to wait.',
            'headers_title' => 'Quota headers',
            'headers_body' => 'Every response carries `X-RateLimit-Limit` and `X-RateLimit-Remaining` so you can throttle before being blocked.',
            'advice_title' => 'Staying under the limit',
            'advice_body' => 'Batch your reads with `per_page` rather than fetching orders one at a time, and back off exponentially when you do hit a 429.',
        ],

        'statuses' => [
            'title' => 'Order statuses',
            'lead' => 'An order moves through a fixed workflow. Statuses are uppercase strings — filter on them with the `status` query parameter.',
            'groups_title' => 'Status shortcuts',
            'groups_body' => 'The `status_group` parameter selects a whole bucket of statuses at once, which is handy for dashboards.',
            'transitions_title' => 'Who moves an order forward',
            'transitions_body' => 'A vendor account cannot push an order through the workflow directly. You create the order, then request a pickup; from that point the status is driven by our operations teams and by the driver. Your integration reads the status, it does not write it.',
            'group_pickup' => 'Awaiting pickup',
            'group_delivery' => 'Out for delivery',
            'group_delivered' => 'Delivered',
            'group_failed' => 'Failed or refused',
        ],
    ],

    'endpoints' => [
        'orders_list' => [
            'title' => 'List orders',
            'description' => 'Returns your orders, most recent first, paginated. Every filter below can be combined.',
        ],
        'orders_create' => [
            'title' => 'Create an order',
            'description' => 'Registers a new shipment. The order starts in `CREATED` and gets a tracking number straight away. Delivery price is taken from the sector when you leave it out.',
        ],
        'orders_show' => [
            'title' => 'Retrieve an order',
            'description' => 'Returns one order with its city, sector, pickup request, status timeline and change history.',
        ],
        'orders_track' => [
            'title' => 'Retrieve an order by tracking number',
            'description' => 'Same payload as retrieving by ID, but keyed on the tracking number your customer sees. Use this when your system only stores the tracking number.',
        ],
        'orders_update' => [
            'title' => 'Update an order',
            'description' => 'Corrects an order that has not been picked up yet. Send only the fields you want to change.',
        ],
        'orders_delete' => [
            'title' => 'Delete an order',
            'description' => 'Permanently removes an order belonging to your account.',
        ],
        'orders_tracking' => [
            'title' => 'Order status timeline',
            'description' => 'Returns every status the order went through, oldest first, with who changed it and when. This is what powers a "track my parcel" page.',
        ],
        'orders_pdf' => [
            'title' => 'Download the shipping label',
            'description' => 'Streams the thermal shipping label as a PDF, ready to print. The response is `application/pdf`, not JSON.',
        ],

        'pickups_list' => [
            'title' => 'List pickup requests',
            'description' => 'Returns the pickup requests raised by your account, most recent first.',
        ],
        'pickups_create' => [
            'title' => 'Request a pickup',
            'description' => 'Asks a driver to collect a batch of orders. Each order must be yours, still in `CREATED`, and not already attached to another pickup request.',
        ],
        'pickups_show' => [
            'title' => 'Retrieve a pickup request',
            'description' => 'Returns one pickup request with its orders, assigned driver and status history.',
        ],

        'cities_list' => [
            'title' => 'List cities',
            'description' => 'Returns the cities we deliver to. You need a `city_id` from here to create an order.',
        ],
        'city_sectors' => [
            'title' => 'List a city\'s sectors',
            'description' => 'Returns the active sectors of one city, with their delivery price. Use it to build the dependent city/sector selector in your own interface.',
        ],
        'sectors_list' => [
            'title' => 'List sectors',
            'description' => 'Returns every sector across all cities, paginated. Prefer the per-city endpoint when you are filling a dropdown.',
        ],
        'user_me' => [
            'title' => 'Current account',
            'description' => 'Returns the account the token belongs to. Handy as a health check: if this returns 200, your token and headers are correct.',
        ],
    ],

    'notes' => [
        'orders_update_status' => 'Only an order still in `CREATED` can be edited. Once it is picked up, the call returns 403.',
        'orders_update_sector' => 'When you change `city_id`, send a `sector_id` that belongs to that city, otherwise validation fails.',
        'orders_delete_scope' => 'The order must belong to your account and to the store you are targeting. Deleting an order already in the delivery flow is not blocked by the API — do it with care.',
        'orders_create_amount' => 'With `payment_method: CASH`, `order_amount` is the sum the driver collects and is mandatory. With `CARD_PAYMENT` the customer has already paid, so `order_amount` is forced to null and you may send `order_value` for insurance purposes.',
        'orders_create_sector' => '`sector_id` must belong to `city_id` and both must be active. Fetch them from the reference endpoints below.',
        'orders_list_partner' => 'Orders ingested from a partner marketplace are not part of this list.',
        'pickups_create_address' => '`pickup_address` must match one of the two pickup addresses saved on your profile. Set them up in the dashboard first.',
        'pdf_accept' => 'Do not send `Accept: application/json` on this call — you would get a JSON error instead of the file.',
    ],

    'fields' => [
        'customer_first_name' => 'Recipient first name.',
        'customer_last_name' => 'Recipient last name.',
        'customer_phone' => 'Recipient phone number, as the driver will dial it.',
        'customer_address' => 'Full street address, including any delivery instructions.',
        'city_id' => 'Destination city. Must be an active city.',
        'sector_id' => 'Destination sector inside the city. Drives the delivery price.',
        'payment_method' => '`CASH` when the driver collects on delivery, `CARD_PAYMENT` when the customer paid you already.',
        'order_amount' => 'Amount the driver collects from the customer. Required for `CASH`, ignored for `CARD_PAYMENT`.',
        'order_value' => 'Declared value of the goods. Only meaningful for `CARD_PAYMENT`.',
        'delivery_price' => 'Delivery fee. Leave it out to use the sector price.',
        'notes' => 'Free-text instructions for the driver.',
        'is_fragile' => 'Flags the parcel as fragile on the label.',
        'can_be_opened' => 'Lets the customer open the parcel before paying.',
        'option_exchange' => 'The driver picks up an item in exchange at delivery.',
        'order_ids' => 'Orders to collect. Each one must be yours and still in `CREATED`.',
        'pickup_address' => 'One of the pickup addresses configured on your profile.',
        'pickup_notes' => 'Instructions for the collecting driver.',
        'to_status' => 'Target status.',
        'comment' => 'Free-text note stored on the status change.',
    ],

    'filters' => [
        'page' => 'Page number.',
        'per_page' => 'Results per page. Capped at 100.',
        'tracking_number' => 'Partial match on the tracking number. `order_number` is accepted as an alias.',
        'customer_name' => 'Partial match on the recipient first name, last name or full name.',
        'customer_phone' => 'Partial match on the recipient phone number.',
        'status' => 'One status, or several to widen the search.',
        'status_group' => 'A named bucket of statuses: `pickup`, `delivery`, `delivered` or `failed`. Ignored when `status` is set.',
        'payment_method' => 'Filter on `CASH` or `CARD_PAYMENT`.',
        'city_id' => 'Restrict to a destination city.',
        'sector_id' => 'Restrict to a destination sector.',
        'created_from' => 'Orders created on or after this date, inclusive.',
        'created_to' => 'Orders created on or before this date, inclusive.',
        'delivery_from' => 'Orders delivered on or after this date, inclusive.',
        'delivery_to' => 'Orders delivered on or before this date, inclusive.',
        'is_fragile' => 'Keep only fragile parcels, or only the others.',
        'can_be_opened' => 'Keep only parcels that may be opened before payment.',
        'sort' => 'Sort column: `created_at`, `tracking_number`, `order_amount`, `order_value`, `delivery_price` or `status`.',
        'direction' => 'Sort direction, `asc` or `desc`.',
        'pickup_status' => 'Filter on the pickup request status.',
        'city_search' => 'Partial match on the city name.',
        'order_id' => 'Numeric identifier of the order.',
        'tracking_path' => 'Tracking number of the order, for example `SPD-2026-583920`.',
        'pickup_id' => 'Numeric identifier of the pickup request.',
        'city_path' => 'Numeric identifier of the city.',
    ],

    'headers' => [
        'authorization' => 'Your personal token, prefixed with `Bearer`.',
        'accept' => 'Must be `application/json`.',
        'content_type' => 'Must be `application/json` on requests with a body.',
        'store' => 'Identifier of the store to act on. Optional on single-store accounts.',
    ],
];
