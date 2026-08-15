<script setup>
import LandingButton from '@/Components/Landing/LandingButton.vue';
import TrackingSearch from '@/Components/Landing/TrackingSearch.vue';
import { useLandingLocale } from '@/Components/Landing/i18n';

const { t } = useLandingLocale();
</script>

<template>
    <section id="accueil" class="sz-hero">
        <div class="sz-hero__bg" aria-hidden="true">
            <span class="sz-hero__blob sz-hero__blob--1"></span>
            <span class="sz-hero__blob sz-hero__blob--2"></span>
            <span class="sz-hero__grid"></span>
        </div>

        <div class="sz-hero__inner">
            <div class="sz-hero__left" data-aos="fade-up">
                <span class="sz-hero__badge">
                    <span class="sz-hero__badge-dot"></span>
                    {{ t('hero.badge') }}
                </span>

                <h1 class="sz-hero__title">
                    {{ t('hero.titleLead') }}
                    <span class="sz-hero__title-grad">{{ t('hero.titleHighlight') }}</span>
                    {{ t('hero.titleTail') }}
                </h1>

                <p class="sz-hero__subtitle">{{ t('hero.subtitle') }}</p>

                <div class="sz-hero__actions">
                    <LandingButton href="/register" variant="primary" size="lg">
                        {{ t('hero.cta') }}
                    </LandingButton>
                    <LandingButton href="#tarifs" variant="light" size="lg">
                        {{ t('hero.ctaSecondary') }}
                    </LandingButton>
                </div>

                <div class="sz-hero__track">
                    <TrackingSearch />
                </div>

                <div class="sz-hero__proof">
                    <div class="sz-hero__stars" :aria-label="t('hero.ratingLabel')">
                        <svg v-for="n in 5" :key="n" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l2.9 6.2 6.8.8-5 4.6 1.3 6.7L12 17.8 5.9 20.3l1.4-6.7-5-4.6 6.8-.8z" />
                        </svg>
                    </div>
                    <div class="sz-hero__proof-text">
                        <strong>{{ t('hero.rating') }}</strong>
                        <span>{{ t('hero.proof') }}</span>
                    </div>
                </div>
            </div>

            <div class="sz-hero__right" data-aos="fade-left" data-aos-delay="80">
                <div class="sz-hero__visual">
                    <!-- Dashboard card -->
                    <div class="sz-mock sz-mock--dash">
                        <div class="sz-mock__bar">
                            <span></span><span></span><span></span>
                            <div class="sz-mock__pill">app.speedzone.ma</div>
                        </div>
                        <div class="sz-mock__body">
                            <div class="sz-mock__row">
                                <div>
                                    <p class="sz-mock__label">{{ t('hero.mock.delivered') }}</p>
                                    <p class="sz-mock__value">1 284</p>
                                </div>
                                <span class="sz-mock__trend">+12,4%</span>
                            </div>
                            <svg class="sz-mock__chart" viewBox="0 0 300 90" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="szArea" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#1d4ed8" stop-opacity="0.28" />
                                        <stop offset="100%" stop-color="#1d4ed8" stop-opacity="0" />
                                    </linearGradient>
                                </defs>
                                <path d="M0 70 L40 60 L80 66 L120 40 L160 48 L200 26 L240 34 L300 12 L300 90 L0 90 Z" fill="url(#szArea)" />
                                <path d="M0 70 L40 60 L80 66 L120 40 L160 48 L200 26 L240 34 L300 12" fill="none" stroke="#1d4ed8" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="sz-mock__list">
                                <div class="sz-mock__item">
                                    <span class="sz-mock__ic sz-mock__ic--blue">SP</span>
                                    <div>
                                        <p>SPD-2026-583920</p>
                                        <small>Rabat → Kénitra</small>
                                    </div>
                                    <span class="sz-mock__tag sz-mock__tag--green">{{ t('hero.mock.statusDelivered') }}</span>
                                </div>
                                <div class="sz-mock__item">
                                    <span class="sz-mock__ic sz-mock__ic--amber">SP</span>
                                    <div>
                                        <p>SPD-2026-583921</p>
                                        <small>Casablanca → Marrakech</small>
                                    </div>
                                    <span class="sz-mock__tag sz-mock__tag--amber">{{ t('hero.mock.statusOnTheWay') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="sz-mock sz-mock--phone">
                        <div class="sz-phone__notch"></div>
                        <div class="sz-phone__screen">
                            <p class="sz-phone__title">{{ t('hero.mock.liveTracking') }}</p>
                            <div class="sz-phone__map">
                                <svg viewBox="0 0 120 120" width="100%" height="100%">
                                    <path d="M8 30 Q40 10 70 34 T112 40 L108 96 Q70 112 40 92 T10 88 Z" fill="#eef2ff" stroke="#c7d2fe" stroke-width="1.5" />
                                    <path d="M20 70 Q50 55 90 48" fill="none" stroke="#1d4ed8" stroke-width="2.5" stroke-dasharray="4 5" stroke-linecap="round" />
                                    <circle cx="20" cy="70" r="5" fill="#10b981" />
                                    <circle cx="90" cy="48" r="5" fill="#1d4ed8" />
                                </svg>
                                <span class="sz-phone__pulse"></span>
                            </div>
                            <div class="sz-phone__status">
                                <span class="sz-phone__bike">🛵</span>
                                <div>
                                    <p>{{ t('hero.mock.driverOnTheWay') }}</p>
                                    <small>{{ t('hero.mock.eta') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floating cards -->
                    <div class="sz-float sz-float--1" data-aos="zoom-in" data-aos-delay="220">
                        <span class="sz-float__ic sz-float__ic--green">✓</span>
                        <div><p>{{ t('hero.mock.parcelDelivered') }}</p><small>{{ t('hero.mock.minutesAgo') }}</small></div>
                    </div>
                    <div class="sz-float sz-float--2" data-aos="zoom-in" data-aos-delay="300">
                        <span class="sz-float__ic sz-float__ic--blue">🚚</span>
                        <div><p>{{ t('hero.mock.driverOnTheWay') }}</p><small>{{ t('hero.mock.downtown') }}</small></div>
                    </div>
                    <div class="sz-float sz-float--3" data-aos="zoom-in" data-aos-delay="380">
                        <span class="sz-float__ic sz-float__ic--amber">💵</span>
                        <div><p>{{ t('hero.mock.codPayment') }}</p><small>350,00 DH</small></div>
                    </div>
                    <div class="sz-float sz-float--4" data-aos="zoom-in" data-aos-delay="460">
                        <span class="sz-float__ic sz-float__ic--violet">📦</span>
                        <div><p>{{ t('hero.mock.pickupConfirmed') }}</p><small>Salé</small></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.sz-hero {
    position: relative;
    padding: 9.5rem 1.5rem 5rem;
    overflow: hidden;
    background: var(--sz-gradient-soft);
}
.sz-hero__bg {
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 0;
}
.sz-hero__blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(70px);
    opacity: 0.5;
}
.sz-hero__blob--1 {
    width: 480px;
    height: 480px;
    top: -160px;
    right: -80px;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.5), transparent 70%);
}
.sz-hero__blob--2 {
    width: 420px;
    height: 420px;
    bottom: -160px;
    left: -100px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.35), transparent 70%);
}
.sz-hero__grid {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(15, 23, 42, 0.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(15, 23, 42, 0.035) 1px, transparent 1px);
    background-size: 44px 44px;
    mask-image: radial-gradient(circle at 50% 30%, #000 30%, transparent 75%);
    -webkit-mask-image: radial-gradient(circle at 50% 30%, #000 30%, transparent 75%);
}

.sz-hero__inner {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    gap: 3rem;
    align-items: center;
}

.sz-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid var(--sz-border);
    color: var(--sz-primary-dark);
    font-weight: 600;
    font-size: 0.82rem;
    padding: 0.5rem 0.95rem;
    border-radius: 999px;
    box-shadow: var(--sz-shadow-sm);
}
.sz-hero__badge-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--sz-accent);
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
}

.sz-hero__title {
    margin: 1.4rem 0 0;
    font-size: clamp(2.4rem, 5vw, 3.75rem);
    line-height: 1.08;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--sz-dark);
}
.sz-hero__title-grad {
    background: var(--sz-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.sz-hero__subtitle {
    margin: 1.4rem 0 0;
    font-size: 1.1rem;
    color: var(--sz-slate);
    max-width: 520px;
}

.sz-hero__actions {
    margin-top: 2rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.9rem;
}

.sz-hero__track {
    margin-top: 1.6rem;
}

.sz-hero__proof {
    margin-top: 1.8rem;
    display: flex;
    align-items: center;
    gap: 0.9rem;
}
.sz-hero__stars {
    color: #f59e0b;
    display: inline-flex;
    gap: 2px;
}
.sz-hero__proof-text {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
}
.sz-hero__proof-text strong {
    font-size: 1.05rem;
    color: var(--sz-dark);
}
.sz-hero__proof-text span {
    font-size: 0.85rem;
    color: var(--sz-muted);
}

/* ---------- Visual ---------- */
.sz-hero__right {
    position: relative;
}
.sz-hero__visual {
    position: relative;
    min-height: 460px;
}

.sz-mock {
    background: #fff;
    border-radius: var(--sz-radius);
    box-shadow: var(--sz-shadow-lg);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.sz-mock--dash {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 430px;
    margin-left: auto;
    overflow: hidden;
    animation: sz-float-y 7s ease-in-out infinite;
}
.sz-mock__bar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0.85rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid var(--sz-border);
}
.sz-mock__bar span {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #e2e8f0;
}
.sz-mock__bar span:nth-child(1) { background: #fca5a5; }
.sz-mock__bar span:nth-child(2) { background: #fcd34d; }
.sz-mock__bar span:nth-child(3) { background: #86efac; }
.sz-mock__pill {
    margin-inline-start: auto;
    font-size: 0.7rem;
    color: var(--sz-muted);
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: 999px;
    padding: 0.2rem 0.7rem;
}
.sz-mock__body {
    padding: 1.2rem;
}
.sz-mock__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.sz-mock__label {
    margin: 0;
    font-size: 0.8rem;
    color: var(--sz-muted);
}
.sz-mock__value {
    margin: 0.2rem 0 0;
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--sz-dark);
}
.sz-mock__trend {
    background: rgba(16, 185, 129, 0.12);
    color: var(--sz-accent-dark);
    font-weight: 700;
    font-size: 0.8rem;
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
}
.sz-mock__chart {
    width: 100%;
    height: 90px;
    margin: 0.6rem 0 1rem;
}
.sz-mock__list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.sz-mock__item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.7rem;
    border-radius: 14px;
    background: #f8fafc;
}
.sz-mock__item p {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-mock__item small {
    color: var(--sz-muted);
    font-size: 0.72rem;
}
.sz-mock__item > div {
    flex: 1;
}
.sz-mock__ic {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.72rem;
    font-weight: 800;
    color: #fff;
}
.sz-mock__ic--blue { background: var(--sz-primary); }
.sz-mock__ic--amber { background: #f59e0b; }
.sz-mock__tag {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
}
.sz-mock__tag--green { background: rgba(16, 185, 129, 0.14); color: var(--sz-accent-dark); }
.sz-mock__tag--amber { background: rgba(245, 158, 11, 0.16); color: #b45309; }

.sz-mock--phone {
    position: absolute;
    left: -10px;
    bottom: -30px;
    z-index: 3;
    width: 190px;
    padding: 0.5rem;
    border-radius: 34px;
    animation: sz-float-y 6s ease-in-out infinite reverse;
}
.sz-phone__notch {
    width: 60px;
    height: 6px;
    border-radius: 999px;
    background: #e2e8f0;
    margin: 0.35rem auto 0.6rem;
}
.sz-phone__screen {
    background: linear-gradient(180deg, #f8fafc, #fff);
    border-radius: 26px;
    padding: 0.9rem;
    border: 1px solid var(--sz-border);
}
.sz-phone__title {
    margin: 0 0 0.6rem;
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--sz-dark);
}
.sz-phone__map {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    background: #eef2ff;
    aspect-ratio: 1.1;
}
.sz-phone__pulse {
    position: absolute;
    left: 16%;
    top: 58%;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(16, 185, 129, 0.5);
    animation: sz-pulse 2s ease-out infinite;
}
.sz-phone__status {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin-top: 0.7rem;
    padding: 0.55rem 0.6rem;
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: 14px;
}
.sz-phone__bike { font-size: 1.2rem; }
.sz-phone__status p {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-phone__status small {
    font-size: 0.68rem;
    color: var(--sz-muted);
}

.sz-float {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(8px);
    border: 1px solid var(--sz-border);
    border-radius: 16px;
    padding: 0.6rem 0.85rem;
    box-shadow: var(--sz-shadow);
    z-index: 4;
}
.sz-float p {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-float small {
    font-size: 0.68rem;
    color: var(--sz-muted);
}
.sz-float__ic {
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: #fff;
}
.sz-float__ic--green { background: var(--sz-accent); }
.sz-float__ic--blue { background: var(--sz-primary); }
.sz-float__ic--amber { background: #f59e0b; }
.sz-float__ic--violet { background: #7c3aed; }
.sz-float--1 { top: 8%; left: -18px; animation: sz-float-y 5.5s ease-in-out infinite; }
.sz-float--2 { top: 30%; right: -14px; animation: sz-float-y 6.5s ease-in-out infinite; }
.sz-float--3 { bottom: 20%; right: 6px; animation: sz-float-y 5s ease-in-out infinite reverse; }
.sz-float--4 { bottom: -6px; left: 40%; animation: sz-float-y 7s ease-in-out infinite; }

@keyframes sz-float-y {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}
@keyframes sz-pulse {
    0% { transform: scale(1); opacity: 0.7; }
    100% { transform: scale(3.4); opacity: 0; }
}

@media (max-width: 992px) {
    .sz-hero__inner {
        grid-template-columns: 1fr;
        gap: 4rem;
    }
    .sz-hero__visual {
        min-height: 420px;
        max-width: 460px;
        margin: 0 auto;
    }
    .sz-hero__subtitle {
        max-width: none;
    }
}

@media (max-width: 560px) {
    .sz-hero {
        padding: 7.5rem 1.1rem 3rem;
    }
    .sz-float--1, .sz-float--4 { display: none; }
    .sz-mock--phone { width: 150px; left: 0; bottom: -14px; }
    .sz-hero__visual { min-height: 380px; }
}

@media (prefers-reduced-motion: reduce) {
    .sz-mock--dash, .sz-mock--phone, .sz-float, .sz-phone__pulse {
        animation: none !important;
    }
}
</style>
