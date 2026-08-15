<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useLandingLocale } from '@/Components/Landing/i18n';

const { t } = useLandingLocale();

const year = new Date().getFullYear();

const company = computed(() => usePage().props.company ?? {});

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
    {
        title: 'footer.columns.support',
        links: [
            { label: 'footer.support.help', href: '#contact' },
            { label: 'footer.support.faq', href: '#contact' },
            { label: 'footer.support.terms', href: '#contact' },
            { label: 'footer.support.privacy', href: '#contact' },
            { label: 'footer.support.contact', href: '#contact' },
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
                            width="180"
                            height="54"
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

            <div class="sz-footer__bottom">
                <p>{{ t('footer.rights', { year }) }}</p>
                <div class="sz-footer__legal">
                    <a href="#contact">{{ t('footer.terms') }}</a>
                    <a href="#contact">{{ t('footer.privacy') }}</a>
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
    grid-template-columns: 1.6fr 1fr 1fr 1fr 1.2fr;
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
    height: 54px;
    max-width: 180px;
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
@media (max-width: 560px) {
    .sz-footer__top {
        grid-template-columns: 1fr;
    }
    .sz-footer__bottom {
        flex-direction: column;
        text-align: center;
    }
}
</style>
