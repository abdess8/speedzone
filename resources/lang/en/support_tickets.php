<?php

return [
    'title' => 'Support Center',
    'page_title' => 'Support',
    'list_title' => 'Support Tickets',
    'create' => 'Create Support Ticket',
    'empty' => 'No support tickets found.',

    'filters' => [
        'reference' => 'Ticket #',
        'subject' => 'Subject',
        'seller' => 'Seller',
        'seller_placeholder' => 'Name or email',
        'assigned_to' => 'Assigned to',
        'unassigned' => 'Unassigned',
        'status' => 'Status',
        'all_statuses' => 'All statuses',
        'category' => 'Category',
        'all_categories' => 'All categories',
        'created_from' => 'Created from',
        'created_to' => 'Created to',
    ],

    'table' => [
        'reference' => 'Reference',
        'seller' => 'Seller',
        'object' => 'Related Object',
        'category' => 'Category',
        'status' => 'Status',
        'assigned' => 'Assigned To',
        'subject' => 'Subject',
        'messages' => 'Messages',
        'created_at' => 'Created',
        'last_reply' => 'Last Reply',
    ],

    'detail' => [
        'title' => 'Ticket :reference',
        'page_title' => 'Support Ticket',
        'info' => 'Ticket Information',
        'conversation' => 'Conversation',
        'no_messages' => 'No messages yet. Start the conversation below.',
        'read_only' => 'This ticket is closed. The conversation is read-only.',
        'initial_message' => 'Initial Request',
        'attachments' => 'Attachments',
        'related_object' => 'Related Object',
        'no_object' => 'No related object',
    ],

    'create_form' => [
        'title' => 'Create Support Ticket',
        'page_title' => 'New Ticket',
        'step_category' => 'Support Type',
        'step_object' => 'Related Object',
        'step_message' => 'Describe Your Request',
        'step_attachment' => 'Attachment (optional)',
        'category' => 'Category',
        'category_placeholder' => 'Select a category',
        'object_type' => 'Object Type',
        'object_type_placeholder' => 'Select object type',
        'object_id' => 'Select Record',
        'object_id_placeholder' => 'Choose a record',
        'object_hint' => 'Only your own records are shown.',
        'subject' => 'Subject',
        'subject_placeholder' => 'Short title for your request',
        'message' => 'Message',
        'message_placeholder' => 'Describe your request in detail…',
        'attachment' => 'Attachment',
        'attachment_hint' => 'Images, PDF or documents up to 10 MB.',
        'submit' => 'Submit Ticket',
        'loading_objects' => 'Loading records…',
        'no_objects' => 'No records found for this type.',
    ],

    'chat' => [
        'placeholder' => 'Type your message…',
        'send' => 'Send',
        'attach' => 'Attach file',
        'you' => 'You',
        'support' => 'Support',
        'seller' => 'Seller',
    ],

    'actions' => [
        'view' => 'View',
        'assign' => 'Assign',
        'change_status' => 'Change Status',
        'close' => 'Close Ticket',
        'back_to_list' => 'Back to tickets',
        'create_for_object' => 'Create Ticket',
    ],

    'assign' => [
        'title' => 'Assign Ticket',
        'select_agent' => 'Select support agent',
        'unassign' => 'Unassign',
        'submit' => 'Assign',
    ],

    'status' => [
        'title' => 'Update Status',
        'select' => 'Select new status',
        'submit' => 'Update',
    ],

    'panel' => [
        'title' => 'Support Tickets',
        'empty' => 'No support tickets for this record.',
        'create' => 'Create Ticket',
    ],

    'confirms' => [
        'close_title' => 'Close this ticket?',
        'close_text' => 'The conversation will become read-only.',
        'confirm' => 'Yes, proceed',
    ],

    'created' => 'Ticket :reference created successfully.',
    'message_sent' => 'Message sent.',
    'assigned' => 'Ticket assigned successfully.',
    'status_updated' => 'Ticket status updated.',
    'closed' => 'Ticket closed.',

    'errors' => [
        'object_not_found' => 'The selected record does not exist.',
        'object_forbidden' => 'You do not have access to this record.',
        'empty_message' => 'Please enter a message or attach a file.',
        'action_not_allowed' => 'This action is not allowed for this ticket.',
        'ticket_closed' => 'This ticket is closed.',
    ],

    'notifications' => [
        'new_ticket' => 'New support ticket :reference',
        'new_reply' => 'New reply on ticket :reference',
        'assigned' => 'Ticket :reference assigned to you',
    ],
];
