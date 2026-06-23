<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>SpeedZone Express - Logistics Management Platform</title>
    <meta name="description"
        content="SpeedZone Express is a modern logistics and delivery management platform built with Inertia.js, Vue.js, and Laravel.">
    <meta name="keywords"
        content="SpeedZone Express, logistics, delivery, transport, Inertia.js, Vue.js, Laravel">
    <meta name="author" content="SpeedZone Express">

    <!-- Social Media Meta Tags -->
    <meta property="og:title" content="SpeedZone Express - Logistics Management Platform">
    <meta property="og:description"
        content="Manage deliveries, invoices, and operations with SpeedZone Express, a modern logistics platform.">
    <meta property="og:image" content="URL to the template's logo or featured image">
    <meta property="og:url" content="URL to the template's webpage">
    <meta name="twitter:card" content="summary_large_image">

    <!-- App favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ Vite::asset('resources/images/favicon-32x32.png') }}">
    <link rel="shortcut icon" href="{{ Vite::asset('resources/images/favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ Vite::asset('resources/images/apple-touch-icon.png') }}">

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
