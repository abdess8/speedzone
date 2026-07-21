<?php

return [
    'register' => [
        'title' => 'Seller registration',
        'subtitle' => 'Join Speed Zone as a delivery seller',
        'heading' => 'Create your seller account',
        'description' => 'Register to start shipping with Speed Zone Express.',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email',
        'phone' => 'Phone',
        'city' => 'City',
        'city_placeholder' => 'Search and select your city',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'submit' => 'Create account',
        'already_have_account' => 'Already have an account?',
        'sign_in' => 'Sign in',
    ],

    'registered' => 'Registration successful. Please verify your email address to continue.',

    'login' => [
        'unverified' => 'Please verify your email address before accessing your account.',
        'rejected' => 'Your registration request has been rejected.',
    ],

    'pending' => [
        'title' => 'Account pending approval',
        'heading' => 'Registration under review',
        'message' => 'Your registration is under review. The Speed Zone team is validating your account.',
        'review_note' => 'You will receive an email once your account has been approved.',
        'contact_support' => 'Contact support',
        'sign_out' => 'Sign out',
    ],

    'admin' => [
        'page_title' => 'Pending seller registrations',
        'details_title' => 'Review seller registration',
        'search_placeholder' => 'Search by name, email or phone…',
        'all_statuses' => 'All statuses',
        'empty' => 'No pending seller registrations found.',
        'view_details' => 'View details',
        'personal_info' => 'Personal information',
        'approval_section' => 'Approval & permissions',
        'permissions_help' => 'Select the permissions this seller should have once approved.',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'rejection_reason' => 'Rejection reason',
        'approved_success' => 'Seller account approved successfully.',
        'rejected_success' => 'Seller registration rejected.',
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'city' => 'City',
            'registered_at' => 'Registration date',
            'status' => 'Status',
            'actions' => 'Actions',
        ],
    ],

    'emails' => [
        'verification_subject' => 'Verify your Speed Zone account',
        'approved_subject' => 'Your Speed Zone account is approved',
        'approved_heading' => 'Your account is approved',
        'approved_body' => 'Hello :name, your Speed Zone seller account has been approved. You can now access the platform.',
        'approved_button' => 'Sign in to Speed Zone',
        'approved_footer' => 'If you did not request this account, please contact support.',
        'rejected_subject' => 'Your Speed Zone registration was rejected',
        'rejected_heading' => 'Registration not approved',
        'rejected_body' => 'Hello :name, your registration request has been rejected.',
        'rejected_reason_label' => 'Reason',
        'rejected_footer' => 'If you believe this is an error, please contact our support team.',
    ],
];
