<script setup>
import SectionHeading from '@/Components/Landing/SectionHeading.vue';

const services = [
    {
        title: 'Ramassage',
        description:
            "Nos livreurs récupèrent vos colis directement dans votre boutique ou entrepôt, selon votre planning.",
        icon: 'pickup',
        color: 'blue',
    },
    {
        title: 'Livraison Express',
        description:
            'Livraison le jour même à Rabat, Salé et Témara pour satisfaire vos clients les plus exigeants.',
        icon: 'express',
        color: 'green',
    },
    {
        title: 'Livraison Nationale',
        description:
            'Expédiez partout au Maroc grâce à notre réseau logistique en pleine expansion et fiable.',
        icon: 'national',
        color: 'violet',
    },
    {
        title: 'Paiement à la livraison',
        description:
            'Encaissement COD sécurisé et reversement rapide, avec un suivi précis de chaque transaction.',
        icon: 'cod',
        color: 'amber',
    },
    {
        title: 'Gestion des retours',
        description:
            'Traitement automatique des retours clients de façon simple, tracée et sans friction.',
        icon: 'returns',
        color: 'rose',
    },
    {
        title: 'Plateforme Web',
        description:
            'Pilotez toute votre logistique depuis une seule plateforme moderne et intuitive.',
        icon: 'platform',
        color: 'blue',
    },
];
</script>

<template>
    <section id="services" class="owl-section owl-services">
        <div class="owl-container">
            <SectionHeading
                eyebrow="Nos services"
                title="Tout ce qu'il vous faut pour livrer en toute sérénité"
                subtitle="Une suite complète de services logistiques conçue pour les e-commerçants et les entreprises marocaines."
            />

            <div class="owl-services__grid">
                <article
                    v-for="(service, index) in services"
                    :key="service.title"
                    class="owl-scard"
                    :class="`owl-scard--${service.color}`"
                    data-aos="fade-up"
                    :data-aos-delay="index * 80"
                >
                    <div class="owl-scard__icon">
                        <!-- pickup -->
                        <svg v-if="service.icon === 'pickup'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <path d="M3 7l9-4 9 4-9 4-9-4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                            <path d="M3 7v10l9 4 9-4V7M12 11v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        </svg>
                        <!-- express -->
                        <svg v-else-if="service.icon === 'express'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        </svg>
                        <!-- national -->
                        <svg v-else-if="service.icon === 'national'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />
                            <path d="M3 12h18M12 3c2.5 2.5 2.5 15 0 18M12 3c-2.5 2.5-2.5 15 0 18" stroke="currentColor" stroke-width="1.8" />
                        </svg>
                        <!-- cod -->
                        <svg v-else-if="service.icon === 'cod'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <rect x="2.5" y="6" width="19" height="12" rx="2.5" stroke="currentColor" stroke-width="1.8" />
                            <circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8" />
                            <path d="M6 9v6M18 9v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        <!-- returns -->
                        <svg v-else-if="service.icon === 'returns'" viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <path d="M4 9h11a5 5 0 0 1 0 10H8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M7 6L4 9l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <!-- platform -->
                        <svg v-else viewBox="0 0 24 24" fill="none" width="26" height="26">
                            <rect x="3" y="4" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.8" />
                            <path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h3 class="owl-scard__title">{{ service.title }}</h3>
                    <p class="owl-scard__desc">{{ service.description }}</p>
                    <span class="owl-scard__link">
                        En savoir plus
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </article>
            </div>
        </div>
    </section>
</template>

<style scoped>
.owl-section {
    padding: 5.5rem 1.5rem;
}
.owl-container {
    max-width: 1200px;
    margin: 0 auto;
}
.owl-services {
    background: #fff;
}
.owl-services__grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}
.owl-scard {
    position: relative;
    background: #fff;
    border: 1px solid var(--owl-border);
    border-radius: var(--owl-radius);
    padding: 2rem 1.8rem;
    box-shadow: var(--owl-shadow-sm);
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    overflow: hidden;
}
.owl-scard::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 4px;
    background: var(--owl-c, var(--owl-primary));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.35s ease;
}
.owl-scard:hover {
    transform: translateY(-8px);
    box-shadow: var(--owl-shadow-lg);
    border-color: transparent;
}
.owl-scard:hover::before {
    transform: scaleX(1);
}
.owl-scard__icon {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--owl-c, var(--owl-primary));
    background: var(--owl-c-soft, rgba(13, 74, 157, 0.1));
    margin-bottom: 1.3rem;
    transition: transform 0.35s ease;
}
.owl-scard:hover .owl-scard__icon {
    transform: scale(1.08) rotate(-4deg);
}
.owl-scard__title {
    margin: 0 0 0.6rem;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--owl-dark);
}
.owl-scard__desc {
    margin: 0;
    font-size: 0.95rem;
    color: var(--owl-slate);
    line-height: 1.65;
}
.owl-scard__link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 1.2rem;
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--owl-c, var(--owl-primary));
    opacity: 0;
    transform: translateX(-6px);
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.owl-scard:hover .owl-scard__link {
    opacity: 1;
    transform: translateX(0);
}

.owl-scard--blue { --owl-c: #0d4a9d; --owl-c-soft: rgba(13, 74, 157, 0.1); }
.owl-scard--green { --owl-c: #d1471a; --owl-c-soft: rgba(241, 90, 36, 0.12); }
.owl-scard--violet { --owl-c: #7c3aed; --owl-c-soft: rgba(124, 58, 237, 0.1); }
.owl-scard--amber { --owl-c: #d97706; --owl-c-soft: rgba(245, 158, 11, 0.14); }
.owl-scard--rose { --owl-c: #e11d48; --owl-c-soft: rgba(225, 29, 72, 0.1); }

@media (max-width: 992px) {
    .owl-services__grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 620px) {
    .owl-section {
        padding: 4rem 1.1rem;
    }
    .owl-services__grid {
        grid-template-columns: 1fr;
    }
}
</style>
