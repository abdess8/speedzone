<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useBackNavigation } from '@/composables/useBackNavigation';

const props = defineProps({
  status: { type: Number, required: true },
  homeUrl: { type: String, required: true },
});

const { canGoBack, goBack } = useBackNavigation();

const CODES = [403, 404, 419, 429, 500, 503];

const code = computed(() => (CODES.includes(props.status) ? props.status : 500));

const icon = computed(() => ({
  403: 'ri-shield-keyhole-line',
  404: 'ri-compass-3-line',
  419: 'ri-time-line',
  429: 'ri-timer-flash-line',
  500: 'ri-error-warning-line',
  503: 'ri-tools-line',
}[code.value]));

const tone = computed(() => ({
  403: 'warning',
  404: 'primary',
  419: 'info',
  429: 'secondary',
  500: 'danger',
  503: 'secondary',
}[code.value]));
</script>

<template>
  <section class="http-error">
    <div class="http-error-card">
      <div class="http-error-icon" :class="`http-error-icon--${tone}`">
        <i :class="icon" aria-hidden="true"></i>
      </div>

      <p class="http-error-code">{{ code }}</p>
      <h1 class="http-error-title">{{ $t(`errors.${code}.title`) }}</h1>
      <p class="http-error-copy">{{ $t(`errors.${code}.description`) }}</p>

      <div class="http-error-actions">
        <Link :href="homeUrl" class="btn btn-primary">
          <i class="ri-home-5-line me-1"></i>
          {{ $t('errors.home') }}
        </Link>
        <button
          v-if="canGoBack"
          type="button"
          class="btn btn-soft-secondary"
          @click="goBack"
        >
          <i class="ri-arrow-left-line me-1"></i>
          {{ $t('errors.back') }}
        </button>
      </div>
    </div>
  </section>
</template>
