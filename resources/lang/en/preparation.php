<?php

return [
    'title' => 'Order preparation',
    'queue_title' => 'To prepare',
    'empty' => 'Nothing to prepare',
    'empty_hint' => 'Orders placed with warehoused products land here the moment they are created.',

    'columns' => [
        'tracking' => 'Tracking number',
        'created' => 'Ordered on',
        'store' => 'Shop',
        'customer' => 'Customer',
        'city' => 'City',
        'lines' => 'To pick',
        'routing' => 'After packing',
        'hub' => 'Depot',
        'units' => 'Units',
        'check' => 'Check',
    ],

    'filters' => [
        'search' => 'Tracking number…',
        'all_hubs' => 'All depots',
    ],

    'routing' => [
        'local' => 'Delivers locally',
        'transfer' => 'Needs a transfer',
    ],

    'actions' => [
        'scan' => 'Scan parcels',
        'mark_prepared' => 'Mark prepared',
        'prepare_short' => 'Prepared',
    ],

    'selection' => [
        'count' => '{count} order(s) selected',
        'units' => '{count} unit(s) to pick',
    ],

    'confirm' => [
        'title' => 'Mark {count} order(s) as prepared?',
        'text' => 'Parcels whose depot sits in the customer\'s city leave for delivery straight away. The rest wait for a transfer.',
    ],

    'scanner' => [
        'title' => 'Scan packed parcels',
        'camera_preview' => 'Hold the slip\'s QR code in front of the camera.',
        'camera_error' => 'The camera could not be opened. Type the numbers instead.',
        'camera_unsupported' => 'This browser gives no access to the camera. Type the numbers instead.',
        'start_camera' => 'Turn the camera on',
        'manual_label' => 'Tracking number',
        'manual_placeholder' => 'Scan or type a number, then press Enter',
        'invalid_tracking' => 'This tracking number is not recognised.',
        'add' => 'Add',
        'scanned' => '{count} parcel(s) scanned',
        'valid_count' => '{count} accepted',
        'clear_all' => 'Clear all',
        'nothing_scanned' => 'No parcel scanned yet.',
        'valid' => 'Accepted',
        'rejected' => 'Rejected',
        'unable_validate' => 'The check could not be run. Try again.',
        'mark_prepared' => 'Mark prepared',
        'mark_prepared_count' => 'Mark {count} parcel(s) prepared',
        'confirm' => 'Mark {count} parcel(s) as prepared?',
        'confirm_hint' => 'Parcels whose depot sits in the customer\'s city leave for delivery straight away. The rest wait for a transfer.',
        'done' => '{prepared} parcel(s) prepared, {skipped} skipped.',
        'bulk_failed' => 'The update failed',
    ],

    // Read by Laravel, hence the `:placeholder` syntax. The scanner's own
    // counterpart lives under `scanner.done` because vue-i18n cannot read these.
    'flash' => [
        'prepared' => ':prepared order(s) prepared, :skipped skipped.',
        'none_prepared' => 'No order was prepared: they were already handled, or they are not yours to touch.',
    ],

    'errors' => [
        'unknown_order' => 'Unknown tracking number.',
        'not_yours' => 'This order is not yours to touch.',
        'wrong_status' => 'This order is already ":status".',
        'unknown_in_batch' => 'Unknown tracking numbers: :codes',
    ],
];
