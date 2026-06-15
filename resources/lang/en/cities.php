<?php

return [
    'title' => 'Cities',
    'list_title' => 'City List',
    'new_city' => 'New City',
    'create_title' => 'Create City',
    'edit_title' => 'Edit :name',
    'create_button' => 'Create City',
    'filters' => [
        'search_placeholder' => 'Name, code or region…',
    ],
    'table' => [
        'name' => 'Name',
        'code' => 'Code',
        'region' => 'Region',
        'sectors' => 'Sectors',
    ],
    'show' => [
        'info' => 'City Information',
        'total_sectors' => 'Total Sectors',
        'active_sectors' => ':count active',
        'created' => 'Created',
        'updated' => 'Last Updated',
        'sectors_in' => 'Sectors in :name',
        'add_sector' => 'Add Sector',
        'no_sectors' => 'No sectors yet for this city.',
        'add_first_sector' => 'Add the first sector',
        'sectors_count' => ':count sector|:count sectors',
    ],
    'form' => [
        'info' => 'City Information',
        'name_placeholder' => 'e.g. Casablanca',
        'code_placeholder' => 'e.g. CASA',
        'region_placeholder' => 'e.g. Casablanca-Settat',
    ],
    'delete_confirm_title' => 'Delete this city?',
    'delete_confirm_text' => ':name will be removed. Cities with active sectors cannot be deleted.',
    'sector_delete_confirm_title' => 'Remove this sector?',
    'sector_delete_confirm_text' => ':name will be removed from :city.',
    'empty' => 'No cities found.',
];
