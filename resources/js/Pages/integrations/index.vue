<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';

const props = defineProps({
  platforms: { type: Array, default: () => [] },
  stores: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
  /** Platform the topbar shortcut pointed at, highlighted on arrival. */
  selected: { type: String, default: null },
});

const { t } = useI18n();

const statusVariant = (platform) => {
  if (!platform.available) {
    return { class: 'bg-warning-subtle text-warning', key: 'soon' };
  }

  const status = platform.connection?.status;

  if (status === 'connected') {
    return { class: 'bg-success-subtle text-success', key: 'connected' };
  }

  if (status === 'error') {
    return { class: 'bg-danger-subtle text-danger', key: 'error' };
  }

  if (status === 'pending') {
    return { class: 'bg-info-subtle text-info', key: 'pending' };
  }

  return { class: 'bg-secondary-subtle text-secondary', key: 'disconnected' };
};

const hrefFor = (platform) => {
  if (platform.key === 'youcan' && platform.available && platform.can_manage) {
    return route('integrations.youcan');
  }

  return null;
};

const disconnecting = ref(null);
const disconnectForm = useForm({});

const disconnect = (platform) => {
  const id = platform.connection?.id;

  if (!id) {
    return;
  }

  Swal.fire({
    title: t(`integrations.${platform.key}.disconnect_title`),
    text: t(`integrations.${platform.key}.disconnect_text`),
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: t('integrations.disconnect'),
    cancelButtonText: t('common.cancel'),
  }).then((result) => {
    if (!result.isConfirmed) {
      return;
    }

    disconnecting.value = id;
    disconnectForm.delete(route('integrations.destroy', id), {
      preserveScroll: true,
      onFinish: () => {
        disconnecting.value = null;
      },
    });
  });
};

const showFlash = () => {
  const flash = usePage().props?.flash ?? {};

  if (flash.success) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: flash.success,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  }

  if (flash.error) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: flash.error,
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true,
    });
  }
};

onMounted(showFlash);

watch(
  () => [usePage().props?.flash?.success, usePage().props?.flash?.error],
  showFlash,
);

const viewerHint = computed(() => {
  const canManageAny = props.platforms.some((platform) => platform.can_manage);

  return canManageAny ? null : t('integrations.no_permission');
});
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
                <span class="badge mt-1" :class="statusVariant(platform).class">
                  {{ $t(`integrations.status.${statusVariant(platform).key}`) }}
                </span>
              </div>
            </div>

            <p class="text-muted fs-13 flex-grow-1 mb-2">
              {{ $t(`integrations.platforms.${platform.key}`) }}
            </p>

            <p
              v-if="platform.connection?.shop_slug || platform.connection?.store"
              class="fs-12 text-muted mb-3"
            >
              <span v-if="platform.connection.store">{{ platform.connection.store.name }}</span>
              <span v-if="platform.connection.shop_slug">
                <span v-if="platform.connection.store"> · </span>
                {{ platform.connection.shop_slug }}
              </span>
            </p>

            <div class="d-flex flex-column gap-2 mt-auto">
              <Link
                v-if="hrefFor(platform)"
                :href="hrefFor(platform)"
                class="btn btn-primary w-100"
              >
                <i class="ri-plug-line align-bottom me-1"></i>
                {{
                  platform.connection?.status === 'connected'
                    ? $t('integrations.configure')
                    : $t('integrations.activate')
                }}
              </Link>

              <BButton
                v-else
                variant="soft-primary"
                class="w-100"
                disabled
              >
                <i class="ri-plug-line align-bottom me-1"></i>
                {{ platform.available ? $t('integrations.activate') : $t('integrations.connect') }}
              </BButton>

              <BButton
                v-if="platform.can_manage && platform.connection?.id"
                variant="ghost-danger"
                class="w-100"
                :disabled="disconnecting === platform.connection.id"
                @click="disconnect(platform)"
              >
                <i class="ri-link-unlink-m align-bottom me-1"></i>
                {{ $t('integrations.disconnect') }}
              </BButton>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BAlert v-if="viewerHint" :model-value="true" variant="info" class="mt-3 mb-0">
      <i class="ri-information-line align-bottom me-1"></i>
      {{ viewerHint }}
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
