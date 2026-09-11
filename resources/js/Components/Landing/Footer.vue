<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useLandingLocale } from '@/Components/Landing/i18n';
import MadeWithLove from '@/Components/MadeWithLove.vue';

const { t } = useLandingLocale();

const year = new Date().getFullYear();

const page = usePage();
const company = computed(() => page.props.company ?? {});
const authenticated = computed(() => Boolean(page.props.authenticated || page.props.auth?.user));

const whatsappUrl = computed(() => {
    const digits = String(company.value.phone_link ?? '').replace(/\D/g, '');

    return digits ? `https://wa.me/${digits}` : null;
});

const joinHref = computed(() => (authenticated.value ? '/dashboard' : '/register'));
const joinLabel = computed(() => (authenticated.value ? t('nav.dashboard') : t('footer.join')));

const columns = [
    {
        title: 'footer.columns.navigation',
        links: [
            { label: 'nav.home', href: '#accueil' },
            { label: 'nav.services', href: '#services' },
            { label: 'nav.platform', href: '#plateforme' },
            { label: 'nav.zones', href: '#zones' },
            { label: 'nav.pricing', href: '#tarifs' },
            { label: 'nav.about', href: '#apropos' },
        ],
    },
    {
        title: 'footer.columns.services',
        links: [
            { label: 'services.items.pickup.title', href: '#services' },
            { label: 'services.items.express.title', href: '#services' },
            { label: 'services.items.national.title', href: '#services' },
            { label: 'services.items.cod.title', href: '#services' },
            { label: 'services.items.returns.title', href: '#services' },
        ],
    },
];
</script>

<template>
    <footer id="contact" class="sz-footer">
        <div class="sz-container">
            <div class="sz-footer__top">
                <div class="sz-footer__brand-col">
                    <a href="#accueil" class="sz-footer__brand">
                        <img
                            src="@assets/images/logo-brand-full.png"
                            alt="SpeedZone Express"
                            class="sz-footer__logo"
                            width="196"
                            height="50"
                        />
                    </a>
                    <p class="sz-footer__tagline">{{ t('footer.tagline') }}</p>
                    <p class="sz-footer__desc">{{ t('footer.description') }}</p>
                    <div v-if="company.instagram" class="sz-footer__socials">
                        <a
                            :href="company.instagram"
                            class="sz-social"
                            target="_blank"
                            rel="noopener noreferrer"
                            :aria-label="t('footer.follow')"
                        >
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
                                <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.8" />
                                <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8" />
                                <circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" />
                            </svg>
                            <span>Instagram</span>
                        </a>
                    </div>
                </div>

                <div v-for="col in columns" :key="col.title" class="sz-footer__col">
                    <h4>{{ t(col.title) }}</h4>
                    <ul>
                        <li v-for="link in col.links" :key="link.label">
                            <a :href="link.href">{{ t(link.label) }}</a>
                        </li>
                    </ul>
                </div>

                <div class="sz-footer__col sz-footer__contact">
                    <h4>{{ t('footer.columns.contact') }}</h4>
                    <ul>
                        <li>
                            <span class="sz-footer__ci">📍</span>
                            <span>{{ company.address }}</span>
                        </li>
                        <li>
                            <span class="sz-footer__ci">📞</span>
                            <a :href="`tel:${company.phone_link}`" dir="ltr">{{ company.phone }}</a>
                        </li>
                        <li>
                            <span class="sz-footer__ci">✉️</span>
                            <a :href="`mailto:${company.email}`" dir="ltr">{{ company.email }}</a>
                        </li>
                        <li v-if="company.instagram">
                            <span class="sz-footer__ci">📸</span>
                            <a :href="company.instagram" target="_blank" rel="noopener noreferrer">
                                {{ t('footer.follow') }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="sz-footer__connect">
                <h2 class="sz-footer__connect-title">{{ t('footer.stayInTouch') }}</h2>
                <p class="sz-footer__connect-text">{{ t('footer.stayInTouchText') }}</p>

                <div class="sz-footer__ctas">
                    <a
                        v-if="whatsappUrl"
                        class="sz-footer__cta sz-footer__cta--ghost"
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                            <path d="M19.05 4.91A9.82 9.82 0 0 0 12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.86 9.86 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.91-7.02zm-7.01 15.24h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.42 5.83c0 4.54-3.7 8.23-8.25 8.23zm4.52-6.16c-.25-.12-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.39-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.76-1.84-.2-.48-.4-.42-.56-.42h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74 1.49.64 1.88.7 2.56.59.39-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.14-1.18-.06-.10-.23-.17-.48-.29z" />
                        </svg>
                        {{ t('footer.whatsapp') }}
                    </a>
                    <Link class="sz-footer__cta sz-footer__cta--solid" :href="joinHref">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
                            <rect x="4" y="3.5" width="16" height="17" rx="2.2" stroke="currentColor" stroke-width="1.8" />
                            <path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        {{ joinLabel }}
                    </Link>
                </div>

                <div class="sz-footer__icons">
                    <a
                        v-if="whatsappUrl"
                        class="sz-footer__icon"
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="t('footer.whatsapp')"
                    >
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                            <path d="M19.05 4.91A9.82 9.82 0 0 0 12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.86 9.86 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.91-7.02zm-7.01 15.24h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.42 5.83c0 4.54-3.7 8.23-8.25 8.23z" />
                        </svg>
                    </a>
                    <a
                        v-if="company.instagram"
                        class="sz-footer__icon"
                        :href="company.instagram"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="t('footer.follow')"
                    >
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
                            <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.8" />
                            <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8" />
                            <circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" />
                        </svg>
                    </a>
                    <a
                        v-if="company.email"
                        class="sz-footer__icon"
                        :href="`mailto:${company.email}`"
                        :aria-label="company.email"
                    >
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none">
                            <rect x="3" y="5" width="18" height="14" rx="2.2" stroke="currentColor" stroke-width="1.8" />
                            <path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                </div>
            </div>

            <div class="sz-footer__bottom">
                <div class="sz-footer__headline">
                    <a href="#accueil" class="sz-footer__topbtn" :aria-label="t('footer.scrollTop')">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" aria-hidden="true">
                            <path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                    <p class="sz-footer__motto">{{ t('footer.tagline') }}</p>
                </div>
                <p>{{ t('footer.rights', { year }) }}</p>
                <MadeWithLove class="sz-footer__made" />
                <div class="sz-footer__legal">
                    <a href="#contact">{{ t('footer.privacy') }}</a>
                    <a href="#contact">{{ t('footer.terms') }}</a>
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.sz-footer {
    background: var(--sz-dark);
    color: rgba(255, 255, 255, 0.7);
    padding: 4.5rem 1.5rem 2rem;
}
.sz-container {
    max-width: 1200px;
    margin: 0 auto;
}
.sz-footer__top {
    display: grid;
    grid-template-columns: 1.8fr 1fr 1fr 1.3fr;
    gap: 2.5rem;
    padding-bottom: 3rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.sz-footer__brand {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    margin-bottom: 1rem;
}
.sz-footer__logo {
    display: block;
    width: auto;
    height: 50px;
    max-width: 220px;
    object-fit: contain;
}
.sz-footer__tagline {
    margin: 0 0 0.5rem;
    color: #fff;
    font-weight: 600;
}
.sz-footer__desc {
    margin: 0 0 1.3rem;
    font-size: 0.9rem;
    max-width: 280px;
}
.sz-footer__socials {
    display: flex;
    gap: 0.6rem;
}
.sz-social {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    height: 38px;
    padding: 0 0.9rem;
    border-radius: 11px;
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.25s ease, transform 0.25s ease;
}
.sz-social:hover {
    background: var(--sz-primary);
    transform: translateY(-3px);
    color: #fff;
}

.sz-footer__col h4 {
    margin: 0 0 1.1rem;
    color: #fff;
    font-size: 0.95rem;
    font-weight: 700;
}
.sz-footer__col ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}
.sz-footer__col a {
    color: rgba(255, 255, 255, 0.68);
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.2s ease;
}
.sz-footer__col a:hover {
    color: #fff;
}
.sz-footer__contact li {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    font-size: 0.9rem;
    line-height: 1.5;
}
.sz-footer__contact a,
.sz-footer__contact span:not(.sz-footer__ci) {
    overflow-wrap: anywhere;
}
.sz-footer__ci {
    font-size: 0.9rem;
}

.sz-footer__connect {
    display: none;
}

.sz-footer__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 1.8rem;
    flex-wrap: wrap;
}
.sz-footer__bottom p {
    margin: 0;
    font-size: 0.85rem;
}
.sz-footer__motto {
    display: none;
}
.sz-footer__topbtn {
    display: none;
}
.sz-footer__headline {
    display: contents;
}
.sz-footer__made {
    color: rgba(255, 255, 255, 0.68);
}
.sz-footer__made:hover {
    color: #fb7185;
}
.sz-footer__legal {
    display: flex;
    gap: 1.5rem;
}
.sz-footer__legal a {
    color: rgba(255, 255, 255, 0.68);
    text-decoration: none;
    font-size: 0.85rem;
}
.sz-footer__legal a:hover {
    color: #fff;
}

@media (max-width: 992px) {
    .sz-footer__top {
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }
    .sz-footer__brand-col {
        grid-column: 1 / -1;
    }
}
@media (max-width: 720px) {
    .sz-footer {
        padding: 3.2rem 1.25rem 1.6rem;
        background:
            radial-gradient(circle at 80% 0%, rgba(29, 78, 216, 0.32), transparent 42%),
            radial-gradient(circle at 10% 90%, rgba(37, 99, 235, 0.18), transparent 40%),
            var(--sz-dark);
    }
    .sz-footer__top {
        display: none;
    }
    .sz-footer__connect {
        display: block;
        text-align: center;
        padding-bottom: 2.2rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .sz-footer__connect-title {
        margin: 0;
        color: #fff;
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -0.03em;
    }
    .sz-footer__connect-text {
        margin: 0.7rem auto 1.5rem;
        max-width: 22rem;
        font-size: 0.95rem;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.72);
    }
    .sz-footer__ctas {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    .sz-footer__cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 48px;
        padding: 0.75rem 0.9rem;
        border-radius: 999px;
        font-size: 0.92rem;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
    }
    .sz-footer__cta--ghost {
        color: #fff;
        border: 1.5px solid rgba(255, 255, 255, 0.55);
        background: transparent;
    }
    .sz-footer__cta--ghost:hover {
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
    }
    .sz-footer__cta--solid {
        color: #fff;
        background: var(--sz-primary);
        border: 1.5px solid var(--sz-primary);
    }
    .sz-footer__cta--solid:hover {
        background: var(--sz-primary-dark);
        border-color: var(--sz-primary-dark);
        color: #fff;
    }
    .sz-footer__icons {
        display: flex;
        justify-content: center;
        gap: 1.15rem;
        margin-top: 1.7rem;
    }
    .sz-footer__icon {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        text-decoration: none;
        transition: color 0.2s ease, transform 0.2s ease;
    }
    .sz-footer__icon:hover {
        color: var(--sz-secondary);
        transform: translateY(-2px);
    }
    .sz-footer__bottom {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.55rem;
        padding-top: 1.5rem;
        width: 100%;
    }
    .sz-footer__headline {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) 42px;
        align-items: center;
        width: 100%;
        column-gap: 0.45rem;
    }
    .sz-footer__motto {
        display: block;
        grid-column: 2;
        margin: 0;
        color: #93c5fd;
        font-size: 0.95rem;
        font-weight: 800;
        line-height: 1.35;
        text-align: center;
    }
    .sz-footer__topbtn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        justify-self: start;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 1.5px solid rgba(255, 255, 255, 0.45);
        color: #fff;
        text-decoration: none;
    }
    .sz-footer__legal {
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.85rem 1.25rem;
    }
    .sz-footer__legal a {
        text-decoration: underline;
        text-underline-offset: 3px;
        font-size: 0.8rem;
    }
}
</style>
