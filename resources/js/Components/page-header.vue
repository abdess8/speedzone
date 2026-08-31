<script setup>
import { useBackNavigation } from '@/composables/useBackNavigation';
import { usePageRefresh } from '@/composables/usePageRefresh';

/**
 * Title bar of every screen, and therefore the one place where a back button
 * and a refresh button can be added once and appear on all of them.
 *
 * The back arrow shows itself only when this tab has somewhere of ours to go
 * back to, so the landing page of a session — the dashboard, or a link opened
 * straight from a message — is not decorated with an arrow that leads nowhere.
 * Pages that own their navigation (a wizard, a scanner) opt out with
 * `:back="false"`, and a page whose data cannot meaningfully be re-fetched
 * (a form being filled in) with `:refresh="false"`.
 */

defineProps({
  title: { type: String, default: '' },
  pageTitle: { type: String, default: '' },
  back: { type: Boolean, default: true },
  refresh: { type: Boolean, default: true },
});

const { canGoBack, goBack } = useBackNavigation();
const { refreshing, refresh: reloadPage } = usePageRefresh();
</script>

<template>
  <BRow>
    <BCol cols="12">
      <div class="page-title-box d-sm-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <button
            v-if="back && canGoBack"
            type="button"
            class="btn btn-sm btn-icon btn-light flex-shrink-0"
            :title="$t('common.back')"
            :aria-label="$t('common.back')"
            @click="goBack"
          >
            <i class="ri-arrow-left-line fs-16"></i>
          </button>
          <h4 class="mb-sm-0">{{ title }}</h4>
          <button
            v-if="refresh"
            type="button"
            class="btn btn-sm btn-icon btn-light flex-shrink-0"
            :disabled="refreshing"
            :title="$t('common.refresh')"
            :aria-label="$t('common.refresh')"
            @click="reloadPage"
          >
            <i class="ri-refresh-line fs-16" :class="{ 'page-header-spin': refreshing }"></i>
          </button>
        </div>

        <div class="page-title-right">
          <ol class="breadcrumb m-0">
            <li class="breadcrumb-item">
              <BLink href="javascript: void(0);">{{ pageTitle }}</BLink>
            </li>
            <li class="breadcrumb-item active">{{ title }}</li>
          </ol>
        </div>
      </div>
    </BCol>
  </BRow>
</template>

<style scoped>
.page-header-spin {
  display: inline-block;
  animation: page-header-spin 0.8s linear infinite;
}

@keyframes page-header-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .page-header-spin {
    animation: none;
    opacity: 0.5;
  }
}
</style>
