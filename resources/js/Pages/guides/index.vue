<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import { guideI18nKey, guideStepCount, hasGuide } from '@/guides/index.js';
import { startGuide } from '@/composables/useGuide';
import {
  guideProgressFor,
  hydrateGuideProgress,
  resetGuideProgress,
} from '@/composables/useGuideProgress';

/**
 * The Help Center — "Académie".
 *
 * Lists the interactive guides this reader may run. The catalog arrives already
 * filtered by the server; a guide with no client definition is dropped here,
 * since there would be nothing to play.
 */

const props = defineProps({
  guides: { type: Array, default: () => [] },
  /** guide key → { completed, completed_count, last_step_index } */
  progress: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const search = ref('');
const category = ref('all');

// The server's copy wins over whatever localStorage remembered on this device.
onMounted(() => hydrateGuideProgress(props.progress));

const catalog = computed(() =>
  props.guides
    .filter((guide) => hasGuide(guide.key))
    .map((guide) => {
      const i18nKey = guideI18nKey(guide.key);
      const state = guideProgressFor(guide.key);

      return {
        ...guide,
        title: t(`guides.catalog.${i18nKey}.title`),
        summary: t(`guides.catalog.${i18nKey}.summary`),
        audience: t(`guides.catalog.${i18nKey}.audience`),
        steps: guideStepCount(guide.key),
        completed: state.completed,
        completedCount: state.completed_count,
        resumeIndex: state.completed ? 0 : state.last_step_index,
      };
    })
);

const categories = computed(() => [
  'all',
  ...new Set(catalog.value.map((guide) => guide.category)),
]);

const filtered = computed(() => {
  const needle = search.value.trim().toLowerCase();

  return catalog.value.filter((guide) => {
    if (category.value !== 'all' && guide.category !== category.value) {
      return false;
    }

    if (needle === '') {
      return true;
    }

    return `${guide.title} ${guide.summary}`.toLowerCase().includes(needle);
  });
});

const completedTotal = computed(() => catalog.value.filter((guide) => guide.completed).length);

function statusOf(guide) {
  if (guide.completed) {
    return { key: 'completed', variant: 'success', icon: 'ri-checkbox-circle-line' };
  }

  if (guide.resumeIndex > 0) {
    return { key: 'in_progress', variant: 'warning', icon: 'ri-progress-4-line' };
  }

  return { key: 'new', variant: 'primary', icon: 'ri-sparkling-line' };
}

function actionLabel(guide) {
  if (guide.completed) {
    return t('guides.card.replay');
  }

  if (guide.resumeIndex > 0) {
    return t('guides.card.resume', { step: guide.resumeIndex + 1 });
  }

  return t('guides.card.start');
}

function play(guide) {
  // The engine takes it from here: it travels to the guide's screen if we are
  // not already on it, then shows the first step.
  startGuide(guide.key, { stepIndex: guide.resumeIndex });
}

async function forget(guide) {
  if (window.confirm(t('guides.card.reset_confirm'))) {
    await resetGuideProgress(guide.key);
  }
}
</script>

<template>
  <Layout>
    <PageHeader :title="$t('guides.title')" :pageTitle="$t('guides.page_title')" />

    <BCard no-body class="guides-hero">
      <BCardBody class="p-4">
        <BRow class="align-items-center g-4">
          <BCol lg="8">
            <h4 class="mb-2">{{ $t('guides.title') }}</h4>
            <p class="text-muted mb-0">{{ $t('guides.subtitle') }}</p>
          </BCol>
          <BCol lg="4">
            <div class="d-flex gap-3 justify-content-lg-end">
              <div class="text-center">
                <h3 class="mb-0 text-primary">{{ catalog.length }}</h3>
                <p class="text-muted fs-12 mb-0">
                  {{ $t('guides.available', { count: catalog.length }) }}
                </p>
              </div>
              <div class="vr"></div>
              <div class="text-center">
                <h3 class="mb-0 text-success">{{ completedTotal }}</h3>
                <p class="text-muted fs-12 mb-0">
                  {{ $t('guides.completed_count', { completed: completedTotal, total: catalog.length }) }}
                </p>
              </div>
            </div>
          </BCol>
        </BRow>
      </BCardBody>
    </BCard>

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-3">
        <div class="search-box flex-grow-1" style="max-width: 22rem">
          <input
            v-model="search"
            type="search"
            class="form-control"
            :placeholder="$t('guides.search')"
          />
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button
            v-for="option in categories"
            :key="option"
            type="button"
            class="btn btn-sm"
            :class="category === option ? 'btn-primary' : 'btn-soft-secondary'"
            @click="category = option"
          >
            {{ option === 'all' ? $t('common.all') : $t(`guides.categories.${option}`) }}
          </button>
        </div>
      </BCardBody>
    </BCard>

    <BRow class="g-4">
      <BCol v-for="guide in filtered" :key="guide.key" md="6" xl="4">
        <BCard no-body class="h-100 guide-tile">
          <BCardBody class="d-flex flex-column">
            <div class="d-flex align-items-start gap-3 mb-3">
              <div class="guide-tile__icon bg-primary-subtle text-primary">
                <i :class="guide.icon"></i>
              </div>
              <div class="flex-grow-1">
                <span class="badge bg-light text-body border mb-1">
                  {{ $t(`guides.categories.${guide.category}`) }}
                </span>
                <h5 class="fs-15 mb-0">{{ guide.title }}</h5>
              </div>
              <span
                class="badge"
                :class="`bg-${statusOf(guide).variant}-subtle text-${statusOf(guide).variant}`"
              >
                <i :class="statusOf(guide).icon" class="align-bottom me-1"></i>
                {{ $t(`guides.status.${statusOf(guide).key}`) }}
              </span>
            </div>

            <p class="text-muted fs-13 flex-grow-1">{{ guide.summary }}</p>

            <div class="d-flex flex-wrap align-items-center gap-3 text-muted fs-12 mb-3">
              <span>
                <i class="ri-list-ordered align-bottom me-1"></i>
                {{ $t('guides.card.steps', { count: guide.steps }) }}
              </span>
              <span>
                <i class="ri-time-line align-bottom me-1"></i>
                {{ $t('guides.card.minutes', { count: guide.minutes }) }}
              </span>
              <span>
                <i class="ri-user-star-line align-bottom me-1"></i>
                {{ guide.audience }}
              </span>
            </div>

            <p v-if="guide.completedCount > 0" class="text-success fs-12 mb-3">
              <i class="ri-medal-line align-bottom me-1"></i>
              {{ $t('guides.status.completed_times', { count: guide.completedCount }) }}
            </p>

            <div class="d-flex align-items-center gap-2">
              <BButton variant="primary" class="flex-grow-1" @click="play(guide)">
                <i class="ri-play-circle-line align-bottom me-1"></i>
                {{ actionLabel(guide) }}
              </BButton>
              <BButton
                v-if="guide.completed || guide.resumeIndex > 0"
                variant="ghost-secondary"
                :title="$t('guides.card.reset')"
                @click="forget(guide)"
              >
                <i class="ri-restart-line"></i>
              </BButton>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol v-if="filtered.length === 0" cols="12">
        <BCard no-body>
          <BCardBody class="text-center py-5">
            <i class="ri-compass-3-line display-6 text-muted"></i>
            <p class="text-muted mt-3 mb-0">
              {{ catalog.length === 0 ? $t('guides.empty_catalog') : $t('guides.empty') }}
            </p>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
.guides-hero {
  background-image: linear-gradient(135deg, rgba(var(--vz-primary-rgb), 0.08), transparent 60%);
}

.guide-tile {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.guide-tile:hover {
  transform: translateY(-2px);
  box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.08);
}

.guide-tile__icon {
  display: flex;
  width: 2.75rem;
  height: 2.75rem;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 0.625rem;
  font-size: 1.375rem;
}

@media (prefers-reduced-motion: reduce) {
  .guide-tile,
  .guide-tile:hover {
    transform: none;
    transition: none;
  }
}
</style>
