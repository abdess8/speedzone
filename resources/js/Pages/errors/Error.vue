<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import ErrorPanel from './ErrorPanel.vue';

const props = defineProps({
  status: { type: Number, required: true },
  homeUrl: { type: String, required: true },
});

const page = usePage();
const authenticated = computed(() => Boolean(page.props.auth?.user));
const pageTitle = computed(() => String(props.status));
</script>

<template>
  <Head :title="pageTitle" />

  <!-- Signed-in visitors keep the real chrome (topbar + sidebar or top nav,
       plus the phone tab bar) so a 403 is a page they can leave, not a modal
       that traps them on the URL they were not allowed to open. -->
  <Layout v-if="authenticated">
    <ErrorPanel :status="status" :home-url="homeUrl" />
  </Layout>

  <div v-else class="http-error-guest">
    <header class="http-error-guest-bar">
      <Link href="/" class="http-error-guest-brand">
        <img src="@assets/images/logo-brand-full.png" alt="SpeedZone Express" />
      </Link>
      <Link :href="route('login')" class="btn btn-primary btn-sm">
        {{ $t('errors.login') }}
      </Link>
    </header>
    <ErrorPanel :status="status" :home-url="homeUrl" />
  </div>
</template>
