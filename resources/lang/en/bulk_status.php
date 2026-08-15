<?php

return [
    'title' => 'Bulk status update',
    'page_title' => 'Statuses',
    'subtitle' => 'Change the status of several orders or returns in a single operation.',
    'menu' => 'Bulk status update',
    'quick_action' => 'Quick actions',

    'entities' => [
        'ORDER' => 'Orders',
        'RETURN' => 'Returns',
    ],

    'steps' => [
        'entity' => 'Type',
        'target' => 'Target status',
        'selection' => 'Selection',
        'confirmation' => 'Confirmation',
        'result' => 'Result',
    ],

    'entity_step' => [
        'title' => 'What do you want to update?',
        'help' => 'Only the types you hold at least one allowed transition for are offered.',
    ],

    'target_step' => [
        'title' => 'Change the status to',
        'help' => 'You only see the statuses your permissions let you move items into.',
        'sources' => 'From: :statuses',
        'empty' => 'No target status is available to you for this type.',
    ],

    'selection' => [
        'title' => 'Eligible items',
        'eligible' => '{0} No eligible item|{1} :count eligible item|[2,*] :count eligible items',
        'selected' => '{0} No item selected|{1} :count item selected|[2,*] :count items selected',
        'select_all' => 'Select all',
        'select_page' => 'Select page',
        'clear' => 'Clear selection',
        'empty' => 'No item can move to this status with your current access.',
        'search' => 'Order number, reference, recipient, phone…',
        'source_filter' => 'Current status',
        'all_sources' => 'All source statuses',
        'loading' => 'Loading items…',
    ],

    'columns' => [
        'reference' => 'Reference',
        'current_status' => 'Current status',
        'new_status' => 'New status',
        'customer' => 'Recipient',
        'details' => 'Details',
        'created' => 'Created',
    ],

    'details' => [
        'city' => 'City',
        'sector' => 'Sector',
        'seller' => 'Seller',
        'to_collect' => 'To collect',
        'order' => 'Order',
        'current_city' => 'Current city',
        'reason' => 'Reason',
        'driver' => 'Driver',
    ],

    'scan' => [
        'button' => 'Scan a QR code',
        'title' => 'Scan QR codes',
        'help' => 'Scan codes one after another: every valid item is added to your selection.',
        'manual' => 'Or type the reference',
        'add' => 'Add',
        'start' => 'Start camera',
        'stop' => 'Stop camera',
        'aim' => 'Hold the QR code inside the frame',
        'added' => ':reference added to the selection.',
        'already' => ':reference is already selected.',
        'unreadable' => 'This QR code does not match any known reference.',
        'not_found' => 'No item found for reference :reference.',
        'inaccessible' => 'Item :reference is not accessible with your account.',
        'transition_forbidden' => ':reference cannot move from ":from" to ":to".',
        'camera_unsupported' => 'This browser cannot access the camera. Type the reference instead.',
        'camera_error' => 'Camera unavailable. Check the permission in your browser.',
        'unreachable' => 'Could not verify. Please try again.',
    ],

    'confirm' => [
        'title' => 'Confirm the update',
        'intro' => 'You are about to update:',
        'items' => '{1} :count item|[2,*] :count items',
        'breakdown' => ':count × :from → :to',
        'comment' => 'Comment (optional)',
        'comment_help' => 'Added to the history of every updated item.',
        'submit' => 'Confirm the update',
        'irreversible' => 'This action is written to each item\'s history and cannot be undone in bulk.',
        'consequences' => 'Some transitions trigger business actions (driver payout, partner sync, closing the return).',
    ],

    'result' => [
        'title' => 'Update result',
        'succeeded' => '{0} No item processed|{1} :count item processed successfully|[2,*] :count items processed successfully',
        'failed' => '{1} :count item could not be updated|[2,*] :count items could not be updated',
        'reason' => 'Reason',
        'restart' => 'New update',
        'back_to_list' => 'Back to the list',
    ],

    'failures' => [
        'NOT_FOUND' => 'Item not found.',
        'INACCESSIBLE' => 'Item not accessible with your account.',
        'PERMISSION_DENIED' => 'Insufficient permission.',
        'TRANSITION_NOT_ALLOWED' => 'Transition not allowed.',
        'STATUS_CHANGED' => 'The status has already changed.',
        'BUSINESS_RULE' => 'Business rule error.',
        'status_changed_detail' => 'Selected as ":expected", it is now ":actual".',
        'unexpected' => 'An unexpected error occurred.',
    ],

    'flash' => [
        'success' => '{1} :succeeded item updated successfully.|[2,*] :succeeded items updated successfully.',
        'warning' => ':succeeded item(s) updated, :failed failed.',
        'error' => 'No item could be updated (:failed failed).',
    ],

    'errors' => [
        'no_transition' => 'No status transition is available to you.',
        'unknown_entity' => 'Unknown item type.',
        'target_forbidden' => 'This target status is not available to you.',
    ],

    'audit' => [
        'comment' => 'Bulk status update',
    ],

    'permissions' => [
        'title' => 'Status update permissions',
        'page_title' => 'Administration',
        'subtitle' => 'Define, per role, the "current status → new status" transitions usable in a bulk update.',
        'menu' => 'Status permissions',
        'admin_only' => 'Only an administrator can manage these permissions.',
        'saved' => 'Permission updated.',
        'transition' => 'Transition',
        'roles' => 'Roles',
        'search' => 'Filter transitions…',
        'empty' => 'No transition matches your search.',
        'granted_count' => '{0} No role|{1} 1 role|[2,*] :count roles',
        'help' => 'The listed transitions are the ones the workflow actually allows: an impossible transition cannot be granted.',
        'note' => 'Users inherit their role\'s permissions. An administrator holds every transition.',
    ],
];
