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
    name: 'OWL Delivery',
    slogan: 'Livraison rapide. Confiance assurée.',
    description:
        'OWL Delivery simplifie la livraison de colis entre Rabat, Salé, Kénitra et le Gharb grâce à une plateforme moderne de suivi, paiement et gestion logistique.',
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
        email: 'contact@oowlmedia.com',
    },
};

const JSON_LD_ID = 'owl-landing-jsonld';

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
        <title>OWL Delivery | Livraison Express au Maroc</title>
        <meta
            name="description"
            content="OWL Delivery simplifie la livraison de colis entre Rabat, Salé, Kénitra et le Gharb grâce à une plateforme moderne de suivi, paiement et gestion logistique."
        />
        <meta name="theme-color" content="#0D4A9D" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />

        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="OWL Delivery" />
        <meta property="og:title" content="OWL Delivery | Livraison Express au Maroc" />
        <meta
            property="og:description"
            content="La plateforme de livraison la plus fiable au Maroc. Gérez vos expéditions, vos paiements et vos retours depuis une seule plateforme."
        />
        <meta property="og:locale" content="fr_MA" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="OWL Delivery | Livraison Express au Maroc" />
        <meta
            name="twitter:description"
            content="La plateforme de livraison la plus fiable au Maroc."
        />
    </Head>

    <div class="owl-landing">
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
 * into child components, but the selector `.owl-landing` keeps them contained.
 */
.owl-landing {
    --owl-primary: #0d4a9d;
    --owl-primary-dark: #0a3877;
    --owl-secondary: #1560c4;
    --owl-accent: #f15a24;
    --owl-accent-dark: #d1471a;
    --owl-dark: #0f172a;
    --owl-slate: #475569;
    --owl-muted: #64748b;
    --owl-bg: #f8fafc;
    --owl-bg-soft: #eef4fc;
    --owl-border: #e2e8f0;
    --owl-white: #ffffff;

    --owl-radius: 24px;
    --owl-radius-sm: 14px;
    --owl-radius-lg: 32px;

    --owl-shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.04);
    --owl-shadow: 0 10px 30px rgba(15, 23, 42, 0.06), 0 2px 8px rgba(15, 23, 42, 0.04);
    --owl-shadow-lg: 0 30px 60px rgba(13, 74, 157, 0.12), 0 12px 24px rgba(15, 23, 42, 0.06);
    --owl-shadow-primary: 0 18px 40px rgba(13, 74, 157, 0.28);

    /*
     * Two gradients, because navy and orange sit on opposite sides of the wheel
     * and everything between them is brown. Across a hero panel that midpoint is
     * hundreds of pixels wide and reads as a warm dusk; across a single clipped
     * word it just reads as dirty. So type and buttons ramp within the blues,
     * and only full-bleed panels get the warm one.
     */
    --owl-gradient: linear-gradient(135deg, #0d4a9d 0%, #1560c4 60%, #2c7ae0 100%);
    --owl-gradient-warm: linear-gradient(135deg, #0d4a9d 0%, #123a78 52%, #f15a24 130%);
    --owl-gradient-soft: linear-gradient(160deg, #eef4fc 0%, #f8fafc 100%);

    --owl-font: 'Plus Jakarta Sans', 'Manrope', system-ui, -apple-system, 'Segoe UI', sans-serif;

    font-family: var(--owl-font);
    color: var(--owl-dark);
    background: var(--owl-bg);
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    line-height: 1.6;
    overflow-x: hidden;
}

.owl-landing *,
.owl-landing *::before,
.owl-landing *::after {
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
