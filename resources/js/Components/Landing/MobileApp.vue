<script setup>
const features = [
    { title: 'Suivi en temps réel', desc: 'Localisez chaque colis en direct.', icon: 'track' },
    { title: 'Notifications instantanées', desc: 'Soyez alerté à chaque étape.', icon: 'bell' },
    { title: 'Gestion des commandes', desc: 'Créez et suivez vos envois.', icon: 'orders' },
    { title: 'Historique complet', desc: 'Retrouvez tout votre activité.', icon: 'history' },
];
</script>

<template>
    <section id="tarifs" class="sz-section sz-app">
        <div class="sz-container sz-app__grid">
            <div class="sz-app__media" data-aos="fade-right">
                <div class="sz-app__glow" aria-hidden="true"></div>
                <div class="sz-app__phone">
                    <div class="sz-app__notch"></div>
                    <div class="sz-app__screen">
                        <div class="sz-app__hd">
                            <span>Bonjour, Yassine 👋</span>
                            <span class="sz-app__avatar">Y</span>
                        </div>
                        <div class="sz-app__balance">
                            <small>Colis en cours</small>
                            <strong>18</strong>
                            <span class="sz-app__chip">3 en livraison</span>
                        </div>
                        <div class="sz-app__item">
                            <span class="sz-app__ic">📦</span>
                            <div><p>SPD-2026-4821</p><small>Rabat → Salé</small></div>
                            <span class="sz-app__tag">En route</span>
                        </div>
                        <div class="sz-app__item">
                            <span class="sz-app__ic">✅</span>
                            <div><p>SPD-2026-4820</p><small>Kénitra</small></div>
                            <span class="sz-app__tag sz-app__tag--green">Livré</span>
                        </div>
                        <div class="sz-app__item">
                            <span class="sz-app__ic">🛵</span>
                            <div><p>SPD-2026-4819</p><small>Témara</small></div>
                            <span class="sz-app__tag">En route</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sz-app__text" data-aos="fade-left">
                <span class="sz-app__eyebrow">Application mobile</span>
                <h2 class="sz-app__title">Votre activité de livraison dans votre poche</h2>
                <p class="sz-app__lead">
                    Gérez vos commandes, suivez vos colis et recevez vos notifications en temps réel,
                    où que vous soyez.
                </p>

                <div class="sz-app__features">
                    <div v-for="(f, i) in features" :key="f.title" class="sz-app__feat" data-aos="fade-up" :data-aos-delay="i * 80">
                        <span class="sz-app__feat-ic">
                            <svg v-if="f.icon === 'track'" viewBox="0 0 24 24" fill="none" width="20" height="20">
                                <path d="M12 21s-7-5.2-7-11a7 7 0 1 1 14 0c0 5.8-7 11-7 11z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                <circle cx="12" cy="10" r="2.4" stroke="currentColor" stroke-width="1.8" />
                            </svg>
                            <svg v-else-if="f.icon === 'bell'" viewBox="0 0 24 24" fill="none" width="20" height="20">
                                <path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                <path d="M10 20a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg v-else-if="f.icon === 'orders'" viewBox="0 0 24 24" fill="none" width="20" height="20">
                                <rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.8" />
                                <path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg v-else viewBox="0 0 24 24" fill="none" width="20" height="20">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />
                                <path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </span>
                        <div>
                            <p>{{ f.title }}</p>
                            <small>{{ f.desc }}</small>
                        </div>
                    </div>
                </div>

                <div class="sz-app__stores">
                    <a href="#" class="sz-store" aria-label="Télécharger sur l'App Store">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path d="M16.5 1.5c.1 1-.3 2-.9 2.8-.7.8-1.8 1.4-2.8 1.3-.1-1 .4-2 1-2.7.7-.8 1.8-1.3 2.7-1.4zM19.8 17c-.5 1.2-.8 1.7-1.5 2.7-.9 1.4-2.2 3.1-3.8 3.1-1.4 0-1.8-.9-3.7-.9s-2.3.9-3.7.9c-1.6 0-2.8-1.6-3.7-2.9C.9 16.8.6 12.4 2.3 10c.9-1.3 2.3-2.1 3.6-2.1 1.4 0 2.3 1 3.5 1 1.1 0 1.8-1 3.5-1 1.2 0 2.5.7 3.4 1.8-3 1.7-2.5 6 .0 7.3z" />
                        </svg>
                        <span><small>Télécharger sur</small><strong>App Store</strong></span>
                    </a>
                    <a href="#" class="sz-store" aria-label="Disponible sur Google Play">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor">
                            <path d="M3.6 2.3c-.3.3-.5.8-.5 1.4v16.6c0 .6.2 1.1.5 1.4l.1.1L13 12.1v-.2L3.7 2.2zM16.3 15.4L13.4 12.5v-.9l2.9-2.9.1.1 3.4 2c1 .5 1 1.5 0 2.1l-3.5 2zM13.4 12.5L4.1 21.8c.3.3.9.4 1.5.1l11-6.3M13.4 11.6L16.6 8.4l-11-6.3c-.6-.3-1.2-.3-1.5.1z" />
                        </svg>
                        <span><small>Disponible sur</small><strong>Google Play</strong></span>
                    </a>
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
.sz-app {
    background: var(--sz-bg);
}
.sz-app__grid {
    display: grid;
    grid-template-columns: 1fr 1.1fr;
    gap: 3.5rem;
    align-items: center;
}
.sz-app__media {
    position: relative;
    display: flex;
    justify-content: center;
}
.sz-app__glow {
    position: absolute;
    width: 340px;
    height: 340px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(29, 78, 216, 0.28), transparent 68%);
    filter: blur(30px);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}
.sz-app__phone {
    position: relative;
    width: 270px;
    background: #0f172a;
    border-radius: 40px;
    padding: 0.75rem;
    box-shadow: var(--sz-shadow-lg);
    animation: sz-float-y 6.5s ease-in-out infinite;
}
.sz-app__notch {
    width: 90px;
    height: 22px;
    background: #0f172a;
    border-radius: 0 0 14px 14px;
    position: absolute;
    top: 0.75rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 2;
}
.sz-app__screen {
    background: linear-gradient(180deg, #eef2ff, #fff 40%);
    border-radius: 32px;
    padding: 1.4rem 1rem 1rem;
    min-height: 470px;
}
.sz-app__hd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--sz-dark);
    margin-bottom: 1rem;
}
.sz-app__avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--sz-primary);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
}
.sz-app__balance {
    background: var(--sz-gradient);
    color: #fff;
    border-radius: 20px;
    padding: 1.1rem;
    margin-bottom: 1rem;
    display: flex;
    flex-direction: column;
    box-shadow: var(--sz-shadow-primary);
}
.sz-app__balance small {
    opacity: 0.85;
    font-size: 0.72rem;
}
.sz-app__balance strong {
    font-size: 2rem;
    line-height: 1.1;
}
.sz-app__chip {
    align-self: flex-start;
    margin-top: 0.5rem;
    background: rgba(255, 255, 255, 0.22);
    border-radius: 999px;
    padding: 0.2rem 0.7rem;
    font-size: 0.7rem;
    font-weight: 600;
}
.sz-app__item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: 16px;
    padding: 0.65rem 0.7rem;
    margin-bottom: 0.6rem;
}
.sz-app__item > div {
    flex: 1;
}
.sz-app__item p {
    margin: 0;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-app__item small {
    font-size: 0.68rem;
    color: var(--sz-muted);
}
.sz-app__ic {
    font-size: 1.1rem;
}
.sz-app__tag {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.16);
    color: #b45309;
}
.sz-app__tag--green {
    background: rgba(16, 185, 129, 0.16);
    color: var(--sz-accent-dark);
}

.sz-app__eyebrow {
    display: inline-block;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--sz-primary);
    background: rgba(29, 78, 216, 0.08);
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    margin-bottom: 1rem;
}
.sz-app__title {
    margin: 0;
    font-size: clamp(1.8rem, 3.4vw, 2.4rem);
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.15;
    color: var(--sz-dark);
}
.sz-app__lead {
    margin: 1rem 0 2rem;
    font-size: 1.05rem;
    color: var(--sz-slate);
    max-width: 480px;
}
.sz-app__features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.2rem;
    margin-bottom: 2rem;
}
.sz-app__feat {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}
.sz-app__feat-ic {
    flex-shrink: 0;
    width: 42px;
    height: 42px;
    border-radius: 13px;
    background: #fff;
    border: 1px solid var(--sz-border);
    color: var(--sz-primary);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--sz-shadow-sm);
}
.sz-app__feat p {
    margin: 0;
    font-weight: 700;
    font-size: 0.92rem;
    color: var(--sz-dark);
}
.sz-app__feat small {
    color: var(--sz-muted);
    font-size: 0.8rem;
}
.sz-app__stores {
    display: flex;
    flex-wrap: wrap;
    gap: 0.9rem;
}
.sz-store {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    background: var(--sz-dark);
    color: #fff;
    text-decoration: none;
    padding: 0.7rem 1.3rem;
    border-radius: 14px;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}
.sz-store:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.25);
    color: #fff;
}
.sz-store span {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}
.sz-store small {
    font-size: 0.62rem;
    opacity: 0.8;
}
.sz-store strong {
    font-size: 0.95rem;
}

@keyframes sz-float-y {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-14px); }
}

@media (max-width: 992px) {
    .sz-app__grid {
        grid-template-columns: 1fr;
        gap: 3.5rem;
    }
    .sz-app__lead {
        max-width: none;
    }
}
@media (max-width: 620px) {
    .sz-section {
        padding: 4rem 1.1rem;
    }
    .sz-app__features {
        grid-template-columns: 1fr;
    }
}
@media (prefers-reduced-motion: reduce) {
    .sz-app__phone {
        animation: none;
    }
}
</style>
