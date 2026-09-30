<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import french from '@assets/images/flags/fr.svg';
import us_flag from '@assets/images/flags/us.svg';

const page = usePage();

const languages = [
  { code: 'fr', title: 'Français', flag: french },
  { code: 'en', title: 'English', flag: us_flag },
];

const current = computed(() => page.props.locale ?? 'fr');

const setLanguage = (locale) => {
  if (locale === current.value) {
    return;
  }

  router.post(route('locale.update'), { locale }, { preserveScroll: true });
};
</script>

<template>
  <div class="auth-locale-switcher d-inline-flex align-items-center gap-1 rounded-pill px-1 py-1">
    <button
      v-for="language in languages"
      :key="language.code"
      type="button"
      class="btn btn-sm rounded-pill d-inline-flex align-items-center gap-1 px-2 py-1"
      :class="language.code === current ? 'btn-light text-body' : 'btn-link text-white text-decoration-none'"
      :aria-pressed="language.code === current"
      :aria-label="language.title"
      @click="setLanguage(language.code)"
    >
      <img :src="language.flag" :alt="language.title" height="14" width="20" />
      <span class="fs-12 fw-semibold">{{ language.code.toUpperCase() }}</span>
    </button>
  </div>
</template>

<style scoped>
.auth-locale-switcher {
  background: rgba(15, 23, 42, 0.35);
  backdrop-filter: blur(6px);
}
</style>
