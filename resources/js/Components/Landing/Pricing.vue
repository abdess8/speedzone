<script setup>
import { computed, ref } from 'vue';
import SectionHeading from '@/Components/Landing/SectionHeading.vue';
import LandingButton from '@/Components/Landing/LandingButton.vue';
import { useLandingLocale } from '@/Components/Landing/i18n';

const props = defineProps({
    cities: { type: Array, default: () => [] },
    price: { type: Object, default: () => ({ min: 0, max: 0 }) },
});

const { t, tName } = useLandingLocale();

const query = ref('');

const included = ['pickup', 'cod', 'tracking', 'attempts', 'support'];

const cityName = (city) => tName('cities', city.key, city.name);
const regionName = (city) => tName('regions', city.region_key, city.region);

const rows = computed(() => {
    const needle = query.value.trim().toLowerCase();

    const matching = needle
        ? props.cities.filter(
              (city) =>
                  cityName(city).toLowerCase().includes(needle) ||
                  regionName(city).toLowerCase().includes(needle)
          )
        : [...props.cities];

    return matching.sort((a, b) => a.price - b.price || cityName(a).localeCompare(cityName(b)));
});
</script>

<template>
    <section id="tarifs" class="sz-section sz-pricing">
        <div class="sz-container">
            <SectionHeading
                :eyebrow="t('pricing.eyebrow')"
                :title="t('pricing.title')"
                :subtitle="t('pricing.subtitle')"
            />

            <div class="sz-pricing__grid">
                <aside class="sz-pricing__side" data-aos="fade-right">
                    <div class="sz-pricing__range">
                        <span class="sz-pricing__range-label">{{ t('coverage.from') }}</span>
                        <p class="sz-pricing__range-value">
                            {{ Math.round(price.min) }}
                            <small>{{ t('coverage.currency') }}</small>
                        </p>
                        <p class="sz-pricing__range-note">
                            {{ t('pricing.priceRange', { min: Math.round(price.min), max: Math.round(price.max) }) }}
                        </p>
                    </div>

                    <div class="sz-pricing__included">
                        <h3>{{ t('pricing.included.title') }}</h3>
                        <ul>
                            <li v-for="item in included" :key="item">
                                <span class="sz-check">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                                        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                {{ t(`pricing.included.items.${item}`) }}
                            </li>
                        </ul>
                        <LandingButton href="/register" variant="primary" size="md" block>
                            {{ t('pricing.cta') }}
                        </LandingButton>
                    </div>
                </aside>

                <div class="sz-pricing__table-wrap" data-aos="fade-left">
                    <label class="sz-pricing__search">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                            <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        <input v-model="query" type="search" :placeholder="t('pricing.search')" :aria-label="t('pricing.search')" />
                    </label>

                    <div class="sz-pricing__scroll">
                        <table class="sz-table">
                            <thead>
                                <tr>
                                    <th>{{ t('pricing.table.city') }}</th>
                                    <th>{{ t('pricing.table.region') }}</th>
                                    <th>{{ t('pricing.table.delay') }}</th>
                                    <th class="sz-table__price">{{ t('pricing.table.price') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="city in rows" :key="city.key">
                                    <td class="sz-table__city">{{ cityName(city) }}</td>
                                    <td class="sz-table__region">{{ regionName(city) }}</td>
                                    <td><span class="sz-table__delay">{{ city.delay }}</span></td>
                                    <td class="sz-table__price">
                                        <strong>{{ Math.round(city.price) }} {{ t('coverage.currency') }}</strong>
                                    </td>
                                </tr>
                                <tr v-if="!rows.length">
                                    <td colspan="4" class="sz-table__empty">{{ t('pricing.noResult') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="sz-pricing__note">{{ t('pricing.note') }}</p>
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
.sz-pricing {
    background: #fff;
}
.sz-pricing__grid {
    display: grid;
    grid-template-columns: 0.85fr 1.4fr;
    gap: 2rem;
    align-items: start;
}

.sz-pricing__side {
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
}
.sz-pricing__range {
    background: var(--sz-gradient);
    color: #fff;
    border-radius: var(--sz-radius);
    padding: 1.6rem;
    box-shadow: var(--sz-shadow-primary);
}
.sz-pricing__range-label {
    font-size: 0.78rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    opacity: 0.85;
}
.sz-pricing__range-value {
    margin: 0.25rem 0 0.5rem;
    font-size: 3rem;
    font-weight: 800;
    line-height: 1;
}
.sz-pricing__range-value small {
    font-size: 1.1rem;
    font-weight: 700;
    opacity: 0.9;
}
.sz-pricing__range-note {
    margin: 0;
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.88);
}

.sz-pricing__included {
    background: var(--sz-bg);
    border: 1px solid var(--sz-border);
    border-radius: var(--sz-radius);
    padding: 1.5rem;
}
.sz-pricing__included h3 {
    margin: 0 0 1rem;
    font-size: 1rem;
    font-weight: 700;
    color: var(--sz-dark);
}
.sz-pricing__included ul {
    list-style: none;
    margin: 0 0 1.4rem;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}
.sz-pricing__included li {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--sz-slate);
}
.sz-check {
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    border-radius: 7px;
    background: rgba(16, 185, 129, 0.14);
    color: var(--sz-accent-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.sz-pricing__table-wrap {
    background: #fff;
    border: 1px solid var(--sz-border);
    border-radius: var(--sz-radius);
    padding: 1.2rem;
    box-shadow: var(--sz-shadow-sm);
}
.sz-pricing__search {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: var(--sz-bg);
    border: 1px solid var(--sz-border);
    border-radius: 999px;
    padding: 0.6rem 1rem;
    color: var(--sz-muted);
    margin-bottom: 1rem;
}
.sz-pricing__search input {
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    font-family: var(--sz-font);
    font-size: 0.92rem;
    color: var(--sz-dark);
}
.sz-pricing__scroll {
    max-height: 430px;
    overflow-y: auto;
}
.sz-table {
    width: 100%;
    border-collapse: collapse;
}
.sz-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #fff;
    text-align: start;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--sz-muted);
    padding: 0.6rem 0.75rem;
    border-bottom: 1px solid var(--sz-border);
}
.sz-table td {
    padding: 0.7rem 0.75rem;
    border-bottom: 1px solid var(--sz-border);
    font-size: 0.9rem;
    color: var(--sz-slate);
}
.sz-table tbody tr:last-child td {
    border-bottom: none;
}
.sz-table tbody tr:hover td {
    background: var(--sz-bg);
}
.sz-table__city {
    font-weight: 700;
    color: var(--sz-dark);
    white-space: nowrap;
}
.sz-table__region {
    font-size: 0.85rem;
}
.sz-table__delay {
    display: inline-block;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--sz-primary-dark);
    background: rgba(29, 78, 216, 0.08);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    white-space: nowrap;
}
.sz-table__price {
    text-align: end;
    white-space: nowrap;
}
.sz-table__price strong {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--sz-dark);
}
.sz-table__empty {
    text-align: center;
    color: var(--sz-muted);
    padding: 2rem 0.75rem;
}
.sz-pricing__note {
    margin: 1rem 0 0;
    font-size: 0.8rem;
    color: var(--sz-muted);
}

@media (max-width: 992px) {
    .sz-pricing__grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 620px) {
    .sz-section {
        padding: 4rem 1.1rem;
    }
    .sz-table__region {
        display: none;
    }
    .sz-table thead th:nth-child(2) {
        display: none;
    }
}
</style>
