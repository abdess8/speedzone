<script setup>
import SectionHeading from '@/Components/Landing/SectionHeading.vue';

const activeCities = [
    'Rabat', 'Salé', 'Témara', 'Skhirat', 'Kénitra',
    'Sidi Yahya', 'Sidi Slimane', 'Souk El Arbaa', 'Sidi Kacem', 'Mehdia', 'Gharb',
];

const comingSoon = ['Casablanca', 'Fès', 'Marrakech', 'Tanger'];

// Pins positioned (%) over the stylized north-west map area.
const pins = [
    { name: 'Kénitra', x: 46, y: 30 },
    { name: 'Mehdia', x: 38, y: 26 },
    { name: 'Sidi Kacem', x: 63, y: 22 },
    { name: 'Sidi Slimane', x: 60, y: 33 },
    { name: 'Salé', x: 40, y: 46 },
    { name: 'Rabat', x: 36, y: 52 },
    { name: 'Témara', x: 33, y: 60 },
];
</script>

<template>
    <section id="zones" class="sz-section sz-coverage">
        <div class="sz-container">
            <SectionHeading
                eyebrow="Zones couvertes"
                title="Nous livrons là où vous êtes"
                subtitle="Une couverture dense sur l'axe Rabat – Kénitra – Gharb, et une expansion continue vers tout le Maroc."
            />

            <div class="sz-coverage__grid">
                <div class="sz-coverage__map" data-aos="fade-right">
                    <svg viewBox="0 0 400 340" class="sz-coverage__svg" aria-hidden="true">
                        <defs>
                            <linearGradient id="szMap" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#dbeafe" />
                                <stop offset="100%" stop-color="#eff6ff" />
                            </linearGradient>
                        </defs>
                        <path
                            d="M96 22 L150 12 L214 26 L268 20 L326 46 L360 96 L372 150 L356 196 L372 232 L340 276 L300 300 L250 318 L196 322 L150 306 L120 320 L84 300 L60 258 L44 210 L30 160 L40 108 L64 60 Z"
                            fill="url(#szMap)"
                            stroke="#bfdbfe"
                            stroke-width="2"
                        />
                        <path
                            d="M96 22 L150 12 L214 26 L268 20 L326 46 L360 96 L372 150"
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
                        class="sz-pin"
                        :style="{ left: pin.x + '%', top: pin.y + '%' }"
                    >
                        <span class="sz-pin__dot"></span>
                        <span class="sz-pin__ring"></span>
                        <span class="sz-pin__label">{{ pin.name }}</span>
                    </span>
                </div>

                <div class="sz-coverage__panel" data-aos="fade-left">
                    <div class="sz-coverage__block">
                        <div class="sz-coverage__block-head">
                            <span class="sz-legend sz-legend--active"></span>
                            <h3>Zones actives</h3>
                        </div>
                        <div class="sz-chips">
                            <span v-for="city in activeCities" :key="city" class="sz-chip sz-chip--active">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                                    <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ city }}
                            </span>
                        </div>
                    </div>

                    <div class="sz-coverage__block">
                        <div class="sz-coverage__block-head">
                            <span class="sz-legend sz-legend--soon"></span>
                            <h3>Bientôt disponible</h3>
                        </div>
                        <div class="sz-chips">
                            <span v-for="city in comingSoon" :key="city" class="sz-chip sz-chip--soon">
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
.sz-section {
    padding: 5.5rem 1.5rem;
}
.sz-container {
    max-width: 1200px;
    margin: 0 auto;
}
.sz-coverage {
    background: var(--sz-bg);
}
.sz-coverage__grid {
    display: grid;
    grid-template-columns: 1.1fr 1fr;
    gap: 3rem;
    align-items: center;
}
.sz-coverage__map {
    position: relative;
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: var(--sz-radius);
    padding: 1.5rem;
    box-shadow: var(--sz-shadow);
}
.sz-coverage__svg {
    width: 100%;
    height: auto;
    display: block;
}
.sz-pin {
    position: absolute;
    transform: translate(-50%, -50%);
}
.sz-pin__dot {
    display: block;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--sz-primary);
    border: 3px solid #fff;
    box-shadow: 0 4px 10px rgba(29, 78, 216, 0.45);
    position: relative;
    z-index: 2;
}
.sz-pin__ring {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    background: rgba(29, 78, 216, 0.4);
    animation: sz-ring 2.4s ease-out infinite;
}
.sz-pin__label {
    position: absolute;
    left: 50%;
    top: -26px;
    transform: translateX(-50%);
    white-space: nowrap;
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--sz-dark);
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: 999px;
    padding: 0.15rem 0.55rem;
    box-shadow: var(--sz-shadow-sm);
    opacity: 0;
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.sz-pin:hover .sz-pin__label {
    opacity: 1;
    transform: translateX(-50%) translateY(-2px);
}

.sz-coverage__panel {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}
.sz-coverage__block-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 1rem;
}
.sz-coverage__block-head h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-legend {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}
.sz-legend--active { background: var(--sz-primary); }
.sz-legend--soon { background: #cbd5e1; }
.sz-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}
.sz-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.5rem 0.9rem;
    border-radius: 999px;
    border: 1px solid var(--sz-border);
    transition: transform 0.2s ease;
}
.sz-chip:hover {
    transform: translateY(-2px);
}
.sz-chip--active {
    background: rgba(29, 78, 216, 0.07);
    color: var(--sz-primary-dark);
    border-color: rgba(29, 78, 216, 0.2);
}
.sz-chip--active svg {
    color: var(--sz-accent-dark);
}
.sz-chip--soon {
    background: #fff;
    color: var(--sz-muted);
    border-style: dashed;
}

@keyframes sz-ring {
    0% { transform: translate(-50%, -50%) scale(1); opacity: 0.6; }
    100% { transform: translate(-50%, -50%) scale(3.6); opacity: 0; }
}

@media (max-width: 992px) {
    .sz-coverage__grid {
        grid-template-columns: 1fr;
        gap: 2.5rem;
    }
    .sz-coverage__map {
        max-width: 460px;
        margin: 0 auto;
        width: 100%;
    }
}
@media (max-width: 620px) {
    .sz-section {
        padding: 4rem 1.1rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .sz-pin__ring {
        animation: none;
    }
}
</style>
