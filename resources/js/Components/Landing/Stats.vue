<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { CountTo } from 'vue3-count-to';
import { useLandingLocale } from '@/Components/Landing/i18n';

const { t } = useLandingLocale();

const visible = ref(false);
const root = ref(null);
let observer = null;

const stats = [
    { key: 'parcels', end: 15000, suffix: '+', decimals: 0, separator: ' ' },
    { key: 'success', end: 99.2, suffix: '%', decimals: 1, separator: '' },
    { key: 'companies', end: 500, suffix: '+', decimals: 0, separator: '' },
    { key: 'tracking', text: '24/7' },
    { key: 'satisfaction', end: 4.9, suffix: '/5', decimals: 1, separator: '' },
];

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    visible.value = true;
                    observer.disconnect();
                }
            });
        },
        { threshold: 0.35 }
    );
    if (root.value) observer.observe(root.value);
});

onBeforeUnmount(() => {
    if (observer) observer.disconnect();
});
</script>

<template>
    <section ref="root" class="sz-stats">
        <div class="sz-stats__bg" aria-hidden="true"></div>
        <div class="sz-container sz-stats__grid">
            <div
                v-for="(stat, index) in stats"
                :key="stat.key"
                class="sz-stat"
                data-aos="fade-up"
                :data-aos-delay="index * 45"
            >
                <div class="sz-stat__value">
                    <template v-if="stat.text">{{ stat.text }}</template>
                    <CountTo
                        v-else-if="visible"
                        :start-val="0"
                        :end-val="stat.end"
                        :decimals="stat.decimals"
                        :duration="2200"
                        :separator="stat.separator"
                        :suffix="stat.suffix"
                    />
                    <span v-else>0{{ stat.suffix }}</span>
                </div>
                <p class="sz-stat__label">{{ t(`stats.${stat.key}`) }}</p>
            </div>
        </div>
    </section>
</template>

<style scoped>
.sz-stats {
    position: relative;
    padding: 4rem 1.5rem;
    background: var(--sz-dark);
    overflow: hidden;
}
.sz-stats__bg {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 15% 20%, rgba(37, 99, 235, 0.4), transparent 45%),
        radial-gradient(circle at 85% 80%, rgba(16, 185, 129, 0.28), transparent 45%);
}
.sz-container {
    position: relative;
    max-width: 1200px;
    margin: 0 auto;
}
.sz-stats__grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.5rem;
}
.sz-stat {
    text-align: center;
    padding: 1rem 0.5rem;
    position: relative;
}
.sz-stat:not(:last-child)::after {
    content: '';
    position: absolute;
    right: 0;
    top: 20%;
    height: 60%;
    width: 1px;
    background: rgba(255, 255, 255, 0.12);
}
.sz-stat__value {
    font-size: clamp(1.9rem, 3.5vw, 2.9rem);
    font-weight: 800;
    letter-spacing: -0.02em;
    background: linear-gradient(180deg, #fff, #cbd5e1);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    line-height: 1;
}
.sz-stat__label {
    margin: 0.7rem 0 0;
    font-size: 0.92rem;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 500;
}

@media (max-width: 860px) {
    .sz-stats__grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem 1rem;
    }
    .sz-stat:not(:last-child)::after {
        display: none;
    }
    .sz-stat:nth-child(5) {
        grid-column: 1 / -1;
    }
}
</style>
