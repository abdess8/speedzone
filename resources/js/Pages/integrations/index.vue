<script setup>
import { ref } from 'vue';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import { ECOMMERCE_PLATFORMS } from '@/constants/ecommercePlatforms';

defineProps({
  can: { type: Object, default: () => ({}) },
  /** Platform the topbar shortcut pointed at, highlighted on arrival. */
  selected: { type: String, default: null },
});

const platforms = ref(ECOMMERCE_PLATFORMS);
</script>

<template>
  <Layout>
    <PageHeader :title="$t('integrations.title')" :pageTitle="$t('common.settings')" />

    <BCard no-body>
      <BCardBody>
        <h5 class="card-title mb-1">{{ $t('integrations.catalog_title') }}</h5>
        <p class="text-muted mb-0">{{ $t('integrations.catalog_subtitle') }}</p>
      </BCardBody>
    </BCard>

    <BRow class="g-3">
      <BCol v-for="platform in platforms" :key="platform.key" md="6" xl="3">
        <BCard no-body class="h-100" :class="{ 'border border-primary': selected === platform.key }">
          <BCardBody class="d-flex flex-column">
            <div class="d-flex align-items-center gap-3 mb-3">
              <span class="platform-mark" :style="{ backgroundColor: platform.color }">
                <i :class="platform.icon"></i>
              </span>
              <div>
                <h5 class="mb-0">{{ platform.name }}</h5>
                <span class="badge bg-warning-subtle text-warning mt-1">
                  {{ $t('integrations.status.soon') }}
                </span>
              </div>
            </div>

            <p class="text-muted fs-13 flex-grow-1">
              {{ $t(`integrations.platforms.${platform.key}`) }}
            </p>

            <BButton variant="soft-primary" class="w-100" disabled>
              <i class="ri-plug-line align-bottom me-1"></i>
              {{ $t('integrations.connect') }}
            </BButton>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BAlert :model-value="true" variant="info" class="mt-3 mb-0">
      <i class="ri-information-line align-bottom me-1"></i>
      {{ can.manage ? $t('integrations.roadmap_manager') : $t('integrations.roadmap_viewer') }}
    </BAlert>
  </Layout>
</template>

<style scoped>
.platform-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.5rem;
  font-size: 1.5rem;
  color: #fff;
}
</style>
