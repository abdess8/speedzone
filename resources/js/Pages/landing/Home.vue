<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AOS from 'aos';

import Navbar from '@/Components/Landing/Navbar.vue';
import Hero from '@/Components/Landing/Hero.vue';
import Services from '@/Components/Landing/Services.vue';
import Stats from '@/Components/Landing/Stats.vue';
import Platform from '@/Components/Landing/Platform.vue';
import Process from '@/Components/Landing/Process.vue';
import Coverage from '@/Components/Landing/Coverage.vue';
import Testimonials from '@/Components/Landing/Testimonials.vue';
import MobileApp from '@/Components/Landing/MobileApp.vue';
import CTA from '@/Components/Landing/CTA.vue';
import Footer from '@/Components/Landing/Footer.vue';

defineProps({
    authenticated: {
        type: Boolean,
        default: false,
    },
});

const structuredData = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'SpeedZone',
    slogan: 'Livraison rapide. Confiance assurée.',
    description:
        'SpeedZone simplifie la livraison de colis entre Rabat, Salé, Kénitra et le Gharb grâce à une plateforme moderne de suivi, paiement et gestion logistique.',
    areaServed: ['Rabat', 'Salé', 'Témara', 'Skhirat', 'Kénitra', 'Gharb'],
    address: {
        '@type': 'PostalAddress',
        addressLocality: 'Rabat',
        addressCountry: 'MA',
    },
    contactPoint: {
        '@type': 'ContactPoint',
        telephone: '+212-5-00-00-00-00',
        contactType: 'customer service',
        email: 'contact@speedzone.ma',
    },
};

const JSON_LD_ID = 'sz-landing-jsonld';

onMounted(() => {
    // The layout is injected on Inertia navigation, so refresh the observers.
    if (AOS && typeof AOS.refreshHard === 'function') {
        AOS.refreshHard();
    }

    // Inject JSON-LD structured data into <head> (reliable across client nav).
    if (typeof document !== 'undefined' && !document.getElementById(JSON_LD_ID)) {
        const script = document.createElement('script');
        script.id = JSON_LD_ID;
        script.type = 'application/ld+json';
        script.textContent = JSON.stringify(structuredData);
        document.head.appendChild(script);
    }
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.getElementById(JSON_LD_ID)?.remove();
    }
});
</script>

<template>
    <Head>
        <title>SpeedZone | Livraison Express au Maroc</title>
        <meta
            name="description"
            content="SpeedZone simplifie la livraison de colis entre Rabat, Salé, Kénitra et le Gharb grâce à une plateforme moderne de suivi, paiement et gestion logistique."
        />
        <meta name="theme-color" content="#1D4ED8" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />

        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="SpeedZone" />
        <meta property="og:title" content="SpeedZone | Livraison Express au Maroc" />
        <meta
            property="og:description"
            content="La plateforme de livraison la plus fiable au Maroc. Gérez vos expéditions, vos paiements et vos retours depuis une seule plateforme."
        />
        <meta property="og:locale" content="fr_MA" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="SpeedZone | Livraison Express au Maroc" />
        <meta
            name="twitter:description"
            content="La plateforme de livraison la plus fiable au Maroc."
        />
    </Head>

    <div class="sz-landing">
        <Navbar :authenticated="authenticated" />

        <main>
            <Hero />
            <Services />
            <Stats />
            <Platform />
            <Process />
            <Coverage />
            <Testimonials />
            <MobileApp />
            <CTA :authenticated="authenticated" />
        </main>

        <Footer />
    </div>
</template>

<style>
/*
 * Design tokens live on the landing root so every scoped child component can
 * consume them via CSS custom-property inheritance without leaking Tailwind /
 * Bootstrap-level globals. Intentionally NOT scoped: the variables must cascade
 * into child components, but the selector `.sz-landing` keeps them contained.
 */
.sz-landing {
    --sz-primary: #1d4ed8;
    --sz-primary-dark: #1e40af;
    --sz-secondary: #2563eb;
    --sz-accent: #10b981;
    --sz-accent-dark: #059669;
    --sz-dark: #0f172a;
    --sz-slate: #475569;
    --sz-muted: #64748b;
    --sz-bg: #f8fafc;
    --sz-bg-soft: #eef2ff;
    --sz-border: #e2e8f0;
    --sz-white: #ffffff;

    --sz-radius: 24px;
    --sz-radius-sm: 14px;
    --sz-radius-lg: 32px;

    --sz-shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.04);
    --sz-shadow: 0 10px 30px rgba(15, 23, 42, 0.06), 0 2px 8px rgba(15, 23, 42, 0.04);
    --sz-shadow-lg: 0 30px 60px rgba(29, 78, 216, 0.12), 0 12px 24px rgba(15, 23, 42, 0.06);
    --sz-shadow-primary: 0 18px 40px rgba(29, 78, 216, 0.28);

    --sz-gradient: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #10b981 160%);
    --sz-gradient-soft: linear-gradient(160deg, #eef2ff 0%, #f8fafc 100%);

    --sz-font: 'Plus Jakarta Sans', 'Manrope', system-ui, -apple-system, 'Segoe UI', sans-serif;

    font-family: var(--sz-font);
    color: var(--sz-dark);
    background: var(--sz-bg);
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    line-height: 1.6;
    overflow-x: hidden;
}

.sz-landing *,
.sz-landing *::before,
.sz-landing *::after {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

@media (prefers-reduced-motion: reduce) {
    html {
        scroll-behavior: auto;
    }
}
</style>
