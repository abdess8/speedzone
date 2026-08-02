<?php

return [
    'title' => 'My team',
    'subtitle' => 'Manage the people who can access your stores.',
    'create_title' => 'New team member',
    'edit_title' => 'Edit team member',
    'empty' => 'No team member yet.',
    'empty_hint' => 'Create a role, then invite your first team member.',
    'add' => 'Add a team member',
    'manage_roles' => 'Manage roles',

    'fields' => [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email',
        'phone_number' => 'Phone',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'locale' => 'Language',
        'stores' => 'Accessible stores',
        'roles' => 'Roles',
        'status' => 'Status',
        'sessions' => 'Active sessions',
        'last_activity' => 'Last activity',
    ],

    'hints' => [
        'password' => 'Share this password with the member; they can change it from their profile.',
        'password_edit' => 'Leave empty to keep the current password. Changing it signs the member out immediately.',
        'stores' => 'The member will only see orders, invoices and pickups of the selected stores.',
        'roles' => 'Roles decide what the member can do. They can never exceed your own rights.',
    ],

    'sections' => [
        'identity' => 'Identity',
        'access' => 'Access',
        'security' => 'Sign in',
    ],

    'sessions' => [
        'none' => 'No open session',
        'count' => ':count open session|:count open sessions',
        'never' => 'Never signed in',
    ],

    'actions' => [
        'suspend' => 'Suspend',
        'reactivate' => 'Reactivate',
        'edit' => 'Edit',
    ],

    'suspend_confirm_title' => 'Suspend :name?',
    'suspend_confirm_text' => 'Their open sessions will be closed immediately and they will no longer be able to sign in.',

    'flash' => [
        'created' => 'Team member :name created.',
        'updated' => 'Team member :name updated.',
        'suspended' => 'Access for :name suspended and sessions closed.',
        'reactivated' => 'Access for :name restored.',
    ],

    'errors' => [
        'not_a_member' => 'This account is not part of your team.',
        'store_required' => 'Select at least one store.',
        'role_required' => 'Select at least one of your team roles.',
        'no_store' => 'Create a store before adding a team member.',
        'no_role' => 'Create a role before adding a team member.',
    ],

    'login' => [
        'suspended' => 'Your access has been suspended by your account administrator.',
    ],

    'roles' => [
        'title' => 'Team roles',
        'subtitle' => 'Define what each kind of team member can do.',
        'create_title' => 'New role',
        'edit_title' => 'Edit role',
        'add' => 'Create a role',
        'empty' => 'No custom role yet.',
        'empty_hint' => 'For example: Stock manager, Order picker.',
        'back' => 'Back to team',

        'fields' => [
            'label' => 'Role name',
            'permissions' => 'Permissions',
        ],

        'hints' => [
            'label' => 'For example: Order picker.',
            'permissions' => 'Only permissions you hold yourself are offered. Store and team administration stays with the account administrator.',
        ],

        'members_count' => ':count member|:count members',
        'permissions_count' => ':count permission|:count permissions',
        'select_all' => 'Select all',
        'clear_all' => 'Clear all',

        'delete_confirm_title' => 'Delete role :name?',
        'delete_confirm_text' => 'This action is permanent.',

        'flash' => [
            'created' => 'Role :name created.',
            'updated' => 'Role :name updated.',
            'deleted' => 'Role :name deleted.',
        ],

        'errors' => [
            'in_use' => 'This role is still assigned to team members.',
            'system_role' => 'This role belongs to the platform and cannot be edited.',
            'permission_required' => 'Select at least one permission.',
        ],
    ],

    'resources' => [
        'orders' => 'Orders',
        'pickup_requests' => 'Pickups',
        'returns' => 'Returns',
        'invoices' => 'Invoices',
        'stores' => 'Stores',
        'support' => 'Support',
        'cities' => 'Cities',
        'sectors' => 'Sectors',
    ],

    'actions_labels' => [
        'create' => 'Create',
        'read' => 'View',
        'update' => 'Edit',
        'delete' => 'Delete',
        'export' => 'Export',
        'print' => 'Print',
        'create_request' => 'Request a return',
        'reply' => 'Reply',
        'close' => 'Close',
        'update_status' => 'Change status',
    ],

    'scopes' => [
        'own' => 'their own data',
        'all' => 'all data',
        'assigned' => 'assigned data',
    ],
];
