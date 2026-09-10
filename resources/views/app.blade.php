<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>SpeedZone Express – Livraison de colis au Maroc</title>
    <meta name="description"
        content="SpeedZone Express est un opérateur de livraison pour le e-commerce marocain. Nous ramassons vos colis en boutique ou en entrepôt, les acheminons partout au Maroc et les remettons au client final. Notre métier couvre le dernier kilomètre : ramassage, transit entre hubs, livraison à domicile, paiement à la livraison (COD), suivi du colis et reversement des encaissements. Chaque commande est tracée jusqu’à la remise en main propre, pour que vendeurs et destinataires livrent et reçoivent en toute confiance.">
    <meta name="keywords"
        content="SpeedZone Express, livraison colis Maroc, e-commerce Maroc, paiement à la livraison, COD, ramassage, logistique, suivi de colis, reversement, livraison nationale">
    <meta name="author" content="SpeedZone Express">

    <!-- Social Media Meta Tags -->
    <meta property="og:title" content="SpeedZone Express – Livraison de colis au Maroc">
    <meta property="og:description"
        content="SpeedZone Express accompagne les e-commerçants marocains du ramassage jusqu’à la livraison : colis, paiement à la livraison, suivi et reversements, partout au Royaume.">
    <meta property="og:image" content="{{ asset('favicon-32x32.png') }}">
    <meta property="og:url" content="{{ url('/') }}">
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
