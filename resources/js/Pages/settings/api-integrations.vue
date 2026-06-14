<script setup>
import { Head } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
</script>

<template>
  <Layout>
    <Head :title="$t('sidebar.api_integrations')" />
    <PageHeader :title="$t('sidebar.api_integrations')" />

    <BRow>
      <BCol cols="12">
        <BCard no-body>
          <BCardBody class="text-center py-5">
            <div class="avatar-md mx-auto mb-4">
              <div class="avatar-title bg-primary-subtle text-primary rounded-circle fs-24">
                <i class="ri-plug-2-line"></i>
              </div>
            </div>
            <h5 class="mb-2">{{ $t('sidebar.api_integrations') }}</h5>
            <p class="text-muted mb-0">
              {{ $t('sidebar.api_integrations') }}
            </p>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
