<?php

return [
    'title' => 'My stores',
    'list_title' => 'Stores',
    'list_hint' => 'Each store runs independently: orders, pickups, returns and invoices are never shared between two stores.',
    'create_title' => 'New store',
    'create_button' => 'Create store',
    'empty' => 'No store yet.',
    'no_category' => 'No category',
    'switch_to' => 'Switch',
    'orders_count' => ':count order(s)',

    'badges' => [
        'default' => 'Default',
        'active_session' => 'Active store',
    ],

    'switcher' => [
        'label' => 'Active store',
        'heading' => 'Switch store',
        'manage' => 'Manage my stores',
    ],

    'picker' => [
        'title' => 'Choose a store',
        'subtitle' => 'You have access to several stores. Pick the one you want to work in.',
    ],

    'fields' => [
        'name' => 'Store name',
        'category' => 'Category',
        'website' => 'Website',
        'logo' => 'Logo',
        'contact_name' => 'Contact name',
        'contact_phone' => 'Phone',
        'contact_email' => 'Email',
        'city' => 'City',
        'stock_hub_city' => 'Stock depot city',
        'address' => 'Address',
        'pickup_address_1' => 'Pickup address 1',
        'pickup_address_2' => 'Pickup address 2',
        'is_default' => 'Set as default store',
    ],

    'form' => [
        'identity' => 'Store identity',
        'branding' => 'Logo and printing',
        'branding_hint' => 'This logo and name are printed on the shipping labels of this store\'s packages.',
        'contact' => 'Contact and addresses',
        'name_placeholder' => 'e.g. Nova Cosmetics',
        'category_placeholder' => 'e.g. Cosmetics',
        'logo_hint' => 'PNG, JPG or WEBP — 2 MB max. A square format prints best on thermal labels.',
        'default_hint' => 'The default store is the one offered first at login.',
        'fulfilment' => 'Stock and preparation',
        'fulfilment_hint' => 'The depot holding this store\'s stock. It is also the city orders picked from your catalog ship out of.',
        'no_stock_hub' => 'No depot — I do not warehouse with you',
    ],

    'delete_confirm_title' => 'Delete this store?',
    'delete_confirm_text' => 'Store ":name" will be archived. This action cannot be undone.',

    'flash' => [
        'created' => 'Store ":name" created.',
        'updated' => 'Store ":name" updated.',
        'deleted' => 'Store ":name" deleted.',
        'cannot_delete' => 'This store cannot be deleted: it is the default store or it already holds orders.',
    ],

    'errors' => [
        'not_accessible' => 'You do not have access to this store.',
        'depot_not_empty' => 'The depot cannot be changed: :units unit(s) are still held in the current one. Sell out or count down that stock before moving.',
    ],
];
