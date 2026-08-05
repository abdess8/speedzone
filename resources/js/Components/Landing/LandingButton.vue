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
        class="sz-btn"
        :class="[`sz-btn--${variant}`, `sz-btn--${size}`, { 'sz-btn--block': block }]"
    >
        <span class="sz-btn__icon sz-btn__icon--left" v-if="$slots.iconLeft">
            <slot name="iconLeft" />
        </span>
        <span class="sz-btn__label"><slot /></span>
        <span class="sz-btn__icon sz-btn__icon--right" v-if="$slots.iconRight">
            <slot name="iconRight" />
        </span>
    </component>
</template>

<style scoped>
.sz-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    border: 1px solid transparent;
    border-radius: 999px;
    font-family: var(--sz-font);
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

.sz-btn--sm {
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
}
.sz-btn--md {
    padding: 0.85rem 1.6rem;
}
.sz-btn--lg {
    padding: 1.05rem 2rem;
    font-size: 1rem;
}
.sz-btn--block {
    width: 100%;
}

.sz-btn__icon {
    display: inline-flex;
    font-size: 1.15em;
}

.sz-btn--primary {
    background: var(--sz-gradient);
    color: #fff;
    box-shadow: var(--sz-shadow-primary);
}
.sz-btn--primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 24px 48px rgba(29, 78, 216, 0.36);
    color: #fff;
}

.sz-btn--accent {
    background: var(--sz-accent);
    color: #fff;
    box-shadow: 0 16px 32px rgba(16, 185, 129, 0.28);
}
.sz-btn--accent:hover {
    transform: translateY(-3px);
    background: var(--sz-accent-dark);
    color: #fff;
}

.sz-btn--outline {
    background: transparent;
    color: var(--sz-primary);
    border-color: rgba(29, 78, 216, 0.35);
}
.sz-btn--outline:hover {
    transform: translateY(-3px);
    border-color: var(--sz-primary);
    background: rgba(29, 78, 216, 0.06);
    color: var(--sz-primary);
}

.sz-btn--light {
    background: #fff;
    color: var(--sz-dark);
    box-shadow: var(--sz-shadow);
    border-color: var(--sz-border);
}
.sz-btn--light:hover {
    transform: translateY(-3px);
    color: var(--sz-primary);
    border-color: rgba(29, 78, 216, 0.3);
}

.sz-btn--ghost {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(6px);
}
.sz-btn--ghost:hover {
    transform: translateY(-3px);
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

@media (prefers-reduced-motion: reduce) {
    .sz-btn:hover {
        transform: none;
    }
}
</style>
