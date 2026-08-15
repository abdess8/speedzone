<script setup>
import { computed, ref } from 'vue';
import SectionHeading from '@/Components/Landing/SectionHeading.vue';
import { useLandingLocale } from '@/Components/Landing/i18n';

const props = defineProps({
    cities: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ cities: 0, sectors: 0, regions: 0 }) },
});

const { t, tName } = useLandingLocale();

const active = ref(null);

const cityName = (city) => tName('cities', city.key, city.name);

const priceOf = (city) => `${Math.round(city.price)} ${t('coverage.currency')}`;

/** Only the cities we have coordinates for can be plotted. */
const pins = computed(() => props.cities.filter((city) => city.x !== null && city.y !== null));

/**
 * Chip anchors are absolute positions on the map, not offsets, so the
 * connector has to be drawn from the pin to wherever the chip was placed.
 */
const labelled = computed(() => pins.value.filter((city) => city.chip));

const sorted = computed(() =>
    [...props.cities].sort((a, b) => cityName(a).localeCompare(cityName(b)))
);

/** Hovering one city clears the map of every other pin, chip and leader. */
const dimmed = (city) => active.value !== null && active.value !== city.key;
</script>

<template>
    <section id="zones" class="sz-section sz-coverage">
        <div class="sz-container">
            <SectionHeading
                :eyebrow="t('coverage.eyebrow')"
                :title="t('coverage.title')"
                :subtitle="t('coverage.subtitle')"
            />

            <div class="sz-coverage__grid">
                <div class="sz-coverage__map" data-aos="fade-right">
                    <span class="sz-coverage__badge">
                        <span class="sz-coverage__badge-dot"></span>
                        {{ t('coverage.national') }}
                    </span>

                    <svg viewBox="0 0 400 340" class="sz-coverage__svg" aria-hidden="true" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <linearGradient id="szMap" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#dbeafe" />
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

                    <!-- Leader lines tying each price chip back to its city. -->
                    <svg class="sz-coverage__leaders" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                        <line
                            v-for="city in labelled"
                            :key="`leader-${city.key}`"
                            :class="{ 'is-dimmed': dimmed(city) }"
                            :x1="city.x"
                            :y1="city.y"
                            :x2="city.chip.x"
                            :y2="city.chip.y"
                            stroke="#93c5fd"
                            stroke-width="1"
                            vector-effect="non-scaling-stroke"
                        />
                    </svg>

                    <span
                        v-for="city in pins"
                        :key="city.key"
                        class="sz-pin"
                        :class="{ 'is-active': active === city.key, 'is-dimmed': dimmed(city) }"
                        :style="{ left: `${city.x}%`, top: `${city.y}%` }"
                        tabindex="0"
                        @mouseenter="active = city.key"
                        @mouseleave="active = null"
                        @focus="active = city.key"
                        @blur="active = null"
                    >
                        <span class="sz-pin__dot"></span>
                        <span class="sz-pin__ring"></span>
                        <span v-if="!city.chip" class="sz-pin__tip">
                            <strong>{{ cityName(city) }}</strong>
                            <small>{{ t('coverage.from') }} {{ priceOf(city) }} · {{ city.delay }}</small>
                        </span>
                    </span>

                    <span
                        v-for="city in labelled"
                        :key="`chip-${city.key}`"
                        class="sz-chip-map"
                        :class="{ 'is-active': active === city.key, 'is-dimmed': dimmed(city) }"
                        :style="{ left: `${city.chip.x}%`, top: `${city.chip.y}%` }"
                        @mouseenter="active = city.key"
                        @mouseleave="active = null"
                    >
                        <span class="sz-chip-map__city">{{ cityName(city) }}</span>
                        <span class="sz-chip-map__price">{{ priceOf(city) }}</span>
                        <span class="sz-chip-map__delay">{{ city.delay }}</span>
                    </span>

                    <p class="sz-coverage__hint">{{ t('coverage.mapHint') }}</p>
                </div>

                <div class="sz-coverage__panel" data-aos="fade-left">
                    <div class="sz-coverage__stats">
                        <div class="sz-cstat">
                            <strong>{{ totals.cities }}</strong>
                            <span>{{ t('coverage.stats.cities') }}</span>
                        </div>
                        <div class="sz-cstat">
                            <strong>{{ totals.sectors }}</strong>
                            <span>{{ t('coverage.stats.sectors') }}</span>
                        </div>
                        <div class="sz-cstat">
                            <strong>{{ totals.regions }}</strong>
                            <span>{{ t('coverage.stats.regions') }}</span>
                        </div>
                    </div>

                    <div class="sz-coverage__block">
                        <h3>{{ t('coverage.allCities') }}</h3>
                        <div class="sz-chips">
                            <span
                                v-for="city in sorted"
                                :key="city.key"
                                class="sz-chip"
                                :class="{ 'is-active': active === city.key }"
                                @mouseenter="active = city.key"
                                @mouseleave="active = null"
                            >
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                                    <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                {{ cityName(city) }}
                                <b>{{ priceOf(city) }}</b>
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
    grid-template-columns: 1.15fr 1fr;
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
.sz-coverage__badge {
    position: absolute;
    top: 1.1rem;
    inset-inline-start: 1.1rem;
    z-index: 5;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--sz-primary-dark);
    background: rgba(29, 78, 216, 0.08);
    border-radius: 999px;
    padding: 0.35rem 0.8rem;
}
.sz-coverage__badge-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--sz-accent);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}
.sz-coverage__svg {
    width: 100%;
    height: auto;
    display: block;
}
.sz-coverage__leaders {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    opacity: 0.75;
}
.sz-coverage__hint {
    margin: 0.5rem 0 0;
    text-align: center;
    font-size: 0.78rem;
    color: var(--sz-muted);
}

.sz-pin {
    position: absolute;
    transform: translate(-50%, -50%);
    cursor: pointer;
    outline: none;
}
.sz-pin__dot {
    display: block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--sz-primary);
    border: 2.5px solid #fff;
    box-shadow: 0 3px 8px rgba(29, 78, 216, 0.45);
    position: relative;
    z-index: 2;
    transition: transform 0.2s ease;
}
.sz-pin.is-active .sz-pin__dot,
.sz-pin:focus-visible .sz-pin__dot {
    transform: scale(1.35);
    background: var(--sz-accent-dark);
}
.sz-pin__ring {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    background: rgba(29, 78, 216, 0.35);
    animation: sz-ring 2.8s ease-out infinite;
}
.sz-pin__tip {
    position: absolute;
    left: 50%;
    bottom: 18px;
    transform: translateX(-50%);
    z-index: 8;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    white-space: nowrap;
    background: var(--sz-dark);
    color: #fff;
    border-radius: 10px;
    padding: 0.4rem 0.65rem;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.sz-pin__tip strong {
    font-size: 0.76rem;
    font-weight: 700;
}
.sz-pin__tip small {
    font-size: 0.68rem;
    color: rgba(255, 255, 255, 0.75);
}
.sz-pin.is-active .sz-pin__tip,
.sz-pin:focus-visible .sz-pin__tip {
    opacity: 1;
    transform: translateX(-50%) translateY(-3px);
}

.sz-chip-map {
    position: absolute;
    transform: translate(-50%, -50%);
    z-index: 4;
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.2;
    white-space: nowrap;
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: 12px;
    padding: 0.3rem 0.7rem;
    box-shadow: var(--sz-shadow-sm);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease, opacity 0.25s ease;
}
.sz-chip-map.is-active {
    z-index: 6;
    border-color: var(--sz-primary);
    box-shadow: var(--sz-shadow);
    transform: translate(-50%, -50%) scale(1.12);
}
.sz-chip-map__city {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-chip-map__price {
    font-size: 0.86rem;
    font-weight: 800;
    color: var(--sz-primary);
}
/* The delay is only shown for the city being hovered: at rest the chips have
   to stay small enough not to collide with each other. */
.sz-chip-map__delay {
    display: none;
    font-size: 0.6rem;
    font-weight: 600;
    color: var(--sz-muted);
}
.sz-chip-map.is-active .sz-chip-map__delay {
    display: block;
}

/* Hovering a city empties the map of everything else. */
.sz-pin.is-dimmed,
.sz-chip-map.is-dimmed {
    opacity: 0;
    pointer-events: none;
}
.sz-pin,
.sz-coverage__leaders line {
    transition: opacity 0.25s ease;
}
.sz-coverage__leaders line.is-dimmed {
    opacity: 0;
}

.sz-coverage__panel {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}
.sz-coverage__stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.9rem;
}
.sz-cstat {
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: var(--sz-radius-sm);
    padding: 1rem 0.9rem;
    text-align: center;
    box-shadow: var(--sz-shadow-sm);
}
.sz-cstat strong {
    display: block;
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--sz-primary);
    line-height: 1.1;
}
.sz-cstat span {
    font-size: 0.78rem;
    color: var(--sz-muted);
}
.sz-coverage__block h3 {
    margin: 0 0 1rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
}
.sz-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    background: #fff;
    color: var(--sz-slate);
    border: 1px solid var(--sz-border);
    transition: transform 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}
.sz-chip svg {
    color: var(--sz-accent-dark);
}
.sz-chip b {
    font-weight: 800;
    color: var(--sz-primary);
}
.sz-chip.is-active {
    transform: translateY(-2px);
    border-color: rgba(29, 78, 216, 0.35);
    color: var(--sz-dark);
}

@keyframes sz-ring {
    0% { transform: translate(-50%, -50%) scale(1); opacity: 0.55; }
    100% { transform: translate(-50%, -50%) scale(3.4); opacity: 0; }
}

@media (max-width: 1100px) {
    /* The chips keep their pixel size as the map shrinks, so scale them down. */
    .sz-chip-map {
        padding: 0.22rem 0.5rem;
        border-radius: 10px;
    }
    .sz-chip-map__city {
        font-size: 0.62rem;
    }
    .sz-chip-map__price {
        font-size: 0.74rem;
    }
}
@media (max-width: 640px) {
    /* Below this the map is too narrow for chips; the list still has prices. */
    .sz-chip-map,
    .sz-coverage__leaders {
        display: none;
    }
}
@media (max-width: 992px) {
    .sz-coverage__grid {
        grid-template-columns: 1fr;
        gap: 2.5rem;
    }
    .sz-coverage__map {
        max-width: 480px;
        margin: 0 auto;
        width: 100%;
    }
}
@media (max-width: 620px) {
    .sz-section {
        padding: 4rem 1.1rem;
    }
    .sz-coverage__stats {
        gap: 0.6rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .sz-pin__ring {
        animation: none;
    }
}
</style>
