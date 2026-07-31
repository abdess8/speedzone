<script setup>
const year = new Date().getFullYear();

const columns = [
    {
        title: 'Navigation',
        links: [
            { label: 'Accueil', href: '#accueil' },
            { label: 'Services', href: '#services' },
            { label: 'Plateforme', href: '#plateforme' },
            { label: 'Zones couvertes', href: '#zones' },
            { label: 'À propos', href: '#apropos' },
        ],
    },
    {
        title: 'Services',
        links: [
            { label: 'Ramassage', href: '#services' },
            { label: 'Livraison Express', href: '#services' },
            { label: 'Livraison Nationale', href: '#services' },
            { label: 'Paiement à la livraison', href: '#services' },
            { label: 'Gestion des retours', href: '#services' },
        ],
    },
    {
        title: 'Support',
        links: [
            { label: 'Centre d\'aide', href: '#contact' },
            { label: 'FAQ', href: '#contact' },
            { label: 'Conditions générales', href: '#contact' },
            { label: 'Confidentialité', href: '#contact' },
            { label: 'Nous contacter', href: '#contact' },
        ],
    },
];

const socials = [
    { label: 'Facebook', icon: 'facebook' },
    { label: 'Instagram', icon: 'instagram' },
    { label: 'LinkedIn', icon: 'linkedin' },
    { label: 'X', icon: 'x' },
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
                    <p class="sz-footer__tagline">Livraison rapide. Confiance assurée.</p>
                    <p class="sz-footer__desc">
                        La plateforme de livraison la plus fiable au Maroc, de Rabat au Gharb.
                    </p>
                    <div class="sz-footer__socials">
                        <a v-for="s in socials" :key="s.icon" href="#" :aria-label="s.label" class="sz-social">
                            <svg v-if="s.icon === 'facebook'" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                <path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H7v3h3v6h3v-6h3l1-3h-4v-2c0-.6.4-1 1-1z" />
                            </svg>
                            <svg v-else-if="s.icon === 'instagram'" viewBox="0 0 24 24" width="18" height="18" fill="none">
                                <rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="1.8" />
                                <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8" />
                                <circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" />
                            </svg>
                            <svg v-else-if="s.icon === 'linkedin'" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                <path d="M6.5 8A1.5 1.5 0 1 0 6.5 5a1.5 1.5 0 0 0 0 3zM5 10h3v9H5zM10 10h3v1.3c.5-.8 1.5-1.5 3-1.5 2.3 0 3 1.5 3 4V19h-3v-4c0-1.2-.4-2-1.5-2S13 14 13 15v4h-3z" />
                            </svg>
                            <svg v-else viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M17 3h3l-7 8 8 10h-6l-5-6-5 6H2l8-9L2 3h6l4 5z" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div v-for="col in columns" :key="col.title" class="sz-footer__col">
                    <h4>{{ col.title }}</h4>
                    <ul>
                        <li v-for="link in col.links" :key="link.label">
                            <a :href="link.href">{{ link.label }}</a>
                        </li>
                    </ul>
                </div>

                <div class="sz-footer__col sz-footer__contact">
                    <h4>Contact</h4>
                    <ul>
                        <li>
                            <span class="sz-footer__ci">📍</span>
                            <span>Rabat, Maroc</span>
                        </li>
                        <li>
                            <span class="sz-footer__ci">📞</span>
                            <a href="tel:+212500000000">+212 5 00 00 00 00</a>
                        </li>
                        <li>
                            <span class="sz-footer__ci">✉️</span>
                            <a href="mailto:contact@speedzone.ma">contact@speedzone.ma</a>
                        </li>
                        <li>
                            <span class="sz-footer__ci">🌐</span>
                            <span>www.speedzone.ma</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="sz-footer__bottom">
                <p>© {{ year }} SpeedZone. Tous droits réservés.</p>
                <div class="sz-footer__legal">
                    <a href="#contact">Conditions générales</a>
                    <a href="#contact">Politique de confidentialité</a>
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
    width: 38px;
    height: 38px;
    border-radius: 11px;
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
    align-items: center;
    gap: 0.55rem;
    font-size: 0.9rem;
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
