<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AOS from 'aos';

import Navbar from '@/Components/Landing/Navbar.vue';
import Hero from '@/Components/Landing/Hero.vue';
import Services from '@/Components/Landing/Services.vue';
import Stats from '@/Components/Landing/Stats.vue';
import Platform from '@/Components/Landing/Platform.vue';
import Process from '@/Components/Landing/Process.vue';
import Coverage from '@/Components/Landing/Coverage.vue';
import Pricing from '@/Components/Landing/Pricing.vue';
import Testimonials from '@/Components/Landing/Testimonials.vue';
import CTA from '@/Components/Landing/CTA.vue';
import Footer from '@/Components/Landing/Footer.vue';
import { useLandingLocale } from '@/Components/Landing/i18n';
import '@/Components/Landing/landing.css';

const props = defineProps({
    authenticated: {
        type: Boolean,
        default: false,
    },
    company: {
        type: Object,
        default: () => ({}),
    },
    coverage: {
        type: Object,
        default: () => ({ cities: [], totals: { cities: 0, sectors: 0, regions: 0 }, price: { min: 0, max: 0 } }),
    },
});

const { locale, dir, t } = useLandingLocale();

const structuredData = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: props.company.name ?? 'SpeedZone',
    slogan: t('meta.slogan'),
    description: t('meta.description'),
    areaServed: { '@type': 'Country', name: 'Morocco' },
    address: {
        '@type': 'PostalAddress',
        streetAddress: props.company.address,
        addressLocality: props.company.city,
        addressCountry: props.company.country_code ?? 'MA',
    },
    contactPoint: {
        '@type': 'ContactPoint',
        telephone: props.company.phone_link,
        contactType: 'customer service',
        email: props.company.email,
        areaServed: 'MA',
        availableLanguage: ['fr', 'ar', 'en'],
    },
    sameAs: props.company.instagram ? [props.company.instagram] : [],
}));

const JSON_LD_ID = 'sz-landing-jsonld';

const writeStructuredData = () => {
    if (typeof document === 'undefined') {
        return;
    }

    let script = document.getElementById(JSON_LD_ID);

    if (!script) {
        script = document.createElement('script');
        script.id = JSON_LD_ID;
        script.type = 'application/ld+json';
        document.head.appendChild(script);
    }

    script.textContent = JSON.stringify(structuredData.value);
};

onMounted(() => {
    // The layout is injected on Inertia navigation, so refresh the observers.
    if (AOS && typeof AOS.refreshHard === 'function') {
        AOS.refreshHard();
    }

    document.documentElement.setAttribute('lang', locale.value);
    writeStructuredData();
});

watch(locale, (value) => {
    document.documentElement.setAttribute('lang', value);
    writeStructuredData();
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.getElementById(JSON_LD_ID)?.remove();
    }
});
</script>

<template>
    <Head>
        <title>{{ t('meta.title') }}</title>
        <meta name="description" :content="t('meta.description')" />
        <meta name="theme-color" content="#1D4ED8" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />

        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="SpeedZone" />
        <meta property="og:title" :content="t('meta.title')" />
        <meta property="og:description" :content="t('meta.ogDescription')" />
        <meta property="og:locale" :content="`${locale}_MA`" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="t('meta.title')" />
        <meta name="twitter:description" :content="t('meta.ogDescription')" />
    </Head>

    <div class="sz-landing" :dir="dir" :lang="locale">
        <Navbar :authenticated="authenticated" />

        <main>
            <Hero />
            <Services />
            <Stats />
            <Platform />
            <Process />
            <Coverage :cities="coverage.cities" :totals="coverage.totals" />
            <Pricing :cities="coverage.cities" :price="coverage.price" />
            <Testimonials />
            <CTA :authenticated="authenticated" />
        </main>

        <Footer />
    </div>
</template>
