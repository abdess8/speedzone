<?php

return [
    'title' => 'Driver Assignment',
    'list_title' => 'Driver Zone Management',
    'filters' => [
        'all_cities' => 'All cities',
        'all_sectors' => 'All sectors',
        'search_driver' => 'Search Driver',
        'search_placeholder' => 'Name, email or phone…',
        'filter_by_city' => 'Filter by City',
        'city_placeholder' => 'City…',
        'filter_by_sector' => 'Filter by Sector',
        'sector_placeholder' => 'Sector…',
    ],
    'table' => [
        'driver' => 'Driver',
        'zones' => 'Zones',
        'assigned_sectors' => 'Assigned Sectors',
    ],
    'no_sectors_assigned' => 'No sectors assigned.',
    'actions' => [
        'manage' => 'Manage',
        'remove' => 'Remove',
    ],
    'remove_confirm_title' => 'Remove sector?',
    'remove_confirm_text' => ':sector will be unassigned from :driver.',
    'pagination_range' => ':from–:to of :total drivers',
    'modal' => [
        'title' => 'Assign Sectors — :name',
        'description' => 'Select the delivery sectors this driver should serve. The current selection replaces the existing assignment.',
        'sectors_placeholder' => 'Search and select sectors…',
        'save' => 'Save Assignment',
    ],
    'sector_option_label' => ':name (:price MAD)',
    'empty' => 'No drivers found.',
];
