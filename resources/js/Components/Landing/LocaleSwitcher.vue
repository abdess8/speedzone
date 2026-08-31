<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useLandingLocale } from '@/Components/Landing/i18n';

defineProps({
    block: { type: Boolean, default: false },
});

const { locale, locales, current, t, setLocale } = useLandingLocale();

const open = ref(false);
const root = ref(null);

const choose = (code) => {
    setLocale(code);
    open.value = false;
};

const onDocumentClick = (event) => {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="sz-lang" :class="{ 'sz-lang--block': block }">
        <button
            type="button"
            class="sz-lang__btn"
            :aria-label="t('locale.label')"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open = !open"
        >
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7" />
                <path
                    d="M3 12h18M12 3c2.4 2.6 2.4 15.4 0 18M12 3c-2.4 2.6-2.4 15.4 0 18"
                    stroke="currentColor"
                    stroke-width="1.7"
                />
            </svg>
            <span class="sz-lang__code">{{ current.short }}</span>
            <svg
                class="sz-lang__caret"
                :class="{ 'is-open': open }"
                width="12"
                height="12"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
            >
                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>

        <ul v-show="open" class="sz-lang__menu" role="listbox">
            <li v-for="option in locales" :key="option.code">
                <button
                    type="button"
                    class="sz-lang__option"
                    :class="{ 'is-active': option.code === locale }"
                    role="option"
                    :aria-selected="option.code === locale"
                    :lang="option.code"
                    @click="choose(option.code)"
                >
                    <span>{{ option.label }}</span>
                    <svg v-if="option.code === locale" width="14" height="14" viewBox="0 0 24 24" fill="none">
                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.sz-lang {
    position: relative;
}
.sz-lang__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid var(--sz-border);
    background: rgba(255, 255, 255, 0.7);
    color: var(--sz-dark);
    font-family: var(--sz-font);
    font-weight: 700;
    font-size: 0.85rem;
    padding: 0.5rem 0.7rem;
    border-radius: 999px;
    cursor: pointer;
    transition: border-color 0.2s ease, color 0.2s ease, background 0.2s ease;
}
.sz-lang__btn:hover {
    color: var(--sz-primary);
    border-color: rgba(29, 78, 216, 0.35);
}
.sz-lang__code {
    letter-spacing: 0.02em;
}
.sz-lang__caret {
    transition: transform 0.2s ease;
}
.sz-lang__caret.is-open {
    transform: rotate(180deg);
}

.sz-lang__menu {
    position: absolute;
    top: calc(100% + 0.5rem);
    inset-inline-end: 0;
    z-index: 20;
    min-width: 160px;
    list-style: none;
    margin: 0;
    padding: 0.35rem;
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: 14px;
    box-shadow: var(--sz-shadow-lg);
}
.sz-lang__option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    border: none;
    background: transparent;
    font-family: var(--sz-font);
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--sz-slate);
    text-align: start;
    padding: 0.55rem 0.7rem;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.sz-lang__option:hover {
    background: var(--sz-bg-soft);
    color: var(--sz-dark);
}
.sz-lang__option.is-active {
    color: var(--sz-primary);
    background: rgba(29, 78, 216, 0.08);
}

.sz-lang--block .sz-lang__btn {
    width: 100%;
    justify-content: center;
}
.sz-lang--block .sz-lang__menu {
    inset-inline: 0;
}
</style>
