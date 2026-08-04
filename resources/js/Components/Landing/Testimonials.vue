<script setup>
import SectionHeading from '@/Components/Landing/SectionHeading.vue';
import { Swiper, SwiperSlide } from 'swiper/vue';
import { Autoplay, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/pagination';

const modules = [Autoplay, Pagination];

const testimonials = [
    {
        name: 'Yassine El Amrani',
        company: 'Boutique Mode Rabat',
        initials: 'YA',
        color: 'blue',
        rating: 5,
        comment:
            "Depuis que nous utilisons OWL Delivery, nos livraisons sont plus rapides et nos clients beaucoup plus satisfaits. Le suivi en temps réel change tout.",
    },
    {
        name: 'Salma Bennani',
        company: 'E-commerce Kénitra',
        initials: 'SB',
        color: 'green',
        rating: 5,
        comment:
            "La plateforme est vraiment simple et le service COD est fiable. On gère toutes nos commandes et reversements sans stress au quotidien.",
    },
    {
        name: 'Karim Tazi',
        company: 'Parapharmacie Gharb',
        initials: 'KT',
        color: 'violet',
        rating: 5,
        comment:
            "Les reversements COD sont rapides et transparents. Une équipe réactive et des livreurs toujours professionnels. Je recommande vivement.",
    },
    {
        name: 'Nabila Chraibi',
        company: 'Cosmétiques Salé',
        initials: 'NC',
        color: 'amber',
        rating: 5,
        comment:
            "Enfin un partenaire logistique qui comprend le e-commerce marocain. La gestion des retours nous fait gagner un temps précieux.",
    },
];
</script>

<template>
    <section id="apropos" class="owl-section owl-testi">
        <div class="owl-container">
            <SectionHeading
                eyebrow="Ils nous font confiance"
                title="Ce que nos clients disent de OWL Delivery"
                subtitle="Des centaines d'entreprises marocaines nous confient leurs livraisons chaque jour."
            />

            <div data-aos="fade-up">
                <Swiper
                    :modules="modules"
                    :slides-per-view="1"
                    :space-between="24"
                    :loop="true"
                    :autoplay="{ delay: 4500, disableOnInteraction: false }"
                    :pagination="{ clickable: true }"
                    :breakpoints="{ 768: { slidesPerView: 2 }, 1100: { slidesPerView: 3 } }"
                    class="owl-testi__swiper"
                >
                    <SwiperSlide v-for="t in testimonials" :key="t.name">
                        <article class="owl-tcard">
                            <div class="owl-tcard__quote">"</div>
                            <div class="owl-tcard__stars">
                                <svg v-for="n in t.rating" :key="n" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2l2.9 6.2 6.8.8-5 4.6 1.3 6.7L12 17.8 5.9 20.3l1.4-6.7-5-4.6 6.8-.8z" />
                                </svg>
                            </div>
                            <p class="owl-tcard__text">{{ t.comment }}</p>
                            <div class="owl-tcard__author">
                                <span class="owl-tcard__avatar" :class="`owl-tcard__avatar--${t.color}`">{{ t.initials }}</span>
                                <div>
                                    <p class="owl-tcard__name">{{ t.name }}</p>
                                    <small class="owl-tcard__company">{{ t.company }}</small>
                                </div>
                            </div>
                        </article>
                    </SwiperSlide>
                </Swiper>
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
.owl-testi {
    background: #fff;
}
.owl-testi__swiper {
    padding: 0.5rem 0.5rem 3.5rem;
}
.owl-tcard {
    position: relative;
    height: 100%;
    background: var(--owl-bg);
    border: 1px solid var(--owl-border);
    border-radius: var(--owl-radius);
    padding: 2rem 1.8rem;
    box-shadow: var(--owl-shadow-sm);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
}
.owl-tcard:hover {
    transform: translateY(-6px);
    box-shadow: var(--owl-shadow-lg);
}
.owl-tcard__quote {
    position: absolute;
    top: -8px;
    right: 18px;
    font-size: 5rem;
    line-height: 1;
    font-weight: 800;
    color: rgba(13, 74, 157, 0.1);
}
.owl-tcard__stars {
    color: #f59e0b;
    display: inline-flex;
    gap: 2px;
    margin-bottom: 1rem;
}
.owl-tcard__text {
    margin: 0 0 1.5rem;
    font-size: 0.98rem;
    color: var(--owl-slate);
    line-height: 1.7;
}
.owl-tcard__author {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.owl-tcard__avatar {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.9rem;
    color: #fff;
}
.owl-tcard__avatar--blue { background: var(--owl-primary); }
.owl-tcard__avatar--green { background: var(--owl-accent); }
.owl-tcard__avatar--violet { background: #7c3aed; }
.owl-tcard__avatar--amber { background: #f59e0b; }
.owl-tcard__name {
    margin: 0;
    font-weight: 700;
    color: var(--owl-dark);
    font-size: 0.95rem;
}
.owl-tcard__company {
    color: var(--owl-muted);
    font-size: 0.82rem;
}

.owl-testi__swiper :deep(.swiper-pagination-bullet) {
    background: #cbd5e1;
    opacity: 1;
}
.owl-testi__swiper :deep(.swiper-pagination-bullet-active) {
    background: var(--owl-primary);
    width: 22px;
    border-radius: 999px;
}

@media (max-width: 620px) {
    .owl-section {
        padding: 4rem 1.1rem;
    }
}
</style>
