<script setup>
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    align: { type: String, default: 'center' }, // center | left
    light: { type: Boolean, default: false },
});
</script>

<template>
    <div class="sz-heading" :class="[`sz-heading--${align}`, { 'sz-heading--light': light }]" data-aos="fade-up">
        <span v-if="eyebrow" class="sz-heading__eyebrow">{{ eyebrow }}</span>
        <h2 class="sz-heading__title">{{ title }}</h2>
        <p v-if="subtitle" class="sz-heading__subtitle">{{ subtitle }}</p>
    </div>
</template>

<style scoped>
.sz-heading {
    max-width: 640px;
    margin-bottom: 3.2rem;
}
.sz-heading--center {
    margin-left: auto;
    margin-right: auto;
    text-align: center;
}
.sz-heading--left {
    text-align: start;
}
.sz-heading__eyebrow {
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
.sz-heading__title {
    margin: 0;
    font-size: clamp(1.8rem, 3.5vw, 2.5rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.15;
    color: var(--sz-dark);
}
.sz-heading__subtitle {
    margin: 1rem 0 0;
    font-size: 1.05rem;
    color: var(--sz-slate);
}
.sz-heading--light .sz-heading__title {
    color: #fff;
}
.sz-heading--light .sz-heading__subtitle {
    color: rgba(255, 255, 255, 0.82);
}
.sz-heading--light .sz-heading__eyebrow {
    color: #fff;
    background: rgba(255, 255, 255, 0.16);
}
</style>
