<?php

return [
    'CREATED' => 'Created',
    'RECEIVED_AT_HUB' => 'Received at Hub',
    'IN_TRANSIT_TO_DEPOT' => 'In Transit to Hub',
    'ARRIVED_VENDOR_HUB' => 'Arrived at Vendor Hub',
    'IN_DELIVERY_TO_VENDOR' => 'Out for Return to Vendor',
    'DELIVERED_TO_VENDOR' => 'Returned to Vendor',
    'CANCELLED' => 'Cancelled',

    'descriptions' => [
        'CREATED' => 'The return has just been opened, after a delivery failed for good or at the seller\'s request. The parcel is still with the driver.',
        'RECEIVED_AT_HUB' => 'The driver dropped the undelivered parcel at the hub of the delivery city. It is waiting to be added to a transfer manifest.',
        'IN_TRANSIT_TO_DEPOT' => 'The parcel is on its way to the hub of the seller\'s city, inside an inter-city transfer manifest.',
        'ARRIVED_VENDOR_HUB' => 'The transfer was received and scanned at the hub of the seller\'s home city. The parcel is waiting for a driver.',
        'IN_DELIVERY_TO_VENDOR' => 'A driver took the parcel to hand it back to the seller in person.',
        'DELIVERED_TO_VENDOR' => 'The parcel is back with the seller. The return is closed and the order counts as returned.',
        'CANCELLED' => 'The return was abandoned: the order reverts to the status it held before the return was opened.',
    ],

    'actors' => [
        'CREATED' => 'Driver, Seller or system (failed delivery)',
        'RECEIVED_AT_HUB' => 'Destination hub manager',
        'IN_TRANSIT_TO_DEPOT' => 'Destination hub manager (on transfer dispatch)',
        'ARRIVED_VENDOR_HUB' => 'Vendor hub manager (on transfer receipt)',
        'IN_DELIVERY_TO_VENDOR' => 'Driver assigned to the hand-back',
        'DELIVERED_TO_VENDOR' => 'Driver assigned to the hand-back',
        'CANCELLED' => 'Back office (returns management)',
    ],
];
