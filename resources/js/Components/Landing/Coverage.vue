<script setup>
import SectionHeading from '@/Components/Landing/SectionHeading.vue';

const activeCities = [
    'Rabat', 'Salé', 'Témara', 'Skhirat', 'Kénitra',
    'Sidi Yahya', 'Sidi Slimane', 'Souk El Arbaa', 'Sidi Kacem', 'Mehdia', 'Gharb',
];

const comingSoon = ['Casablanca', 'Fès', 'Marrakech', 'Tanger'];

// Pins positioned (%) over the Morocco SVG (Mercator, viewBox 0 0 400 340).
const pins = [
    { name: 'Tanger', x: 66.6, y: 10.7 },
    { name: 'Rabat', x: 61.4, y: 20.8 },
    { name: 'Casablanca', x: 57.6, y: 23.4 },
    { name: 'Marrakech', x: 55.6, y: 34.5 },
    { name: 'Agadir', x: 47.3, y: 41.2 },
    { name: 'Fès', x: 70.8, y: 20.9 },
    { name: 'Oujda', x: 86.7, y: 17 },
    { name: 'Laâyoune', x: 29, y: 59.3 },
    { name: 'Dakhla', x: 14.7, y: 77.6 },
];
</script>

<template>
    <section id="zones" class="owl-section owl-coverage">
        <div class="owl-container">
            <SectionHeading
                eyebrow="Zones couvertes"
                title="Nous livrons là où vous êtes"
                subtitle="Une couverture dense sur l'axe Rabat – Kénitra – Gharb, et une expansion continue vers tout le Maroc."
            />

            <div class="owl-coverage__grid">
                <div class="owl-coverage__map" data-aos="fade-right">
                    <svg viewBox="0 0 400 340" class="owl-coverage__svg" aria-hidden="true" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <linearGradient id="szMap" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#d9e6f7" />
                                <stop offset="100%" stop-color="#eff6ff" />
                            </linearGradient>
                        </defs>
                        <path
                            d="M288.8 18.1 L302.6 27.8 L324.4 26.2 L348.1 31.2 L358.1 31.5 L366.7 45.9 L368.1 59.6 L376 83 L382 87.7 L377.8 96.2 L347.8 99.9 L337.5 108 L324.2 109.9 L323.2 126 L296.5 134.5 L287.7 145.2 L269 151 L246.1 154.2 L209.1 169.9 L209.3 194.8 L205.8 194.8 L205.8 194.8 L206.4 206 L192.2 206.7 L184.8 211.4 L174.4 211.4 L166.1 208.7 L146.9 211 L139.4 227.1 L132.3 228.6 L121.5 254.5 L89.7 276.4 L82.1 304.2 L72.7 313.2 L70 320.4 L18.4 322 L18 322 L19.1 312.7 L27.9 307.3 L35.4 296.8 L33.9 290 L41.8 275.8 L54.5 262.9 L62.2 259.6 L68.3 247.7 L68.8 236.7 L77.1 224 L92.3 216.5 L106.9 195.2 L107.3 194.9 L118.8 186.8 L140.1 184.5 L158.1 170.1 L169.6 164.5 L188.7 146.7 L183 119.9 L191.7 101.2 L194.8 89.6 L209.5 74.7 L232.5 64.5 L249.5 55.3 L264.8 32 L272 18 L288.8 18.1 Z"
                            fill="url(#szMap)"
                            stroke="#bfdbfe"
                            stroke-width="2"
                            stroke-linejoin="round"
                        />
                        <path
                            d="M274.2 18 L270.4 30.7 L251.1 57.3 L242.9 62.2 L233.9 67.3"
                            fill="none"
                            stroke="#93c5fd"
                            stroke-width="2"
                            stroke-dasharray="3 6"
                            stroke-linecap="round"
                        />
                    </svg>

                    <span
                        v-for="pin in pins"
                        :key="pin.name"
                        class="owl-pin"
                        :style="{ left: pin.x + '%', top: pin.y + '%' }"
                    >
                        <span class="owl-pin__dot"></span>
                        <span class="owl-pin__ring"></span>
                        <span class="owl-pin__label">{{ pin.name }}</span>
                    </span>
                </div>

                <div class="owl-coverage__panel" data-aos="fade-left">
                    <div class="owl-coverage__block">
                        <div class="owl-coverage__block-head">
                            <span class="owl-legend owl-legend--active"></span>
                            <h3>Zones actives</h3>
                        </div>
                        <div class="owl-chips">
                            <span v-for="city in activeCities" :key="city" class="owl-chip owl-chip--active">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                                    <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ city }}
                            </span>
                        </div>
                    </div>

                    <div class="owl-coverage__block">
                        <div class="owl-coverage__block-head">
                            <span class="owl-legend owl-legend--soon"></span>
                            <h3>Bientôt disponible</h3>
                        </div>
                        <div class="owl-chips">
                            <span v-for="city in comingSoon" :key="city" class="owl-chip owl-chip--soon">
                                {{ city }}
                            </span>
                        </div>
                    </div>
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
.owl-coverage {
    background: var(--owl-bg);
}
.owl-coverage__grid {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 3rem;
    align-items: center;
}
.owl-coverage__map {
    position: relative;
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: var(--owl-radius);
    padding: 1.5rem;
    box-shadow: var(--owl-shadow);
    overflow: hidden;
}
.owl-coverage__svg {
    width: 100%;
    height: auto;
    display: block;
}
.owl-pin {
    position: absolute;
    transform: translate(-50%, -50%);
}
.owl-pin__dot {
    display: block;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--owl-primary);
    border: 3px solid #fff;
    box-shadow: 0 4px 10px rgba(13, 74, 157, 0.45);
    position: relative;
    z-index: 2;
}
.owl-pin__ring {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    background: rgba(13, 74, 157, 0.4);
    animation: owl-ring 2.4s ease-out infinite;
}
.owl-pin__label {
    position: absolute;
    left: 50%;
    top: -26px;
    transform: translateX(-50%);
    white-space: nowrap;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--owl-dark);
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    box-shadow: var(--owl-shadow-sm);
    opacity: 0;
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.owl-pin:hover .owl-pin__label {
    opacity: 1;
    transform: translateX(-50%) translateY(-2px);
}

.owl-coverage__panel {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}
.owl-coverage__block-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1rem;
}
.owl-coverage__block-head h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--owl-dark);
}
.owl-legend {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}
.owl-legend--active { background: var(--owl-primary); }
.owl-legend--soon { background: #cbd5e1; }
.owl-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}
.owl-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.5rem 0.9rem;
    border-radius: 999px;
    border: 1px solid var(--owl-border);
    transition: transform 0.2s ease;
}
.owl-chip:hover {
    transform: translateY(-2px);
}
.owl-chip--active {
    background: rgba(13, 74, 157, 0.07);
    color: var(--owl-primary-dark);
    border-color: rgba(13, 74, 157, 0.2);
}
.owl-chip--active svg {
    color: var(--owl-accent-dark);
}
.owl-chip--soon {
    background: #fff;
    color: var(--owl-muted);
    border-style: dashed;
}

@keyframes owl-ring {
    0% { transform: translate(-50%, -50%) scale(1); opacity: 0.6; }
    100% { transform: translate(-50%, -50%) scale(3.6); opacity: 0; }
}

@media (max-width: 992px) {
    .owl-coverage__grid {
        grid-template-columns: 1fr;
        gap: 2.5rem;
    }
    .owl-coverage__map {
        max-width: 460px;
        margin: 0 auto;
        width: 100%;
    }
}
@media (max-width: 620px) {
    .owl-section {
        padding: 4rem 1.1rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .owl-pin__ring {
        animation: none;
    }
}
</style>
