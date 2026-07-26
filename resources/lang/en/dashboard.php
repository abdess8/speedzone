<?php

return [
    'greeting' => 'Good morning, :name!',
    'subtitle' => 'SpeedZone Express logistics overview — live data from your operations.',
    'default_team' => 'Operations Team',

    'periods' => [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_7_days' => 'Last 7 Days',
        'last_30_days' => 'Last 30 Days',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'custom' => 'Custom Range',
    ],

    'select_date_range' => 'Select date range',
    'create_shipment' => 'Create Shipment',
    'retry' => 'Retry',
    'view_all' => 'View all',
    'view' => 'View',
    'view_orders' => 'View orders',
    'view_transfers' => 'View transfers',

    'errors' => [
        'load_failed' => 'Failed to load dashboard.',
    ],

    'empty' => [
        'chart' => 'No data for this period.',
        'orders' => 'No orders yet.',
        'activity' => 'No activity recorded yet.',
        'customers' => 'No customer data for this period.',
    ],

    'kpis' => [
        'orders_today' => 'Today\'s Orders',
        'orders_this_month' => 'Orders This Month',
        'delivered_orders' => 'Delivered Orders',
        'pending_pickup' => 'Pending Pickup',
        'in_transit' => 'In Transit',
        'out_for_delivery' => 'Out For Delivery',
        'returned_orders' => 'Returned Orders',
        'cancelled_orders' => 'Cancelled Orders',
        'cash_to_collect' => 'Cash To Collect',
        'cod_collected' => 'COD Collected',
        'delivery_success_rate' => 'Delivery Success Rate',
        'average_delivery_time' => 'Avg Delivery Time',
        'active_sellers' => 'Active Sellers',
        'active_delivery_agents' => 'Active Delivery Agents',
        'new_customers' => 'New Customers',
        'revenue_in_period' => 'Revenue (Period)',
        'revenue_today' => 'Revenue Today',
        'revenue_this_month' => 'Revenue This Month',
        'average_order_value' => 'Average Order Value',
        'pending_transfers' => 'Pending Transfers',
        'orders_at_agency' => 'Orders At Agency',
        'failed_deliveries' => 'Failed Deliveries',
        'orders_in_period' => 'Orders In Period',
    ],

    'charts' => [
        'orders_by_day' => 'Orders by Day (Last 30 Days)',
        'orders_by_status' => 'Orders by Status',
        'orders_by_status_summary' => 'Orders by Status (Summary)',
        'orders_by_city' => 'Orders by City',
        'payment_methods' => 'Payment Methods',
        'monthly_revenue' => 'Monthly Revenue (Last 12 Months)',
        'delivery_success_rate' => 'Delivery Success Rate',
        'orders_per_seller' => 'Orders Per Seller (Top 10)',
        'delivery_agents_performance' => 'Delivery Agents Performance',
        'delivered_failed' => ':delivered delivered · :failed failed',
    ],

    'series' => [
        'orders' => 'Orders',
        'revenue' => 'Revenue',
        'delivered' => 'Delivered',
        'success_rate' => 'Success Rate',
    ],

    'tables' => [
        'recent_orders' => 'Recent Orders',
        'recent_activity' => 'Recent Activity',
        'top_customers' => 'Top Customers',
        'tracking' => 'Tracking',
        'customer' => 'Customer',
        'seller' => 'Seller',
        'pickup' => 'Pickup',
        'destination' => 'Destination',
        'status' => 'Status',
        'payment' => 'Payment',
        'amount' => 'Amount',
        'agent' => 'Agent',
        'created' => 'Created',
        'phone' => 'Phone',
        'orders' => 'Orders',
        'total_cod' => 'Total COD',
        'delivered' => 'Delivered',
        'pending' => 'Pending',
        'success' => 'Success',
        'avg_time' => 'Avg Time',
    ],

    'status_buckets' => [
        'created' => 'Created',
        'waiting_pickup' => 'Waiting Pickup',
        'picked_up' => 'Picked Up',
        'at_agency' => 'At Agency',
        'in_transit' => 'In Transit',
        'received' => 'Received',
        'out_for_delivery' => 'Out For Delivery',
        'delivered' => 'Delivered',
        'not_delivered' => 'Not Delivered',
        'returned' => 'Returned',
        'cancelled' => 'Cancelled',
    ],

    'payment_methods_note' => 'Bank transfer is not a supported order payment method in the database (only Cash and Card Payment exist).',

    'limitations' => [
        'late_deliveries' => [
            'metric' => 'Late deliveries',
            'reason' => 'No SLA or promised delivery date is stored on orders.',
        ],
        'payment_method_transfer' => [
            'metric' => 'Transfer payment method',
            'reason' => 'Order payment methods are limited to CASH and CARD_PAYMENT in the database.',
        ],
    ],

    'system' => 'System',
    'unknown' => 'Unknown',
];
