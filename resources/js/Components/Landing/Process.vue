<script setup>
import SectionHeading from '@/Components/Landing/SectionHeading.vue';

const steps = [
    { title: 'Créer la commande', desc: 'Ajoutez vos colis en quelques secondes.', icon: 'order' },
    { title: 'Ramassage', desc: 'Un livreur récupère vos colis.', icon: 'pickup' },
    { title: 'Transit', desc: 'Acheminement entre nos hubs.', icon: 'transit' },
    { title: 'Livraison', desc: 'Remise au client final.', icon: 'delivery' },
    { title: 'Paiement', desc: 'Encaissement COD & reversement.', icon: 'payment' },
];
</script>

<template>
    <section class="sz-section sz-process">
        <div class="sz-container">
            <SectionHeading
                eyebrow="Comment ça marche"
                title="De la commande au paiement, en 5 étapes"
                subtitle="Un processus fluide et entièrement traçable, du premier clic jusqu'au reversement."
            />

            <div class="sz-process__track">
                <div class="sz-process__line" aria-hidden="true"></div>
                <div
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="sz-step"
                    data-aos="fade-up"
                    :data-aos-delay="index * 120"
                >
                    <div class="sz-step__badge">
                        <span class="sz-step__num">{{ index + 1 }}</span>
                        <span class="sz-step__icon">
                            <svg v-if="step.icon === 'order'" viewBox="0 0 24 24" fill="none" width="24" height="24">
                                <rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.8" />
                                <path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg v-else-if="step.icon === 'pickup'" viewBox="0 0 24 24" fill="none" width="24" height="24">
                                <path d="M3 7l9-4 9 4-9 4-9-4zM3 7v10l9 4 9-4V7M12 11v10" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                            </svg>
                            <svg v-else-if="step.icon === 'transit'" viewBox="0 0 24 24" fill="none" width="24" height="24">
                                <path d="M3 13l2-6h9l4 4h3v5h-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="7.5" cy="17.5" r="2" stroke="currentColor" stroke-width="1.8" />
                                <circle cx="17.5" cy="17.5" r="2" stroke="currentColor" stroke-width="1.8" />
                            </svg>
                            <svg v-else-if="step.icon === 'delivery'" viewBox="0 0 24 24" fill="none" width="24" height="24">
                                <path d="M12 21s-7-5.2-7-11a7 7 0 1 1 14 0c0 5.8-7 11-7 11z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                <circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.8" />
                            </svg>
                            <svg v-else viewBox="0 0 24 24" fill="none" width="24" height="24">
                                <rect x="2.5" y="6" width="19" height="12" rx="2.5" stroke="currentColor" stroke-width="1.8" />
                                <circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8" />
                            </svg>
                        </span>
                    </div>
                    <h3 class="sz-step__title">{{ step.title }}</h3>
                    <p class="sz-step__desc">{{ step.desc }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.sz-section {
    padding: 5.5rem 1.5rem;
}
.sz-container {
    max-width: 1200px;
    margin: 0 auto;
}
.sz-process {
    background: #fff;
}
.sz-process__track {
    position: relative;
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.5rem;
}
.sz-process__line {
    position: absolute;
    top: 42px;
    left: 10%;
    right: 10%;
    height: 2px;
    background: repeating-linear-gradient(90deg, var(--sz-border) 0 10px, transparent 10px 20px);
}
.sz-step {
    text-align: center;
    position: relative;
    z-index: 1;
}
.sz-step__badge {
    position: relative;
    width: 84px;
    height: 84px;
    margin: 0 auto 1.2rem;
    border-radius: 26px;
    background: #fff;
    border: 1px solid var(--sz-border);
    box-shadow: var(--sz-shadow);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--sz-primary);
    transition: transform 0.35s ease, box-shadow 0.35s ease, color 0.35s ease;
}
.sz-step:hover .sz-step__badge {
    transform: translateY(-6px);
    box-shadow: var(--sz-shadow-lg);
    color: #fff;
    background: var(--sz-gradient);
    border-color: transparent;
}
.sz-step__num {
    position: absolute;
    top: -10px;
    right: -10px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--sz-accent);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 14px rgba(16, 185, 129, 0.35);
}
.sz-step__title {
    margin: 0 0 0.4rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-step__desc {
    margin: 0;
    font-size: 0.88rem;
    color: var(--sz-slate);
}

@media (max-width: 860px) {
    .sz-process__track {
        grid-template-columns: 1fr 1fr;
        gap: 2.5rem 1.5rem;
    }
    .sz-process__line {
        display: none;
    }
}
@media (max-width: 620px) {
    .sz-section {
        padding: 4rem 1.1rem;
    }
    .sz-process__track {
        grid-template-columns: 1fr;
    }
}
</style>
