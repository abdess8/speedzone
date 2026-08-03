<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";

defineProps({
  sections: { type: Array, default: () => [] },
});

/**
 * Section currently expanded. Only one at a time: the contract reads as six
 * distinct questions, and a fully open page turns it back into a wall of text.
 */
const openKey = ref(null);

const toggle = (key) => {
  openKey.value = openKey.value === key ? null : key;
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('help.partnership.page_title')" :pageTitle="$t('help.title')" />

    <BCard no-body>
      <BCardBody>
        <p class="text-muted mb-0">{{ $t('help.partnership.intro') }}</p>
      </BCardBody>
    </BCard>

    <BCard no-body>
      <BCardBody class="d-grid gap-2">
        <article v-for="section in sections" :key="section.key" class="terms-section">
          <button
            type="button"
            class="terms-header"
            :aria-expanded="openKey === section.key"
            :aria-controls="`terms-${section.key}`"
            @click="toggle(section.key)"
          >
            <span class="terms-icon">
              <i :class="section.icon"></i>
            </span>
            <span class="terms-heading">
              <span class="terms-title">{{ section.title }}</span>
              <span class="terms-summary">{{ section.summary }}</span>
            </span>
            <i
              class="ri-arrow-down-s-line terms-chevron"
              :class="{ open: openKey === section.key }"
              aria-hidden="true"
            ></i>
          </button>

          <Transition name="terms">
            <ul v-show="openKey === section.key" :id="`terms-${section.key}`" class="terms-list">
              <li v-for="point in section.points" :key="point">{{ point }}</li>
            </ul>
          </Transition>
        </article>
      </BCardBody>
    </BCard>

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
          <h6 class="mb-1 fs-14">{{ $t('help.processes.page_title') }}</h6>
          <p class="text-muted mb-0 fs-13">{{ $t('help.processes.intro') }}</p>
        </div>
        <Link :href="route('help.processes')" class="btn btn-primary">
          <i class="ri-route-line align-bottom me-1"></i>{{ $t('common.view') }}
        </Link>
      </BCardBody>
    </BCard>
  </Layout>
</template>

<style scoped>
.terms-section {
  overflow: hidden;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.65rem;
}

.terms-header {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.85rem;
  padding: 1rem;
  border: 0;
  background: transparent;
  text-align: start;
  transition: background-color 0.15s ease;
}

.terms-header:hover {
  background: rgba(var(--vz-primary-rgb), 0.04);
}

.terms-icon {
  display: inline-flex;
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.6rem;
  background: rgba(var(--vz-primary-rgb), 0.1);
  color: var(--vz-primary);
  font-size: 1.2rem;
}

.terms-heading {
  min-width: 0;
  flex-grow: 1;
}

.terms-title {
  display: block;
  font-size: 0.9375rem;
  font-weight: 600;
}

.terms-summary {
  display: block;
  color: var(--vz-secondary-color);
  font-size: 0.8125rem;
}

.terms-chevron {
  flex-shrink: 0;
  color: var(--vz-secondary-color);
  font-size: 1.25rem;
  transition: transform 0.2s ease;
}

.terms-chevron.open {
  transform: rotate(180deg);
}

.terms-list {
  margin: 0;
  padding: 0 1rem 1rem 4.2rem;
  color: var(--vz-body-color);
  font-size: 0.875rem;
}

.terms-list li {
  margin-bottom: 0.5rem;
}

.terms-list li:last-child {
  margin-bottom: 0;
}

.terms-enter-active,
.terms-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.terms-enter-from,
.terms-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
  .terms-header,
  .terms-chevron,
  .terms-enter-active,
  .terms-leave-active {
    transition: none;
  }
}
</style>
