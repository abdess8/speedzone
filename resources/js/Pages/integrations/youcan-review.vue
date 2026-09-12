<script setup>
import { onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import StepReview from '@/Pages/orders/Partials/import/StepReview.vue';
import { useOrderImport } from '@/composables/useOrderImport';

/**
 * Review table for a YouCan sync, same inline editor as bulk order import.
 *
 * Manual syncs land here with every fetched order still pending. Auto-syncs
 * that hit mapping errors reuse this screen for the failed rows of that run.
 */

const { t } = useI18n();

const props = defineProps({
  sync: { type: Object, required: true },
  integration: { type: Object, default: null },
  rows: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  sectors: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});

const importer = useOrderImport(props);
const { hydrateRows, validateAll, applyServerErrors, canSave, canVerify, payload, rows, errorCount, invalidRowCount } = importer;

const saving = ref(false);

hydrateRows(props.rows);

watch(
  () => props.rows,
  (records) => hydrateRows(records),
);

const showFlash = () => {
  const flash = usePage().props?.flash ?? {};
  const message = flash.success || flash.error;

  if (!message) {
    return;
  }

  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: flash.error ? 'error' : 'success',
    title: message,
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
  });
};

onMounted(() => {
  showFlash();
  validateAll();
});

function verify() {
  const clean = validateAll();

  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: clean ? 'success' : 'error',
    title: clean
      ? t('orders.import.review.verify_success', { count: rows.value.length })
      : t('orders.import.review.verify_failed', { count: invalidRowCount.value }),
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
  });
}

async function save() {
  if (!canSave.value) {
    return;
  }

  const confirmation = await Swal.fire({
    icon: 'question',
    title: t('orders.import.confirm.title'),
    text: t('orders.import.confirm.text', { count: rows.value.length }),
    showCancelButton: true,
    confirmButtonText: t('orders.import.confirm.confirm'),
    cancelButtonText: t('common.cancel'),
  });

  if (!confirmation.isConfirmed) {
    return;
  }

  saving.value = true;

  router.post(
    route('integrations.youcan.review.store', props.sync.id),
    { orders: payload() },
    {
      onError: (serverErrors) => {
        const mapped = applyServerErrors(serverErrors);

        Swal.fire({
          icon: 'error',
          title: t('orders.import.save_failed'),
          text: mapped
            ? t('orders.import.save_failed_rows', { count: mapped })
            : Object.values(serverErrors).flat().join('\n'),
        });
      },
      onFinish: () => {
        saving.value = false;
      },
    }
  );
}
</script>

<template>
  <Layout>
    <PageHeader
      :title="$t('integrations.sync.review.title', { id: sync.id })"
      :pageTitle="$t('integrations.youcan.page_title')"
    />

    <p class="text-muted fs-13 mb-3">
      {{ $t('integrations.sync.review.lead', { shop: integration?.shop_name || integration?.shop_slug || 'YouCan' }) }}
    </p>

    <StepReview :importer="importer" :payment-methods="paymentMethods" />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 my-4">
      <Link :href="route('integrations.youcan', { store_id: integration?.store_id })" class="btn btn-light">
        {{ $t('common.back') }}
      </Link>

      <div class="hstack gap-2">
        <BButton type="button" variant="warning" :disabled="!canVerify" @click="verify">
          <i class="ri-shield-check-line align-bottom me-1"></i>
          {{ $t('orders.import.verify') }}
        </BButton>
        <BButton type="button" variant="success" :disabled="!canSave || saving" @click="save">
          <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="ri-save-3-line align-bottom me-1"></i>
          {{ $t('orders.import.save', { count: rows.length }) }}
        </BButton>
      </div>
    </div>

    <p v-if="!canSave" class="text-muted text-center fs-13">
      <i class="ri-information-line align-bottom me-1"></i>
      {{
        errorCount > 0
          ? $t('orders.import.save_blocked_errors')
          : $t('orders.import.save_blocked_dirty')
      }}
    </p>
  </Layout>
</template>
