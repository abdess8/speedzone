<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inbound shipment references
    |--------------------------------------------------------------------------
    |
    | Shape of the reference printed on a stock reception slip, following the
    | same convention as orders (SPD-), transfers (TRF-) and returns (RTN-).
    |
    */

    'reference_prefix' => env('STOCK_REFERENCE_PREFIX', 'RCP'),

    'reference_random_digits' => (int) env('STOCK_REFERENCE_RANDOM_DIGITS', 6),

    /*
    |--------------------------------------------------------------------------
    | Catalog import
    |--------------------------------------------------------------------------
    |
    | Rows accepted in a single bulk import. Mirrored client side by
    | MAX_IMPORT_ROWS in resources/js/composables/useProductImport.js — a batch
    | has to fit in one request, and the review table has to stay usable.
    |
    */

    'import_max_rows' => (int) env('STOCK_IMPORT_MAX_ROWS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Low stock threshold
    |--------------------------------------------------------------------------
    |
    | At or below this quantity (and above zero) a product is flagged as running
    | low in the catalog and on the inventory screen.
    |
    */

    'low_stock_threshold' => (int) env('STOCK_LOW_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Pick-list size
    |--------------------------------------------------------------------------
    |
    | References shipped to the order form so the pick-list can filter locally
    | and answer a keystroke without a round trip. A catalog larger than this
    | needs a server-side search endpoint instead of a bigger page payload.
    |
    */

    'picklist_limit' => (int) env('STOCK_PICKLIST_LIMIT', 2000),

];
