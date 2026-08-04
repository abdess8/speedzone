<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'hero' }, // hero | compact
});

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
    <form class="owl-track" :class="`owl-track--${variant}`" @submit.prevent="submit">
        <div class="owl-track__field">
            <span class="owl-track__icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                    <path d="M3.3 7L12 12l8.7-5M12 22V12" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                </svg>
            </span>
            <input
                v-model="trackingNumber"
                type="text"
                class="owl-track__input"
                placeholder="Entrez votre numéro de suivi"
                aria-label="Numéro de suivi"
                autocomplete="off"
            />
        </div>
        <button type="submit" class="owl-track__btn" :disabled="!canSubmit || loading">
            <span v-if="!loading">Suivre</span>
            <span v-else class="owl-track__spinner" aria-hidden="true"></span>
        </button>
    </form>
</template>

<style scoped>
.owl-track {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: 999px;
    padding: 0.4rem 0.4rem 0.4rem 0.5rem;
    box-shadow: var(--owl-shadow);
    max-width: 460px;
}
.owl-track__field {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex: 1;
    padding-left: 0.6rem;
}
.owl-track__icon {
    color: var(--owl-primary);
    display: inline-flex;
}
.owl-track__input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    font-family: var(--owl-font);
    font-size: 0.95rem;
    color: var(--owl-dark);
    min-width: 0;
}
.owl-track__input::placeholder {
    color: var(--owl-muted);
}
.owl-track__btn {
    border: none;
    cursor: pointer;
    background: var(--owl-gradient);
    color: #fff;
    font-family: var(--owl-font);
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
.owl-track__btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--owl-shadow-primary);
}
.owl-track__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.owl-track__spinner {
    width: 18px;
    height: 18px;
    border: 2.5px solid rgba(255, 255, 255, 0.5);
    border-top-color: #fff;
    border-radius: 50%;
    animation: owl-spin 0.7s linear infinite;
}
@keyframes owl-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 520px) {
    .owl-track {
        flex-direction: column;
        border-radius: var(--owl-radius-sm);
        padding: 0.7rem;
        gap: 0.7rem;
    }
    .owl-track__field {
        width: 100%;
        padding: 0.5rem 0.6rem;
    }
    .owl-track__btn {
        width: 100%;
    }
}
</style>
