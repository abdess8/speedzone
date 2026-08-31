<script setup>
import { onMounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import Navbar from '@/Components/Landing/Navbar.vue';
import Footer from '@/Components/Landing/Footer.vue';
import TrackingSearch from '@/Components/Landing/TrackingSearch.vue';
import LandingButton from '@/Components/Landing/LandingButton.vue';
import { useLandingLocale } from '@/Components/Landing/i18n';
import '@/Components/Landing/landing.css';

const props = defineProps({
    trackingNumber: { type: String, default: '' },
    found: { type: Boolean, default: false },
    order: { type: Object, default: null },
    company: { type: Object, default: () => ({}) },
});

const { locale, dir, t } = useLandingLocale();

const formatDate = (iso) => {
    if (!iso) return '';
    try {
        return new Intl.DateTimeFormat(`${locale.value}-MA`, {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(iso));
    } catch (e) {
        return iso;
    }
};

/** The server labels statuses in fr/en only, so Arabic falls back to ours. */
const statusLabel = (status, fallback) => {
    const translated = t(`statuses.${status}`);

    return translated === `statuses.${status}` ? fallback : translated;
};

const syncLang = (value) => document.documentElement.setAttribute('lang', value);

onMounted(() => syncLang(locale.value));
watch(locale, syncLang);

const colorHex = (color) => {
    const map = {
        primary: '#1d4ed8',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#0ea5e9',
        secondary: '#64748b',
        dark: '#0f172a',
    };
    return map[color] || '#1d4ed8';
};
</script>

<template>
    <Head>
        <title>{{ t('tracking.pageTitle', { number: trackingNumber }) }}</title>
        <meta name="robots" content="noindex" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="sz-landing" :dir="dir" :lang="locale">
        <Navbar />

        <main class="sz-tracking">
            <div class="sz-tracking__bg" aria-hidden="true"></div>
            <div class="sz-tracking__container">
                <header class="sz-tracking__head" data-aos="fade-up">
                    <span class="sz-tracking__eyebrow">{{ t('tracking.eyebrow') }}</span>
                    <h1 class="sz-tracking__title">{{ t('tracking.title') }}</h1>
                    <div class="sz-tracking__search">
                        <TrackingSearch variant="compact" />
                    </div>
                </header>

                <!-- Found -->
                <div v-if="found && order" class="sz-tresult" data-aos="fade-up">
                    <div class="sz-tresult__top">
                        <div>
                            <p class="sz-tresult__k">{{ t('tracking.number') }}</p>
                            <p class="sz-tresult__num" dir="ltr">{{ order.tracking_number }}</p>
                            <p v-if="order.city" class="sz-tresult__city">
                                {{ t('tracking.destination', { city: order.city }) }}
                            </p>
                        </div>
                        <span
                            class="sz-tresult__status"
                            :style="{
                                color: colorHex(order.status_color),
                                background: colorHex(order.status_color) + '1f',
                            }"
                        >
                            <i :class="order.status_icon"></i>
                            {{ statusLabel(order.status, order.status_label) }}
                        </span>
                    </div>

                    <div v-if="order.timeline.length" class="sz-timeline">
                        <div
                            v-for="(event, index) in order.timeline"
                            :key="index"
                            class="sz-tl"
                            :class="{ 'sz-tl--last': index === order.timeline.length - 1 }"
                        >
                            <span
                                class="sz-tl__dot"
                                :style="{ background: colorHex(event.color) }"
                            >
                                <i :class="event.icon"></i>
                            </span>
                            <div class="sz-tl__body">
                                <p class="sz-tl__label">{{ statusLabel(event.status, event.label) }}</p>
                                <span class="sz-tl__date">{{ formatDate(event.date) }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="sz-tresult__empty">{{ t('tracking.empty') }}</p>
                </div>

                <!-- Not found -->
                <div v-else class="sz-tnotfound" data-aos="fade-up">
                    <div class="sz-tnotfound__icon">
                        <svg width="42" height="42" viewBox="0 0 24 24" fill="none">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                            <path d="M20 20l-3.5-3.5M9 11h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h2 class="sz-tnotfound__title">{{ t('tracking.notFoundTitle') }}</h2>
                    <p class="sz-tnotfound__text">{{ t('tracking.notFoundText', { number: trackingNumber }) }}</p>
                    <div class="sz-tnotfound__cta">
                        <LandingButton href="/" variant="outline" size="md">{{ t('tracking.back') }}</LandingButton>
                    </div>
                </div>
            </div>
        </main>

        <Footer />
    </div>
</template>

<style scoped>
.sz-tracking {
    position: relative;
    padding: 9rem 1.5rem 5rem;
    min-height: 70vh;
    background: var(--sz-gradient-soft);
    overflow: hidden;
}
.sz-tracking__bg {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 80% 0%, rgba(37, 99, 235, 0.16), transparent 45%);
}
.sz-tracking__container {
    position: relative;
    max-width: 760px;
    margin: 0 auto;
}
.sz-tracking__head {
    text-align: center;
    margin-bottom: 2.5rem;
}
.sz-tracking__eyebrow {
    display: inline-block;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--sz-primary);
    background: rgba(29, 78, 216, 0.08);
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    margin-bottom: 1rem;
}
.sz-tracking__title {
    margin: 0 0 1.6rem;
    font-size: clamp(1.7rem, 3.5vw, 2.4rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    color: var(--sz-dark);
}
.sz-tracking__search {
    display: flex;
    justify-content: center;
}

.sz-tresult,
.sz-tnotfound {
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: var(--sz-radius);
    box-shadow: var(--sz-shadow-lg);
    padding: 2rem;
}
.sz-tresult__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--sz-border);
}
.sz-tresult__k {
    margin: 0;
    font-size: 0.78rem;
    color: var(--sz-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.sz-tresult__num {
    margin: 0.25rem 0 0;
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--sz-dark);
}
.sz-tresult__city {
    margin: 0.35rem 0 0;
    font-size: 0.9rem;
    color: var(--sz-slate);
}
.sz-tresult__status {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-weight: 700;
    font-size: 0.9rem;
    padding: 0.55rem 1rem;
    border-radius: 999px;
}

.sz-timeline {
    margin-top: 1.8rem;
    display: flex;
    flex-direction: column;
}
.sz-tl {
    position: relative;
    display: flex;
    gap: 1rem;
    padding-bottom: 1.6rem;
}
.sz-tl::before {
    content: '';
    position: absolute;
    inset-inline-start: 17px;
    top: 36px;
    bottom: 0;
    width: 2px;
    background: var(--sz-border);
}
.sz-tl--last::before {
    display: none;
}
.sz-tl__dot {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 0 0 4px #fff;
    z-index: 1;
}
.sz-tl__body {
    padding-top: 0.35rem;
}
.sz-tl__label {
    margin: 0;
    font-weight: 700;
    color: var(--sz-dark);
    font-size: 0.95rem;
}
.sz-tl__date {
    font-size: 0.82rem;
    color: var(--sz-muted);
}
.sz-tresult__empty {
    margin: 1.5rem 0 0;
    color: var(--sz-muted);
}

.sz-tnotfound {
    text-align: center;
}
.sz-tnotfound__icon {
    width: 84px;
    height: 84px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.3rem;
}
.sz-tnotfound__title {
    margin: 0 0 0.7rem;
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--sz-dark);
}
.sz-tnotfound__text {
    margin: 0 auto 1.8rem;
    max-width: 420px;
    color: var(--sz-slate);
}
.sz-tnotfound__cta {
    display: flex;
    justify-content: center;
}

@media (max-width: 560px) {
    .sz-tracking {
        padding: 7.5rem 1.1rem 3rem;
    }
    .sz-tresult,
    .sz-tnotfound {
        padding: 1.5rem;
    }
}
</style>
