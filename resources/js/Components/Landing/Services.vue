<script setup>
import SectionHeading from '@/Components/Landing/SectionHeading.vue';
import { useLandingLocale } from '@/Components/Landing/i18n';

const { t } = useLandingLocale();

const services = [
    { key: 'pickup', icon: 'pickup', color: 'blue' },
    { key: 'express', icon: 'express', color: 'green' },
    { key: 'national', icon: 'national', color: 'violet' },
    { key: 'cod', icon: 'cod', color: 'amber' },
    { key: 'returns', icon: 'returns', color: 'rose' },
    { key: 'platform', icon: 'platform', color: 'blue' },
];
</script>

<template>
    <section id="services" class="sz-section sz-services">
        <div class="sz-container">
            <SectionHeading
                :eyebrow="t('services.eyebrow')"
                :title="t('services.title')"
                :subtitle="t('services.subtitle')"
            />

            <div class="sz-services__grid">
                <article
                    v-for="(service, index) in services"
                    :key="service.key"
                    class="sz-scard"
                    :class="`sz-scard--${service.color}`"
                    data-aos="fade-up"
                    :data-aos-delay="index * 40"
                >
                    <div class="sz-scard__icon">
                        <!-- pickup -->
                        <svg v-if="service.icon === 'pickup'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <path d="M3 7l9-4 9 4-9 4-9-4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                            <path d="M3 7v10l9 4 9-4V7M12 11v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        </svg>
                        <!-- express -->
                        <svg v-else-if="service.icon === 'express'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        </svg>
                        <!-- national -->
                        <svg v-else-if="service.icon === 'national'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />
                            <path d="M3 12h18M12 3c2.5 2.5 2.5 15 0 18M12 3c-2.5 2.5-2.5 15 0 18" stroke="currentColor" stroke-width="1.8" />
                        </svg>
                        <!-- cod -->
                        <svg v-else-if="service.icon === 'cod'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <rect x="2.5" y="6" width="19" height="12" rx="2.5" stroke="currentColor" stroke-width="1.8" />
                            <circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8" />
                            <path d="M6 9v6M18 9v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        <!-- returns -->
                        <svg v-else-if="service.icon === 'returns'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <path d="M4 9h11a5 5 0 0 1 0 10H8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M7 6L4 9l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <!-- platform -->
                        <svg v-else viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <rect x="3" y="4" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.8" />
                            <path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h3 class="sz-scard__title">{{ t(`services.items.${service.key}.title`) }}</h3>
                    <p class="sz-scard__desc">{{ t(`services.items.${service.key}.description`) }}</p>
                    <span class="sz-scard__link">
                        {{ t('services.more') }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </article>
            </div>
        </div>
    </section>
</template>

<style scoped>
.sz-section {
    padding: 5.5rem 1.5rem;
}
.sz-container {
    max-width: 1200px;
    margin: 0 auto;
}
.sz-services {
    background: #fff;
}
.sz-services__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}
.sz-scard {
    position: relative;
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: var(--sz-radius);
    padding: 2rem 1.8rem;
    box-shadow: var(--sz-shadow-sm);
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    overflow: hidden;
}
.sz-scard::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 4px;
    background: var(--sz-c, var(--sz-primary));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.35s ease;
}
.sz-scard:hover {
    transform: translateY(-8px);
    box-shadow: var(--sz-shadow-lg);
    border-color: transparent;
}
.sz-scard:hover::before {
    transform: scaleX(1);
}
.sz-scard__icon {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--sz-c, var(--sz-primary));
    background: var(--sz-c-soft, rgba(29, 78, 216, 0.1));
    margin-bottom: 1.3rem;
    transition: transform 0.35s ease;
}
.sz-scard:hover .sz-scard__icon {
    transform: scale(1.08) rotate(-4deg);
}
.sz-scard__title {
    margin: 0 0 0.6rem;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-scard__desc {
    margin: 0;
    font-size: 0.95rem;
    color: var(--sz-slate);
    line-height: 1.65;
}
.sz-scard__link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 1.2rem;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--sz-c, var(--sz-primary));
    opacity: 0;
    transform: translateX(-6px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.sz-scard:hover .sz-scard__link {
    opacity: 1;
    transform: translateX(0);
}

.sz-scard--blue { --sz-c: #1d4ed8; --sz-c-soft: rgba(29, 78, 216, 0.1); }
.sz-scard--green { --sz-c: #059669; --sz-c-soft: rgba(16, 185, 129, 0.12); }
.sz-scard--violet { --sz-c: #7c3aed; --sz-c-soft: rgba(124, 58, 237, 0.1); }
.sz-scard--amber { --sz-c: #d97706; --sz-c-soft: rgba(245, 158, 11, 0.14); }
.sz-scard--rose { --sz-c: #e11d48; --sz-c-soft: rgba(225, 29, 72, 0.1); }

@media (max-width: 992px) {
    .sz-services__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 620px) {
    .sz-section {
        padding: 4rem 1.1rem;
    }
    .sz-services__grid {
        grid-template-columns: 1fr;
    }
}
</style>
