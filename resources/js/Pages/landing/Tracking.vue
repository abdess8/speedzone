<script setup>
import { Head } from '@inertiajs/vue3';
import Navbar from '@/Components/Landing/Navbar.vue';
import Footer from '@/Components/Landing/Footer.vue';
import TrackingSearch from '@/Components/Landing/TrackingSearch.vue';
import LandingButton from '@/Components/Landing/LandingButton.vue';

const props = defineProps({
    trackingNumber: { type: String, default: '' },
    found: { type: Boolean, default: false },
    order: { type: Object, default: null },
});

const formatDate = (iso) => {
    if (!iso) return '';
    try {
        return new Intl.DateTimeFormat('fr-MA', {
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

const colorHex = (color) => {
    const map = {
        primary: '#0d4a9d',
        success: '#f15a24',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#0ea5e9',
        secondary: '#64748b',
        dark: '#0f172a',
    };
    return map[color] || '#0d4a9d';
};
</script>

<template>
    <Head>
        <title>Suivi de colis {{ trackingNumber }} | OWL Delivery</title>
        <meta name="robots" content="noindex" />
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="owl-landing">
        <Navbar />

        <main class="owl-tracking">
            <div class="owl-tracking__bg" aria-hidden="true"></div>
            <div class="owl-tracking__container">
                <header class="owl-tracking__head" data-aos="fade-up">
                    <span class="owl-tracking__eyebrow">Suivi de colis</span>
                    <h1 class="owl-tracking__title">Suivez votre colis en temps réel</h1>
                    <div class="owl-tracking__search">
                        <TrackingSearch variant="compact" />
                    </div>
                </header>

                <!-- Found -->
                <div v-if="found && order" class="owl-tresult" data-aos="fade-up">
                    <div class="owl-tresult__top">
                        <div>
                            <p class="owl-tresult__k">Numéro de suivi</p>
                            <p class="owl-tresult__num">{{ order.tracking_number }}</p>
                            <p v-if="order.city" class="owl-tresult__city">Destination : {{ order.city }}</p>
                        </div>
                        <span
                            class="owl-tresult__status"
                            :style="{
                                color: colorHex(order.status_color),
                                background: colorHex(order.status_color) + '1f',
                            }"
                        >
                            <i :class="order.status_icon"></i>
                            {{ order.status_label }}
                        </span>
                    </div>

                    <div v-if="order.timeline.length" class="owl-timeline">
                        <div
                            v-for="(event, index) in order.timeline"
                            :key="index"
                            class="owl-tl"
                            :class="{ 'owl-tl--last': index === order.timeline.length - 1 }"
                        >
                            <span
                                class="owl-tl__dot"
                                :style="{ background: colorHex(event.color) }"
                            >
                                <i :class="event.icon"></i>
                            </span>
                            <div class="owl-tl__body">
                                <p class="owl-tl__label">{{ event.label }}</p>
                                <span class="owl-tl__date">{{ formatDate(event.date) }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="owl-tresult__empty">Aucun historique disponible pour le moment.</p>
                </div>

                <!-- Not found -->
                <div v-else class="owl-tnotfound" data-aos="fade-up">
                    <div class="owl-tnotfound__icon">
                        <svg width="42" height="42" viewBox="0 0 24 24" fill="none">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                            <path d="M20 20l-3.5-3.5M9 11h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h2 class="owl-tnotfound__title">Numéro introuvable.</h2>
                    <p class="owl-tnotfound__text">
                        Aucun colis ne correspond au numéro
                        <strong>« {{ trackingNumber }} »</strong>. Vérifiez le numéro et réessayez.
                    </p>
                    <div class="owl-tnotfound__cta">
                        <LandingButton href="/" variant="outline" size="md">Retour à l'accueil</LandingButton>
                    </div>
                </div>
            </div>
        </main>

        <Footer />
    </div>
</template>

<style scoped>
.owl-tracking {
    position: relative;
    padding: 9rem 1.5rem 5rem;
    min-height: 70vh;
    background: var(--owl-gradient-soft);
    overflow: hidden;
}
.owl-tracking__bg {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 80% 0%, rgba(37, 99, 235, 0.16), transparent 45%);
}
.owl-tracking__container {
    position: relative;
    max-width: 760px;
    margin: 0 auto;
}
.owl-tracking__head {
    text-align: center;
    margin-bottom: 2.5rem;
}
.owl-tracking__eyebrow {
    display: inline-block;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--owl-primary);
    background: rgba(13, 74, 157, 0.08);
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    margin-bottom: 1rem;
}
.owl-tracking__title {
    margin: 0 0 1.6rem;
    font-size: clamp(1.7rem, 3.5vw, 2.4rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    color: var(--owl-dark);
}
.owl-tracking__search {
    display: flex;
    justify-content: center;
}

.owl-tresult,
.owl-tnotfound {
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: var(--owl-radius);
    box-shadow: var(--owl-shadow-lg);
    padding: 2rem;
}
.owl-tresult__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--owl-border);
}
.owl-tresult__k {
    margin: 0;
    font-size: 0.78rem;
    color: var(--owl-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.owl-tresult__num {
    margin: 0.25rem 0 0;
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--owl-dark);
}
.owl-tresult__city {
    margin: 0.35rem 0 0;
    font-size: 0.9rem;
    color: var(--owl-slate);
}
.owl-tresult__status {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-weight: 700;
    font-size: 0.9rem;
    padding: 0.55rem 1rem;
    border-radius: 999px;
}

.owl-timeline {
    margin-top: 1.8rem;
    display: flex;
    flex-direction: column;
}
.owl-tl {
    position: relative;
    display: flex;
    gap: 1rem;
    padding-bottom: 1.6rem;
}
.owl-tl::before {
    content: '';
    position: absolute;
    left: 17px;
    top: 36px;
    bottom: 0;
    width: 2px;
    background: var(--owl-border);
}
.owl-tl--last::before {
    display: none;
}
.owl-tl__dot {
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
.owl-tl__body {
    padding-top: 0.35rem;
}
.owl-tl__label {
    margin: 0;
    font-weight: 700;
    color: var(--owl-dark);
    font-size: 0.95rem;
}
.owl-tl__date {
    font-size: 0.82rem;
    color: var(--owl-muted);
}
.owl-tresult__empty {
    margin: 1.5rem 0 0;
    color: var(--owl-muted);
}

.owl-tnotfound {
    text-align: center;
}
.owl-tnotfound__icon {
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
.owl-tnotfound__title {
    margin: 0 0 0.7rem;
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--owl-dark);
}
.owl-tnotfound__text {
    margin: 0 auto 1.8rem;
    max-width: 420px;
    color: var(--owl-slate);
}
.owl-tnotfound__cta {
    display: flex;
    justify-content: center;
}

@media (max-width: 560px) {
    .owl-tracking {
        padding: 7.5rem 1.1rem 3rem;
    }
    .owl-tresult,
    .owl-tnotfound {
        padding: 1.5rem;
    }
}
</style>
