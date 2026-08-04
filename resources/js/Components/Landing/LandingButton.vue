<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: { type: String, default: null },
    variant: { type: String, default: 'primary' }, // primary | accent | outline | light | ghost
    size: { type: String, default: 'md' }, // sm | md | lg
    block: { type: Boolean, default: false },
    external: { type: Boolean, default: false },
});

const componentType = computed(() => {
    if (!props.href) return 'button';
    return props.external ? 'a' : Link;
});

const bindings = computed(() => {
    if (!props.href) return {};
    return props.external
        ? { href: props.href }
        : { href: props.href };
});
</script>

<template>
    <component
        :is="componentType"
        v-bind="bindings"
        class="owl-btn"
        :class="[`owl-btn--${variant}`, `owl-btn--${size}`, { 'owl-btn--block': block }]"
    >
        <span class="owl-btn__icon owl-btn__icon--left" v-if="$slots.iconLeft">
            <slot name="iconLeft" />
        </span>
        <span class="owl-btn__label"><slot /></span>
        <span class="owl-btn__icon owl-btn__icon--right" v-if="$slots.iconRight">
            <slot name="iconRight" />
        </span>
    </component>
</template>

<style scoped>
.owl-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    border: 1px solid transparent;
    border-radius: 999px;
    font-family: var(--owl-font);
    font-weight: 700;
    font-size: 0.95rem;
    line-height: 1;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease,
        color 0.25s ease, border-color 0.25s ease;
    will-change: transform;
}

.owl-btn--sm {
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
}
.owl-btn--md {
    padding: 0.85rem 1.6rem;
}
.owl-btn--lg {
    padding: 1.05rem 2rem;
    font-size: 1rem;
}
.owl-btn--block {
    width: 100%;
}

.owl-btn__icon {
    display: inline-flex;
    font-size: 1.15em;
}

.owl-btn--primary {
    background: var(--owl-gradient);
    color: #fff;
    box-shadow: var(--owl-shadow-primary);
}
.owl-btn--primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 24px 48px rgba(13, 74, 157, 0.36);
    color: #fff;
}

.owl-btn--accent {
    background: var(--owl-accent);
    color: #fff;
    box-shadow: 0 16px 32px rgba(241, 90, 36, 0.28);
}
.owl-btn--accent:hover {
    transform: translateY(-3px);
    background: var(--owl-accent-dark);
    color: #fff;
}

.owl-btn--outline {
    background: transparent;
    color: var(--owl-primary);
    border-color: rgba(13, 74, 157, 0.35);
}
.owl-btn--outline:hover {
    transform: translateY(-3px);
    border-color: var(--owl-primary);
    background: rgba(13, 74, 157, 0.06);
    color: var(--owl-primary);
}

.owl-btn--light {
    background: #fff;
    color: var(--owl-dark);
    box-shadow: var(--owl-shadow);
    border-color: var(--owl-border);
}
.owl-btn--light:hover {
    transform: translateY(-3px);
    color: var(--owl-primary);
    border-color: rgba(13, 74, 157, 0.3);
}

.owl-btn--ghost {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(6px);
}
.owl-btn--ghost:hover {
    transform: translateY(-3px);
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

@media (prefers-reduced-motion: reduce) {
    .owl-btn:hover {
        transform: none;
    }
}
</style>
