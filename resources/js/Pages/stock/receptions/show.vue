<script setup>
import { computed, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import StatusTimeline from '@/Components/StatusTimeline.vue';
import ProductThumb from '../Partials/ProductThumb.vue';
import ReceptionCollectSheet from './Partials/ReceptionCollectSheet.vue';
import ReceptionCountSheet from './Partials/ReceptionCountSheet.vue';

const props = defineProps({
  reception: { type: Object, required: true },
  can: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const isCounted = computed(() => props.reception.status === 'VALIDATED');

/** Once a collector has signed, his figure is the one the depot is held to. */
const wasCollected = computed(() => props.reception.totals.collected !== null);

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : t('common.empty_value'));
const formatMoment = (value) => (value ? new Date(value).toLocaleString() : t('common.empty_value'));

const facts = computed(() => [
  { label: t('stock.receptions.columns.seller'), value: props.reception.seller },
  { label: t('stock.receptions.people.sent_by'), value: props.reception.sender },
  {
    label: t('stock.receptions.columns.pickup_city'),
    value: props.reception.pickup_city ?? t('common.empty_value'),
  },
  {
    label: t('stock.receptions.columns.destination'),
    value: props.reception.destination_city ?? t('common.empty_value'),
  },
  { label: t('stock.receptions.columns.sent_at'), value: formatDate(props.reception.sent_at) },
  {
    label: t('stock.receptions.people.collected_by'),
    value: props.reception.collector ?? t('stock.receptions.people.pending'),
  },
  { label: t('stock.receptions.columns.collected_at'), value: formatMoment(props.reception.collected_at) },
  {
    label: t('stock.receptions.people.received_by'),
    value: props.reception.receiver ?? t('stock.receptions.people.pending'),
  },
  { label: t('stock.receptions.columns.received_at'), value: formatDate(props.reception.received_at) },
]);

const send = async () => {
  const confirmed = await Swal.fire({
    title: t('stock.receptions.send_confirm.title'),
    text: t('stock.receptions.send_confirm.text'),
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: t('stock.receptions.send_confirm.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-success', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (confirmed.isConfirmed) {
    router.put(route('stock-receptions.send', props.reception.id), {}, { preserveScroll: true });
  }
};

const dispatchToDepot = async () => {
  const confirmed = await Swal.fire({
    title: t('stock.receptions.dispatch_confirm.title'),
    text: t('stock.receptions.dispatch_confirm.text', {
      city: props.reception.destination_city ?? t('common.empty_value'),
    }),
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: t('stock.receptions.dispatch_confirm.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (confirmed.isConfirmed) {
    router.put(route('stock-receptions.dispatch', props.reception.id), {}, { preserveScroll: true });
  }
};

const cancel = async () => {
  const confirmed = await Swal.fire({
    title: t('stock.receptions.cancel_confirm.title'),
    text: t('stock.receptions.cancel_confirm.text'),
    icon: 'warning',
    input: 'textarea',
    inputLabel: t('stock.receptions.cancel_confirm.reason'),
    inputAttributes: { maxlength: 2000 },
    showCancelButton: true,
    confirmButtonText: t('stock.receptions.cancel_confirm.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (confirmed.isConfirmed) {
    router.put(
      route('stock-receptions.cancel', props.reception.id),
      { reason: confirmed.value || null },
      { preserveScroll: true }
    );
  }
};

onMounted(() => {
  const success = usePage().props?.flash?.success;

  if (success) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: success,
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true,
    });
  }
});
</script>

<template>
  <Layout>
    <PageHeader
      :title="$t('stock.receptions.detail_title', { reference: reception.reference })"
      :pageTitle="$t('stock.receptions.title')"
    />

    <BRow>
      <BCol xl="8">
        <BCard no-body>
          <BCardHeader class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
              <h5 class="card-title mb-0">{{ reception.reference }}</h5>
              <span class="badge" :class="`bg-${reception.status_color}-subtle text-${reception.status_color}`">
                <i :class="`${reception.status_icon} align-bottom me-1`"></i>{{ reception.status_label }}
              </span>
            </div>

            <div class="hstack gap-2">
              <Link
                v-if="can.update"
                :href="route('stock-receptions.edit', reception.id)"
                class="btn btn-sm btn-soft-primary"
              >
                <i class="ri-pencil-line align-bottom me-1"></i>{{ $t('stock.receptions.actions.edit') }}
              </Link>
              <BButton v-if="can.send" variant="success" size="sm" @click="send">
                <i class="ri-time-line align-bottom me-1"></i>{{ $t('stock.receptions.actions.send') }}
              </BButton>
              <BButton v-if="can.dispatch" variant="primary" size="sm" @click="dispatchToDepot">
                <i class="ri-route-line align-bottom me-1"></i>{{ $t('stock.receptions.actions.dispatch') }}
              </BButton>
              <BButton v-if="can.cancel" variant="soft-danger" size="sm" @click="cancel">
                <i class="ri-close-circle-line align-bottom me-1"></i>{{ $t('stock.receptions.actions.cancel') }}
              </BButton>
            </div>
          </BCardHeader>

          <BCardBody>
            <div class="table-responsive">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ $t('stock.receptions.columns.product') }}</th>
                    <th class="text-end">{{ $t('stock.receptions.columns.sent') }}</th>
                    <th class="text-end">{{ $t('stock.receptions.columns.collected') }}</th>
                    <th class="text-end">{{ $t('stock.receptions.columns.received') }}</th>
                    <th class="text-end">{{ $t('stock.receptions.columns.rejected') }}</th>
                    <th class="text-center">{{ $t('stock.receptions.columns.discrepancy') }}</th>
                    <th>{{ $t('stock.receptions.columns.note') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in reception.items" :key="item.id">
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <ProductThumb
                          :name="item.name"
                          :photo-url="item.photo_url"
                          :initials="item.initials"
                          :size="36"
                        />
                        <div class="min-w-0">
                          <Link
                            :href="route('products.show', item.product_id)"
                            class="d-block fw-medium text-body text-truncate"
                          >
                            {{ item.name }}
                          </Link>
                          <span class="d-block text-muted fs-12">{{ item.sku }}</span>
                        </div>
                      </div>
                    </td>
                    <td class="text-end fw-medium">{{ item.quantity_sent }}</td>
                    <td class="text-end">
                      <span v-if="item.quantity_collected === null" class="text-muted">
                        {{ $t('stock.receptions.people.pending') }}
                      </span>
                      <span
                        v-else
                        :class="item.collection_gap === 0 ? 'text-info fw-medium' : 'text-danger fw-medium'"
                      >
                        {{ item.quantity_collected }}
                      </span>
                    </td>
                    <td class="text-end">
                      <span v-if="item.quantity_received === null" class="text-muted">
                        {{ $t('stock.receptions.people.pending') }}
                      </span>
                      <span v-else class="text-success fw-medium">{{ item.quantity_received }}</span>
                    </td>
                    <td class="text-end">
                      <span v-if="item.quantity_rejected === null" class="text-muted">—</span>
                      <span v-else :class="item.quantity_rejected > 0 ? 'text-danger fw-medium' : 'text-muted'">
                        {{ item.quantity_rejected }}
                      </span>
                    </td>
                    <td class="text-center">
                      <span
                        v-if="item.discrepancy === null"
                        class="text-muted"
                      >—</span>
                      <span
                        v-else
                        class="badge"
                        :class="item.discrepancy === 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
                      >
                        {{ item.discrepancy > 0 ? `+${item.discrepancy}` : item.discrepancy }}
                      </span>
                    </td>
                    <td class="text-muted text-wrap">{{ item.note ?? '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>

        <ReceptionCollectSheet v-if="can.collect" :reception="reception" />
        <ReceptionCountSheet v-if="can.receive" :reception="reception" />

        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('stock.receptions.sections.history') }}</h5>
          </BCardHeader>
          <BCardBody>
            <StatusTimeline
              :history="reception.status_history ?? []"
              empty-key="stock.receptions.history.empty"
            />
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="4">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('stock.receptions.sections.summary') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('stock.receptions.columns.sent') }}</span>
              <span class="fw-medium">{{ reception.totals.sent }}</span>
            </div>
            <div v-if="wasCollected" class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('stock.receptions.columns.collected') }}</span>
              <span class="fw-medium text-info">{{ reception.totals.collected }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('stock.receptions.columns.received') }}</span>
              <span class="fw-medium text-success">{{ reception.totals.received }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('stock.receptions.columns.rejected') }}</span>
              <span class="fw-medium text-danger">{{ reception.totals.rejected }}</span>
            </div>
            <div
              v-if="isCounted && reception.totals.unaccounted !== 0"
              class="d-flex justify-content-between border-top pt-2"
            >
              <span class="text-muted">{{ $t('stock.receptions.columns.discrepancy') }}</span>
              <span class="fw-semibold text-danger">{{ reception.totals.unaccounted }}</span>
            </div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('stock.receptions.sections.shipping') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div v-for="fact in facts" :key="fact.label" class="d-flex justify-content-between gap-3 mb-2">
              <span class="text-muted flex-shrink-0">{{ fact.label }}</span>
              <span class="text-end fw-medium">{{ fact.value ?? $t('common.empty_value') }}</span>
            </div>

            <div v-if="reception.sending_notes" class="border-top mt-3 pt-3">
              <p class="text-muted fs-12 mb-1">{{ $t('stock.receptions.form.sending_notes') }}</p>
              <p class="mb-0">{{ reception.sending_notes }}</p>
            </div>

            <div v-if="reception.collection_notes" class="border-top mt-3 pt-3">
              <p class="text-muted fs-12 mb-1">{{ $t('stock.receptions.collection_form.collection_notes') }}</p>
              <p class="mb-0">{{ reception.collection_notes }}</p>
            </div>

            <div v-if="reception.reception_notes" class="border-top mt-3 pt-3">
              <p class="text-muted fs-12 mb-1">{{ $t('stock.receptions.reception_form.reception_notes') }}</p>
              <p class="mb-0">{{ reception.reception_notes }}</p>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
.min-w-0 {
  min-width: 0;
}
</style>
