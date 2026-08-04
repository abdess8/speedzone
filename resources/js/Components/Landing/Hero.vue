<script setup>
import LandingButton from '@/Components/Landing/LandingButton.vue';
import TrackingSearch from '@/Components/Landing/TrackingSearch.vue';
</script>

<template>
    <section id="accueil" class="owl-hero">
        <div class="owl-hero__bg" aria-hidden="true">
            <span class="owl-hero__blob owl-hero__blob--1"></span>
            <span class="owl-hero__blob owl-hero__blob--2"></span>
            <span class="owl-hero__grid"></span>
        </div>

        <div class="owl-hero__inner">
            <div class="owl-hero__left" data-aos="fade-up">
                <span class="owl-hero__badge">
                    <span class="owl-hero__badge-dot"></span>
                    La livraison nouvelle génération au Maroc
                </span>

                <h1 class="owl-hero__title">
                    Livrez vos colis
                    <span class="owl-hero__title-grad">rapidement</span>
                    avec OWL Delivery.
                </h1>

                <p class="owl-hero__subtitle">
                    La plateforme de livraison la plus fiable au Maroc. Gérez vos expéditions,
                    vos paiements et vos retours depuis une seule plateforme.
                </p>

                <div class="owl-hero__actions">
                    <LandingButton href="/register" variant="primary" size="lg">
                        Créer un compte
                    </LandingButton>
                    <LandingButton href="#contact" variant="light" size="lg">
                        Demander un ramassage
                    </LandingButton>
                </div>

                <div class="owl-hero__track">
                    <TrackingSearch />
                </div>

                <div class="owl-hero__proof">
                    <div class="owl-hero__stars" aria-label="Note 4.9 sur 5">
                        <svg v-for="n in 5" :key="n" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l2.9 6.2 6.8.8-5 4.6 1.3 6.7L12 17.8 5.9 20.3l1.4-6.7-5-4.6 6.8-.8z" />
                        </svg>
                    </div>
                    <div class="owl-hero__proof-text">
                        <strong>4.9/5</strong>
                        <span>+500 entreprises nous font confiance</span>
                    </div>
                </div>
            </div>

            <div class="owl-hero__right" data-aos="fade-left" data-aos-delay="150">
                <div class="owl-hero__visual">
                    <!-- Dashboard card -->
                    <div class="owl-mock owl-mock--dash">
                        <div class="owl-mock__bar">
                            <span></span><span></span><span></span>
                            <div class="owl-mock__pill">delivery.oowlmedia.com</div>
                        </div>
                        <div class="owl-mock__body">
                            <div class="owl-mock__row">
                                <div>
                                    <p class="owl-mock__label">Colis livrés aujourd'hui</p>
                                    <p class="owl-mock__value">1 284</p>
                                </div>
                                <span class="owl-mock__trend">+12,4%</span>
                            </div>
                            <svg class="owl-mock__chart" viewBox="0 0 300 90" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="szArea" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#0d4a9d" stop-opacity="0.28" />
                                        <stop offset="100%" stop-color="#0d4a9d" stop-opacity="0" />
                                    </linearGradient>
                                </defs>
                                <path d="M0 70 L40 60 L80 66 L120 40 L160 48 L200 26 L240 34 L300 12 L300 90 L0 90 Z" fill="url(#szArea)" />
                                <path d="M0 70 L40 60 L80 66 L120 40 L160 48 L200 26 L240 34 L300 12" fill="none" stroke="#0d4a9d" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="owl-mock__list">
                                <div class="owl-mock__item">
                                    <span class="owl-mock__ic owl-mock__ic--blue">SP</span>
                                    <div>
                                        <p>SPD-2026-583920</p>
                                        <small>Rabat → Kénitra</small>
                                    </div>
                                    <span class="owl-mock__tag owl-mock__tag--green">Livré</span>
                                </div>
                                <div class="owl-mock__item">
                                    <span class="owl-mock__ic owl-mock__ic--amber">SP</span>
                                    <div>
                                        <p>SPD-2026-583921</p>
                                        <small>Salé → Témara</small>
                                    </div>
                                    <span class="owl-mock__tag owl-mock__tag--amber">En route</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="owl-mock owl-mock--phone">
                        <div class="owl-phone__notch"></div>
                        <div class="owl-phone__screen">
                            <p class="owl-phone__title">Suivi en direct</p>
                            <div class="owl-phone__map">
                                <svg viewBox="0 0 120 120" width="100%" height="100%">
                                    <path d="M8 30 Q40 10 70 34 T112 40 L108 96 Q70 112 40 92 T10 88 Z" fill="#eef4fc" stroke="#c7d2fe" stroke-width="1.5" />
                                    <path d="M20 70 Q50 55 90 48" fill="none" stroke="#0d4a9d" stroke-width="2.5" stroke-dasharray="4 5" stroke-linecap="round" />
                                    <circle cx="20" cy="70" r="5" fill="#f15a24" />
                                    <circle cx="90" cy="48" r="5" fill="#0d4a9d" />
                                </svg>
                                <span class="owl-phone__pulse"></span>
                            </div>
                            <div class="owl-phone__status">
                                <span class="owl-phone__bike">🛵</span>
                                <div>
                                    <p>Livreur en route</p>
                                    <small>Arrivée dans 12 min</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floating cards -->
                    <div class="owl-float owl-float--1" data-aos="zoom-in" data-aos-delay="450">
                        <span class="owl-float__ic owl-float__ic--green">✓</span>
                        <div><p>Colis livré</p><small>il y a 2 min</small></div>
                    </div>
                    <div class="owl-float owl-float--2" data-aos="zoom-in" data-aos-delay="600">
                        <span class="owl-float__ic owl-float__ic--blue">🚚</span>
                        <div><p>Livreur en route</p><small>Rabat centre</small></div>
                    </div>
                    <div class="owl-float owl-float--3" data-aos="zoom-in" data-aos-delay="750">
                        <span class="owl-float__ic owl-float__ic--amber">💵</span>
                        <div><p>Paiement COD</p><small>350,00 DH</small></div>
                    </div>
                    <div class="owl-float owl-float--4" data-aos="zoom-in" data-aos-delay="900">
                        <span class="owl-float__ic owl-float__ic--violet">📦</span>
                        <div><p>Ramassage confirmé</p><small>Salé</small></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.owl-hero {
    position: relative;
    padding: 9.5rem 1.5rem 5rem;
    overflow: hidden;
    background: var(--owl-gradient-soft);
}
.owl-hero__bg {
    position: absolute;
    inset: 0;
    overflow: hidden;
    z-index: 0;
}
.owl-hero__blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(70px);
    opacity: 0.5;
}
.owl-hero__blob--1 {
    width: 480px;
    height: 480px;
    top: -160px;
    right: -80px;
    background: radial-gradient(circle, rgba(37, 99, 235, 0.5), transparent 70%);
}
.owl-hero__blob--2 {
    width: 420px;
    height: 420px;
    bottom: -160px;
    left: -100px;
    background: radial-gradient(circle, rgba(241, 90, 36, 0.35), transparent 70%);
}
.owl-hero__grid {
    position: absolute;
    inset: 0;
    background-image: linear-gradient(rgba(15, 23, 42, 0.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(15, 23, 42, 0.035) 1px, transparent 1px);
    background-size: 44px 44px;
    mask-image: radial-gradient(circle at 50% 30%, #000 30%, transparent 75%);
    -webkit-mask-image: radial-gradient(circle at 50% 30%, #000 30%, transparent 75%);
}

.owl-hero__inner {
    position: relative;
    z-index: 1;
    max-width: 1200px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    gap: 3rem;
    align-items: center;
}

.owl-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid var(--owl-border);
    color: var(--owl-primary-dark);
    font-weight: 600;
    font-size: 0.82rem;
    padding: 0.5rem 0.95rem;
    border-radius: 999px;
    box-shadow: var(--owl-shadow-sm);
}
.owl-hero__badge-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--owl-accent);
    box-shadow: 0 0 0 4px rgba(241, 90, 36, 0.18);
}

.owl-hero__title {
    margin: 1.4rem 0 0;
    font-size: clamp(2.4rem, 5vw, 3.75rem);
    line-height: 1.08;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: var(--owl-dark);
}
.owl-hero__title-grad {
    background: var(--owl-gradient);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.owl-hero__subtitle {
    margin: 1.4rem 0 0;
    font-size: 1.1rem;
    color: var(--owl-slate);
    max-width: 520px;
}

.owl-hero__actions {
    margin-top: 2rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.9rem;
}

.owl-hero__track {
    margin-top: 1.6rem;
}

.owl-hero__proof {
    margin-top: 1.8rem;
    display: flex;
    align-items: center;
    gap: 0.9rem;
}
.owl-hero__stars {
    color: #f59e0b;
    display: inline-flex;
    gap: 2px;
}
.owl-hero__proof-text {
    display: flex;
    flex-direction: column;
    line-height: 1.3;
}
.owl-hero__proof-text strong {
    font-size: 1.05rem;
    color: var(--owl-dark);
}
.owl-hero__proof-text span {
    font-size: 0.85rem;
    color: var(--owl-muted);
}

/* ---------- Visual ---------- */
.owl-hero__right {
    position: relative;
}
.owl-hero__visual {
    position: relative;
    min-height: 460px;
}

.owl-mock {
    background: #fff;
    border-radius: var(--owl-radius);
    box-shadow: var(--owl-shadow-lg);
    border: 1px solid rgba(226, 232, 240, 0.9);
}

.owl-mock--dash {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 430px;
    margin-left: auto;
    overflow: hidden;
    animation: owl-float-y 7s ease-in-out infinite;
}
.owl-mock__bar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0.85rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid var(--owl-border);
}
.owl-mock__bar span {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #e2e8f0;
}
.owl-mock__bar span:nth-child(1) { background: #fca5a5; }
.owl-mock__bar span:nth-child(2) { background: #fcd34d; }
.owl-mock__bar span:nth-child(3) { background: #86efac; }
.owl-mock__pill {
    margin-left: auto;
    font-size: 0.7rem;
    color: var(--owl-muted);
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: 999px;
    padding: 0.2rem 0.7rem;
}
.owl-mock__body {
    padding: 1.2rem;
}
.owl-mock__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.owl-mock__label {
    margin: 0;
    font-size: 0.8rem;
    color: var(--owl-muted);
}
.owl-mock__value {
    margin: 0.2rem 0 0;
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--owl-dark);
}
.owl-mock__trend {
    background: rgba(241, 90, 36, 0.12);
    color: var(--owl-accent-dark);
    font-weight: 700;
    font-size: 0.8rem;
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
}
.owl-mock__chart {
    width: 100%;
    height: 90px;
    margin: 0.6rem 0 1rem;
}
.owl-mock__list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.owl-mock__item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 0.7rem;
    border-radius: 14px;
    background: #f8fafc;
}
.owl-mock__item p {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--owl-dark);
}
.owl-mock__item small {
    color: var(--owl-muted);
    font-size: 0.72rem;
}
.owl-mock__item > div {
    flex: 1;
}
.owl-mock__ic {
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
.owl-mock__ic--blue { background: var(--owl-primary); }
.owl-mock__ic--amber { background: #f59e0b; }
.owl-mock__tag {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
}
.owl-mock__tag--green { background: rgba(241, 90, 36, 0.14); color: var(--owl-accent-dark); }
.owl-mock__tag--amber { background: rgba(245, 158, 11, 0.16); color: #b45309; }

.owl-mock--phone {
    position: absolute;
    left: -10px;
    bottom: -30px;
    z-index: 3;
    width: 190px;
    padding: 0.5rem;
    border-radius: 34px;
    animation: owl-float-y 6s ease-in-out infinite reverse;
}
.owl-phone__notch {
    width: 60px;
    height: 6px;
    border-radius: 999px;
    background: #e2e8f0;
    margin: 0.35rem auto 0.6rem;
}
.owl-phone__screen {
    background: linear-gradient(180deg, #f8fafc, #fff);
    border-radius: 26px;
    padding: 0.9rem;
    border: 1px solid var(--owl-border);
}
.owl-phone__title {
    margin: 0 0 0.6rem;
    font-size: 0.8rem;
    font-weight: 800;
    color: var(--owl-dark);
}
.owl-phone__map {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    background: #eef4fc;
    aspect-ratio: 1.1;
}
.owl-phone__pulse {
    position: absolute;
    left: 16%;
    top: 58%;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(241, 90, 36, 0.5);
    animation: owl-pulse 2s ease-out infinite;
}
.owl-phone__status {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin-top: 0.7rem;
    padding: 0.55rem 0.6rem;
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: 14px;
}
.owl-phone__bike { font-size: 1.2rem; }
.owl-phone__status p {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--owl-dark);
}
.owl-phone__status small {
    font-size: 0.68rem;
    color: var(--owl-muted);
}

.owl-float {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 0.55rem;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(8px);
    border: 1px solid var(--owl-border);
    border-radius: 16px;
    padding: 0.6rem 0.85rem;
    box-shadow: var(--owl-shadow);
    z-index: 4;
}
.owl-float p {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--owl-dark);
}
.owl-float small {
    font-size: 0.68rem;
    color: var(--owl-muted);
}
.owl-float__ic {
    width: 30px;
    height: 30px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    color: #fff;
}
.owl-float__ic--green { background: var(--owl-accent); }
.owl-float__ic--blue { background: var(--owl-primary); }
.owl-float__ic--amber { background: #f59e0b; }
.owl-float__ic--violet { background: #7c3aed; }
.owl-float--1 { top: 8%; left: -18px; animation: owl-float-y 5.5s ease-in-out infinite; }
.owl-float--2 { top: 30%; right: -14px; animation: owl-float-y 6.5s ease-in-out infinite; }
.owl-float--3 { bottom: 20%; right: 6px; animation: owl-float-y 5s ease-in-out infinite reverse; }
.owl-float--4 { bottom: -6px; left: 40%; animation: owl-float-y 7s ease-in-out infinite; }

@keyframes owl-float-y {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}
@keyframes owl-pulse {
    0% { transform: scale(1); opacity: 0.7; }
    100% { transform: scale(3.4); opacity: 0; }
}

@media (max-width: 992px) {
    .owl-hero__inner {
        grid-template-columns: 1fr;
        gap: 4rem;
    }
    .owl-hero__visual {
        min-height: 420px;
        max-width: 460px;
        margin: 0 auto;
    }
    .owl-hero__subtitle {
        max-width: none;
    }
}

@media (max-width: 560px) {
    .owl-hero {
        padding: 7.5rem 1.1rem 3rem;
    }
    .owl-float--1, .owl-float--4 { display: none; }
    .owl-mock--phone { width: 150px; left: 0; bottom: -14px; }
    .owl-hero__visual { min-height: 380px; }
}

@media (prefers-reduced-motion: reduce) {
    .owl-mock--dash, .owl-mock--phone, .owl-float, .owl-phone__pulse {
        animation: none !important;
    }
}
</style>
