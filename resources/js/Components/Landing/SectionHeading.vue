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
    <div class="owl-heading" :class="[`owl-heading--${align}`, { 'owl-heading--light': light }]" data-aos="fade-up">
        <span v-if="eyebrow" class="owl-heading__eyebrow">{{ eyebrow }}</span>
        <h2 class="owl-heading__title">{{ title }}</h2>
        <p v-if="subtitle" class="owl-heading__subtitle">{{ subtitle }}</p>
    </div>
</template>

<style scoped>
.owl-heading {
    max-width: 640px;
    margin-bottom: 3.2rem;
}
.owl-heading--center {
    margin-left: auto;
    margin-right: auto;
    text-align: center;
}
.owl-heading--left {
    text-align: left;
}
.owl-heading__eyebrow {
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
.owl-heading__title {
    margin: 0;
    font-size: clamp(1.8rem, 3.5vw, 2.5rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.15;
    color: var(--owl-dark);
}
.owl-heading__subtitle {
    margin: 1rem 0 0;
    font-size: 1.05rem;
    color: var(--owl-slate);
}
.owl-heading--light .owl-heading__title {
    color: #fff;
}
.owl-heading--light .owl-heading__subtitle {
    color: rgba(255, 255, 255, 0.82);
}
.owl-heading--light .owl-heading__eyebrow {
    color: #fff;
    background: rgba(255, 255, 255, 0.16);
}
</style>
