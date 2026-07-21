<script setup>
import LandingButton from '@/Components/Landing/LandingButton.vue';

defineProps({
    authenticated: { type: Boolean, default: false },
});
</script>

<template>
    <section class="sz-cta-wrap">
        <div class="sz-container">
            <div class="sz-cta" data-aos="zoom-in">
                <div class="sz-cta__glow" aria-hidden="true"></div>
                <div class="sz-cta__content">
                    <span class="sz-cta__badge">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                        </svg>
                        Prêt à accélérer ?
                    </span>
                    <h2 class="sz-cta__title">Prêt à améliorer vos livraisons ?</h2>
                    <p class="sz-cta__text">
                        Rejoignez les centaines d'entreprises qui font déjà confiance à SpeedZone
                        pour livrer plus vite et plus sereinement.
                    </p>
                    <div class="sz-cta__actions">
                        <LandingButton v-if="authenticated" href="/dashboard" variant="light" size="lg">
                            Ouvrir le tableau de bord
                        </LandingButton>
                        <LandingButton v-else href="/register" variant="light" size="lg">
                            Créer un compte gratuitement
                        </LandingButton>
                        <LandingButton href="#contact" variant="ghost" size="lg">
                            Planifier un ramassage
                        </LandingButton>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.sz-cta-wrap {
    padding: 2rem 1.5rem 5rem;
    background: var(--sz-bg);
}
.sz-container {
    max-width: 1200px;
    margin: 0 auto;
}
.sz-cta {
    position: relative;
    border-radius: var(--sz-radius-lg);
    background: var(--sz-gradient);
    padding: 4rem 2rem;
    text-align: center;
    overflow: hidden;
    box-shadow: var(--sz-shadow-lg);
}
.sz-cta__glow {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.22), transparent 40%),
        radial-gradient(circle at 80% 90%, rgba(16, 185, 129, 0.35), transparent 45%);
}
.sz-cta__content {
    position: relative;
    max-width: 660px;
    margin: 0 auto;
}
.sz-cta__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    font-weight: 700;
    font-size: 0.82rem;
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    margin-bottom: 1.2rem;
}
.sz-cta__title {
    margin: 0;
    font-size: clamp(1.9rem, 4vw, 2.8rem);
    font-weight: 800;
    letter-spacing: -0.02em;
    color: #fff;
}
.sz-cta__text {
    margin: 1rem auto 2rem;
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.9);
    max-width: 540px;
}
.sz-cta__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.9rem;
    justify-content: center;
}

@media (max-width: 620px) {
    .sz-cta {
        padding: 3rem 1.3rem;
    }
}
</style>
