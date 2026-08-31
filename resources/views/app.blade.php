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
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{--
        The layout store only writes `data-bs-theme` once Vue has mounted, which
        is several hundred milliseconds after the stylesheet has painted the
        page in its light default. Reading the persisted preference here — the
        same `theme-customizer` entry the store writes — puts the attribute on
        `<html>` before the first paint, so a dark session never flashes white.
    --}}
    <script>
        (function () {
            try {
                var saved = JSON.parse(localStorage.getItem('theme-customizer') || '{}');
                if (saved && saved.mode) {
                    document.documentElement.setAttribute('data-bs-theme', saved.mode);
                }
            } catch (e) {
                // A malformed or unavailable store just means the light default.
            }
        })();
    </script>

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
