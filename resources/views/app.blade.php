<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name') }} — Plateforme de gestion de livraison</title>
    <meta name="description"
        content="{{ config('app.name') }} est la plateforme de gestion logistique et de livraison d'OWL Media : expéditions, suivi, paiements et retours au même endroit.">
    <meta name="keywords"
        content="OWL Delivery, OWL Media, livraison, logistique, colis, transport, Maroc">
    <meta name="author" content="OWL Media">
    <meta name="theme-color" content="#0d4a9d">

    <!-- Social Media Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ config('app.name') }} — Plateforme de gestion de livraison">
    <meta property="og:description"
        content="Gérez vos expéditions, vos factures et vos opérations depuis une seule plateforme.">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset('og-image.png') }}">

    <!-- App favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('favicon-48x48.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
