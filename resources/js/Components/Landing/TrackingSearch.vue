<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useLandingLocale } from '@/Components/Landing/i18n';

const props = defineProps({
    variant: { type: String, default: 'hero' }, // hero | compact
});

const { t } = useLandingLocale();

const trackingNumber = ref('');
const loading = ref(false);

const canSubmit = computed(() => trackingNumber.value.trim().length > 0);

const submit = () => {
    const value = trackingNumber.value.trim();
    if (!value) return;

    loading.value = true;
    router.visit(`/tracking/${encodeURIComponent(value)}`, {
        onFinish: () => {
            loading.value = false;
        },
    });
};
</script>

<template>
    <form class="sz-track" :class="`sz-track--${variant}`" @submit.prevent="submit">
        <div class="sz-track__field">
            <span class="sz-track__icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                    <path d="M3.3 7L12 12l8.7-5M12 22V12" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                </svg>
            </span>
            <input
                v-model="trackingNumber"
                type="text"
                class="sz-track__input"
                :placeholder="t('tracking.placeholder')"
                :aria-label="t('tracking.inputLabel')"
                autocomplete="off"
            />
        </div>
        <button type="submit" class="sz-track__btn" :disabled="!canSubmit || loading">
            <span v-if="!loading">{{ t('tracking.submit') }}</span>
            <span v-else class="sz-track__spinner" aria-hidden="true"></span>
        </button>
    </form>
</template>

<style scoped>
.sz-track {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: 999px;
    padding: 0.4rem 0.4rem 0.4rem 0.5rem;
    box-shadow: var(--sz-shadow);
    max-width: 460px;
}
.sz-track__field {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex: 1;
    padding-left: 0.6rem;
}
.sz-track__icon {
    color: var(--sz-primary);
    display: inline-flex;
}
.sz-track__input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    font-family: var(--sz-font);
    font-size: 0.95rem;
    color: var(--sz-dark);
    min-width: 0;
}
.sz-track__input::placeholder {
    color: var(--sz-muted);
}
.sz-track__btn {
    border: none;
    cursor: pointer;
    background: var(--sz-gradient);
    color: #fff;
    font-family: var(--sz-font);
    font-weight: 700;
    font-size: 0.95rem;
    padding: 0.75rem 1.6rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 108px;
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}
.sz-track__btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--sz-shadow-primary);
}
.sz-track__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.sz-track__spinner {
    width: 18px;
    height: 18px;
    border: 2.5px solid rgba(255, 255, 255, 0.5);
    border-top-color: #fff;
    border-radius: 50%;
    animation: sz-spin 0.7s linear infinite;
}
@keyframes sz-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 520px) {
    .sz-track {
        flex-direction: column;
        border-radius: var(--sz-radius-sm);
        padding: 0.7rem;
        gap: 0.7rem;
    }
    .sz-track__field {
        width: 100%;
        padding: 0.5rem 0.6rem;
    }
    .sz-track__btn {
        width: 100%;
    }
}
</style>
