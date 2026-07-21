<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import LandingButton from '@/Components/Landing/LandingButton.vue';

defineProps({
    authenticated: { type: Boolean, default: false },
});

const scrolled = ref(false);
const mobileOpen = ref(false);

const links = [
    { label: 'Accueil', href: '#accueil' },
    { label: 'Services', href: '#services' },
    { label: 'Plateforme', href: '#plateforme' },
    { label: 'Zones couvertes', href: '#zones' },
    { label: 'Tarifs', href: '#tarifs' },
    { label: 'À propos', href: '#apropos' },
    { label: 'Contact', href: '#contact' },
];

const onScroll = () => {
    scrolled.value = window.scrollY > 24;
};

const closeMobile = () => {
    mobileOpen.value = false;
};

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <header class="sz-nav" :class="{ 'sz-nav--scrolled': scrolled }">
        <div class="sz-nav__inner">
            <a href="#accueil" class="sz-nav__brand" @click="closeMobile">
                <span class="sz-nav__logo" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                        <path d="M3 13l2-6h9l4 4h3v5h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="7.5" cy="17.5" r="2" stroke="currentColor" stroke-width="2" />
                        <circle cx="17.5" cy="17.5" r="2" stroke="currentColor" stroke-width="2" />
                        <path d="M2 10h6" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </span>
                <span class="sz-nav__name">Speed<span>Zone</span></span>
            </a>

            <nav class="sz-nav__menu" :class="{ 'is-open': mobileOpen }">
                <a
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="sz-nav__link"
                    @click="closeMobile"
                >
                    {{ link.label }}
                </a>

                <div class="sz-nav__mobile-cta">
                    <LandingButton v-if="authenticated" href="/dashboard" variant="primary" size="sm" block>
                        Tableau de bord
                    </LandingButton>
                    <template v-else>
                        <LandingButton href="/login" variant="outline" size="sm" block>Connexion</LandingButton>
                        <LandingButton href="/register" variant="primary" size="sm" block>Créer un compte</LandingButton>
                    </template>
                </div>
            </nav>

            <div class="sz-nav__actions">
                <LandingButton v-if="authenticated" href="/dashboard" variant="primary" size="sm">
                    Tableau de bord
                </LandingButton>
                <template v-else>
                    <Link href="/login" class="sz-nav__signin">Connexion</Link>
                    <LandingButton href="/register" variant="primary" size="sm">Créer un compte</LandingButton>
                </template>
            </div>

            <button
                class="sz-nav__burger"
                :class="{ 'is-open': mobileOpen }"
                type="button"
                aria-label="Menu"
                @click="mobileOpen = !mobileOpen"
            >
                <span></span><span></span><span></span>
            </button>
        </div>
    </header>
</template>

<style scoped>
.sz-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    transition: background 0.35s ease, box-shadow 0.35s ease, backdrop-filter 0.35s ease;
    background: transparent;
}
.sz-nav--scrolled {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: saturate(180%) blur(14px);
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.06), 0 8px 30px rgba(15, 23, 42, 0.06);
}

.sz-nav__inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.1rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.sz-nav__brand {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    text-decoration: none;
    color: var(--sz-dark);
    font-weight: 800;
}
.sz-nav__logo {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: var(--sz-gradient);
    color: #fff;
    box-shadow: var(--sz-shadow-primary);
}
.sz-nav__name {
    font-size: 1.3rem;
    letter-spacing: -0.02em;
}
.sz-nav__name span {
    color: var(--sz-primary);
}
.sz-nav--scrolled .sz-nav__name {
    color: var(--sz-dark);
}

.sz-nav__menu {
    display: flex;
    align-items: center;
    gap: 1.9rem;
}
.sz-nav__link {
    position: relative;
    text-decoration: none;
    color: var(--sz-slate);
    font-weight: 600;
    font-size: 0.95rem;
    transition: color 0.2s ease;
}
.sz-nav__link::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -6px;
    width: 0;
    height: 2px;
    border-radius: 2px;
    background: var(--sz-primary);
    transition: width 0.25s ease;
}
.sz-nav__link:hover {
    color: var(--sz-primary);
}
.sz-nav__link:hover::after {
    width: 100%;
}

.sz-nav__actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.sz-nav__signin {
    text-decoration: none;
    color: var(--sz-dark);
    font-weight: 700;
    font-size: 0.95rem;
    transition: color 0.2s ease;
}
.sz-nav__signin:hover {
    color: var(--sz-primary);
}

.sz-nav__mobile-cta {
    display: none;
}

.sz-nav__burger {
    display: none;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    width: 44px;
    height: 44px;
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 0;
}
.sz-nav__burger span {
    display: block;
    height: 2.5px;
    width: 24px;
    margin: 0 auto;
    border-radius: 2px;
    background: var(--sz-dark);
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.sz-nav__burger.is-open span:nth-child(1) {
    transform: translateY(7.5px) rotate(45deg);
}
.sz-nav__burger.is-open span:nth-child(2) {
    opacity: 0;
}
.sz-nav__burger.is-open span:nth-child(3) {
    transform: translateY(-7.5px) rotate(-45deg);
}

@media (max-width: 992px) {
    .sz-nav__actions {
        display: none;
    }
    .sz-nav__burger {
        display: flex;
    }
    .sz-nav__menu {
        position: absolute;
        top: 100%;
        left: 1rem;
        right: 1rem;
        flex-direction: column;
        align-items: stretch;
        gap: 0.4rem;
        padding: 1rem;
        background: #fff;
        border-radius: var(--sz-radius-sm);
        box-shadow: var(--sz-shadow-lg);
        border: 1px solid var(--sz-border);
        opacity: 0;
        transform: translateY(-12px);
        pointer-events: none;
        transition: opacity 0.25s ease, transform 0.25s ease;
    }
    .sz-nav__menu.is-open {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }
    .sz-nav__link {
        padding: 0.6rem 0.5rem;
        border-radius: 10px;
    }
    .sz-nav__link:hover {
        background: var(--sz-bg-soft);
    }
    .sz-nav__mobile-cta {
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        margin-top: 0.6rem;
        padding-top: 0.8rem;
        border-top: 1px solid var(--sz-border);
    }
}
</style>
