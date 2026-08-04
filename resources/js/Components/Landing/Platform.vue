<script setup>
import SectionHeading from '@/Components/Landing/SectionHeading.vue';
import LandingButton from '@/Components/Landing/LandingButton.vue';

const features = [
    'Gestion des commandes',
    'Suivi temps réel (GPS)',
    'Gestion des livreurs',
    'Paiements COD',
    'Historique complet',
    'Notifications instantanées',
    'Facturation & reversement',
    'QR Codes & scan',
];
</script>

<template>
    <section id="plateforme" class="owl-section owl-platform">
        <div class="owl-container owl-platform__grid">
            <div class="owl-platform__media" data-aos="fade-right">
                <div class="owl-laptop">
                    <div class="owl-laptop__screen">
                        <div class="owl-laptop__topbar">
                            <span class="owl-laptop__brand">OWL Delivery</span>
                            <span class="owl-laptop__dots"><i></i><i></i><i></i></span>
                        </div>
                        <div class="owl-laptop__content">
                            <div class="owl-laptop__cards">
                                <div class="owl-lcard">
                                    <span class="owl-lcard__k">Commandes</span>
                                    <span class="owl-lcard__v">3 942</span>
                                    <span class="owl-lcard__bar"><i style="width: 78%"></i></span>
                                </div>
                                <div class="owl-lcard">
                                    <span class="owl-lcard__k">Livrées</span>
                                    <span class="owl-lcard__v">3 705</span>
                                    <span class="owl-lcard__bar"><i style="width: 92%; background: var(--owl-accent)"></i></span>
                                </div>
                            </div>
                            <div class="owl-laptop__chart">
                                <svg viewBox="0 0 320 120" preserveAspectRatio="none" width="100%" height="120">
                                    <g v-for="(h, i) in [50, 72, 45, 88, 66, 100, 80]" :key="i">
                                        <rect
                                            :x="12 + i * 44"
                                            :y="120 - h"
                                            width="26"
                                            :height="h"
                                            rx="6"
                                            :fill="i % 2 ? '#f15a24' : '#0d4a9d'"
                                            :opacity="0.85"
                                        />
                                    </g>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="owl-laptop__base"></div>
                </div>

                <div class="owl-platform__phone">
                    <div class="owl-platform__phone-inner">
                        <span class="owl-platform__phone-label">Notifications</span>
                        <div class="owl-pnote"><span>📦</span> Nouvelle commande</div>
                        <div class="owl-pnote"><span>✅</span> Colis livré · COD 240 DH</div>
                        <div class="owl-pnote"><span>🚚</span> Transfert en transit</div>
                    </div>
                </div>
            </div>

            <div class="owl-platform__text">
                <SectionHeading
                    align="left"
                    eyebrow="Plateforme"
                    title="Une plateforme intelligente pour gérer toute votre logistique"
                    subtitle="Centralisez commandes, livreurs, paiements et retours. Prenez des décisions plus rapidement grâce à des données claires en temps réel."
                />
                <ul class="owl-platform__features">
                    <li v-for="(feature, i) in features" :key="feature" data-aos="fade-up" :data-aos-delay="i * 60">
                        <span class="owl-check">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                                <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        {{ feature }}
                    </li>
                </ul>
                <div class="owl-platform__cta" data-aos="fade-up">
                    <LandingButton href="/register" variant="primary" size="lg">
                        Découvrir la plateforme
                    </LandingButton>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.owl-section {
    padding: 5.5rem 1.5rem;
}
.owl-container {
    max-width: 1200px;
    margin: 0 auto;
}
.owl-platform {
    background: var(--owl-bg);
}
.owl-platform__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3.5rem;
    align-items: center;
}

.owl-platform__media {
    position: relative;
}
.owl-laptop {
    position: relative;
}
.owl-laptop__screen {
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: 18px 18px 6px 6px;
    box-shadow: var(--owl-shadow-lg);
    overflow: hidden;
}
.owl-laptop__topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.7rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid var(--owl-border);
}
.owl-laptop__brand {
    font-weight: 800;
    font-size: 0.85rem;
    color: var(--owl-primary);
}
.owl-laptop__dots {
    display: inline-flex;
    gap: 5px;
}
.owl-laptop__dots i {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #e2e8f0;
}
.owl-laptop__content {
    padding: 1.2rem;
}
.owl-laptop__cards {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.9rem;
    margin-bottom: 1.1rem;
}
.owl-lcard {
    background: #f8fafc;
    border: 1px solid var(--owl-border);
    border-radius: 14px;
    padding: 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.owl-lcard__k {
    font-size: 0.75rem;
    color: var(--owl-muted);
}
.owl-lcard__v {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--owl-dark);
}
.owl-lcard__bar {
    height: 6px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.owl-lcard__bar i {
    display: block;
    height: 100%;
    background: var(--owl-primary);
    border-radius: 999px;
}
.owl-laptop__chart {
    background: #f8fafc;
    border: 1px solid var(--owl-border);
    border-radius: 14px;
    padding: 0.8rem;
}
.owl-laptop__base {
    height: 14px;
    width: 60%;
    margin: 0 auto;
    background: linear-gradient(180deg, #e2e8f0, #cbd5e1);
    border-radius: 0 0 12px 12px;
}

.owl-platform__phone {
    position: absolute;
    right: -12px;
    bottom: -34px;
    width: 180px;
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: 26px;
    padding: 0.7rem;
    box-shadow: var(--owl-shadow-lg);
    animation: owl-float-y 6s ease-in-out infinite;
}
.owl-platform__phone-inner {
    background: #f8fafc;
    border-radius: 20px;
    padding: 0.8rem;
}
.owl-platform__phone-label {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--owl-dark);
    display: block;
    margin-bottom: 0.5rem;
}
.owl-pnote {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: 12px;
    padding: 0.5rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--owl-slate);
    margin-bottom: 0.45rem;
}

.owl-platform__features {
    list-style: none;
    margin: 0.5rem 0 0;
    padding: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.85rem 1.2rem;
}
.owl-platform__features li {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-weight: 600;
    color: var(--owl-dark);
    font-size: 0.95rem;
}
.owl-check {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 8px;
    background: rgba(241, 90, 36, 0.14);
    color: var(--owl-accent-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.owl-platform__cta {
    margin-top: 2rem;
}

@keyframes owl-float-y {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}

@media (max-width: 992px) {
    .owl-platform__grid {
        grid-template-columns: 1fr;
        gap: 4.5rem;
    }
    .owl-platform__media {
        max-width: 480px;
    }
}
@media (max-width: 620px) {
    .owl-section {
        padding: 4rem 1.1rem;
    }
    .owl-platform__features {
        grid-template-columns: 1fr;
    }
    .owl-platform__phone {
        width: 140px;
        right: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .owl-platform__phone {
        animation: none;
    }
}
</style>
