<?php

return [
    'title' => 'Announcements',
    'subtitle' => 'Push a message to your users as a banner or a sign-in modal.',

    'create_title' => 'New announcement',
    'edit_title' => 'Edit announcement',
    'create_button' => 'Publish',
    'update_button' => 'Save changes',

    'types' => [
        'info' => 'Information',
        'warning' => 'Warning',
        'danger' => 'Critical',
        'success' => 'Good news',
    ],

    'formats' => [
        'modal' => 'Modal',
        'banner' => 'Banner',
        'modal_hint' => 'Opens over the interface on the first page of a session. The reader has to close it to carry on.',
        'banner_hint' => 'Sits at the top of the content area, on every page.',
    ],

    'statuses' => [
        'active' => 'Live',
        'expired' => 'Expired',
        'disabled' => 'Switched off',
    ],

    'table' => [
        'announcement' => 'Announcement',
        'type' => 'Type',
        'format' => 'Format',
        'audience' => 'Audience',
        'status' => 'Status',
        'end_date' => 'Ends',
        'author' => 'Created by',
        'actions' => 'Actions',
        'empty' => 'No announcement yet.',
        'empty_hint' => 'Publish one to reach your sellers, drivers or dispatchers.',
    ],

    'filters' => [
        'title' => 'Filters',
        'search' => 'Search a title',
        'type' => 'Type',
        'format' => 'Format',
        'status' => 'Status',
        'all' => 'All',
    ],

    'form' => [
        'appearance' => 'Type and format',
        'appearance_hint' => 'How the announcement looks, and where it shows up.',
        'audience' => 'Recipients',
        'audience_hint' => 'Roles and cities narrow each other. Named people are added on top.',
        'content' => 'Content',
        'schedule' => 'Scheduling',
        'schedule_hint' => 'The announcement disappears on its own once this moment has passed.',

        'title_field' => 'Title',
        'message' => 'Message',
        'message_hint' => 'Bold, italic, colour, size, lists and links are kept. Anything else is stripped for safety.',
        'end_date' => 'Hide from',
        'dismissible' => 'The reader can close it',
        'dismissible_hint' => 'Switch off to pin the banner to every page with no close button.',
        'dismissible_modal_note' => 'A modal is always closable, otherwise the reader could not carry on.',
        'active' => 'Publish straight away',

        'roles' => 'By role',
        'all_roles' => 'All roles',
        'cities' => 'By city',
        'all_cities' => 'All cities',
        'cities_hint' => 'A driver is matched on the cities of the sectors assigned to them, a seller on the cities of their shops.',
        'users' => 'Named people',
        'users_placeholder' => 'Search a name or an email address',
        'users_hint' => 'These people receive the announcement whatever the role and city selection says.',
    ],

    'audience' => [
        'everyone' => 'Everyone',
        'nobody' => 'Nobody yet',
        'all_roles' => 'all roles',
        'all_cities' => 'everywhere',
        'roles_in_cities' => ':roles, :cities',
        'plus_users' => '+ :count named',
        'only_users' => ':count named recipients',
        'summary_label' => 'This announcement will reach',
    ],

    'flash' => [
        'created' => 'Announcement ":title" published.',
        'updated' => 'Announcement ":title" updated.',
        'deleted' => 'Announcement ":title" deleted.',
        'enabled' => 'Announcement ":title" is live.',
        'disabled' => 'Announcement ":title" is switched off.',
        'expired_cannot_enable' => 'This announcement has expired. Push its end date back before switching it on again.',
    ],

    'validation' => [
        'end_date_future' => 'Pick a moment in the future, otherwise the announcement is expired the second it is saved.',
        'no_audience' => 'This announcement would reach nobody. Pick at least one role and one city, or name the people it is for.',
        'unknown_role' => 'One of the selected roles no longer exists. Reload the page and pick the roles again.',
        'unknown_city' => 'One of the selected cities is unknown or no longer active. Reload the page and pick the cities again.',
    ],

    'actions' => [
        'enable' => 'Switch on',
        'disable' => 'Switch off',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'dismiss' => 'Dismiss',
        'understood' => 'Got it',
    ],

    'delete_confirm_title' => 'Delete this announcement?',
    'delete_confirm_text' => '":title" will be removed for good.',
];
