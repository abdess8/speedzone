<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import ProductThumb from '../Partials/ProductThumb.vue';
import StockLevel from '../Partials/StockLevel.vue';
import ProductHistory from './Partials/ProductHistory.vue';
import ProductMovements from './Partials/ProductMovements.vue';
import ProductCounts from './Partials/ProductCounts.vue';
import ProductReceptions from './Partials/ProductReceptions.vue';
import { formatMoney as money } from '@/common/formatMoney';

/**
 * Product sheet: what the reference is, and everything that happened to it.
 *
 * The four logs live behind tabs on the same screen on purpose. "Who changed the
 * price", "who took twelve units out", "who last counted the shelf" and "is more
 * of it on the way" are asked in the same conversation, and splitting them
 * across four screens is what makes a stock dispute unresolvable.
 */

const { t } = useI18n();

const props = defineProps({
  product: { type: Object, required: true },
  detail: { type: Object, default: () => ({}) },
  history: { type: Array, default: () => [] },
  movements: { type: Array, default: () => [] },
  counts: { type: Array, default: () => [] },
  receptions: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const tab = ref('movements');

/** Slips still travelling, which is the only reception count worth badging. */
const incomingReceptions = computed(
  () => props.receptions.filter((reception) => reception.in_progress).length
);

const lastCountedAt = computed(() => props.counts[0]?.created_at ?? null);

const dimensions = computed(() => {
  const { length_cm: length, width_cm: width, height_cm: height } = props.detail;

  if ([length, width, height].every((value) => value === null || value === undefined)) {
    return null;
  }

  return [length, width, height].map((value) => value ?? '?').join(' × ') + ' cm';
});

const facts = computed(() =>
  [
    { label: t('stock.products.columns.sku'), value: props.product.sku },
    { label: t('stock.products.columns.barcode'), value: props.product.barcode },
    {
      label: t('stock.products.columns.category'),
      value: props.product.category ?? t('stock.products.no_category'),
    },
    { label: t('stock.products.columns.unit_price'), value: `${money(props.product.unit_price)} ${t('common.currency_mad')}` },
    {
      label: t('stock.products.columns.cost_price'),
      value: props.product.cost_price === null ? null : `${money(props.product.cost_price)} ${t('common.currency_mad')}`,
    },
    {
      label: t('stock.products.columns.margin'),
      value: props.product.margin === null ? null : `${money(props.product.margin)} ${t('common.currency_mad')}`,
    },
    {
      label: t('stock.products.detail.weight'),
      value: props.detail.weight_grams ? `${props.detail.weight_grams} g` : null,
    },
    { label: t('stock.products.detail.dimensions'), value: dimensions.value },
    // The one fact from the counting log that belongs next to the stock figure
    // rather than behind a tab: a quantity nobody has checked in months is a
    // different number from one verified this morning.
    {
      label: t('stock.counts.last_counted'),
      value: lastCountedAt.value ? new Date(lastCountedAt.value).toLocaleString() : null,
    },
  ].filter((fact) => fact.value !== null && fact.value !== undefined && fact.value !== '')
);

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t('common.empty_value'));

async function toggleBlock() {
  if (props.product.is_blocked) {
    const confirmation = await Swal.fire({
      icon: 'question',
      title: t('stock.products.block.release_title'),
      text: t('stock.products.block.release_text'),
      showCancelButton: true,
      confirmButtonText: t('stock.products.actions.release'),
      cancelButtonText: t('common.cancel'),
      customClass: { confirmButton: 'btn btn-success', cancelButton: 'btn btn-light' },
      buttonsStyling: false,
    });

    if (confirmation.isConfirmed) {
      router.put(route('products.block', props.product.id), { blocked: false }, { preserveScroll: true });
    }

    return;
  }

  const { isConfirmed, value } = await Swal.fire({
    icon: 'warning',
    title: t('stock.products.block.title'),
    text: t('stock.products.block.text'),
    input: 'textarea',
    inputLabel: t('stock.products.block.reason'),
    inputPlaceholder: t('stock.products.block.reason_placeholder'),
    inputValidator: (reason) => (String(reason ?? '').trim() === '' ? t('stock.products.block.reason') : undefined),
    showCancelButton: true,
    confirmButtonText: t('stock.products.block.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (isConfirmed) {
    router.put(
      route('products.block', props.product.id),
      { blocked: true, reason: value },
      { preserveScroll: true }
    );
  }
}

async function archive() {
  const confirmation = await Swal.fire({
    icon: 'warning',
    title: t('stock.products.archive.title'),
    text: t('stock.products.archive.text'),
    showCancelButton: true,
    confirmButtonText: t('stock.products.archive.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (confirmation.isConfirmed) {
    router.delete(route('products.destroy', props.product.id));
  }
}

onMounted(() => {
  const success = usePage().props?.flash?.success;

  if (success) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: success,
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
    });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="product.name" :pageTitle="$t('stock.products.title')" />

    <div v-if="product.is_blocked" class="alert alert-danger d-flex align-items-start gap-2">
      <i class="ri-shield-cross-line fs-18"></i>
      <div>
        <p class="mb-1 fw-semibold">
          {{ $t('stock.products.detail.blocked_banner', { reason: product.blocked_reason ?? '' }) }}
        </p>
        <p v-if="detail.blocked_by" class="mb-0 fs-13">
          {{ $t('stock.products.detail.blocked_by', { author: detail.blocked_by, date: formatDate(detail.blocked_at) }) }}
        </p>
      </div>
    </div>

    <BRow>
      <BCol xl="4">
        <BCard no-body>
          <BCardBody>
            <div class="d-flex align-items-center gap-3 mb-3">
              <ProductThumb
                :name="product.name"
                :photo-url="product.photo_url"
                :initials="product.initials"
                :size="72"
              />
              <div class="min-w-0">
                <h5 class="mb-1 text-truncate">{{ product.name }}</h5>
                <div class="d-flex flex-wrap gap-1">
                  <span v-if="product.is_fragile" class="badge bg-warning-subtle text-warning">
                    {{ $t('stock.products.badges.fragile') }}
                  </span>
                  <span v-if="!product.is_active" class="badge bg-secondary-subtle text-secondary">
                    {{ $t('stock.products.badges.archived') }}
                  </span>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-between align-items-center bg-light rounded p-3 mb-3">
              <span class="text-muted">{{ $t('stock.products.columns.stock') }}</span>
              <StockLevel
                :quantity="product.stock_quantity"
                :is-low-stock="product.is_low_stock"
                :is-out-of-stock="product.is_out_of_stock"
              />
            </div>

            <dl class="mb-0">
              <div
                v-for="fact in facts"
                :key="fact.label"
                class="d-flex justify-content-between align-items-start gap-3 py-2 fact-row"
              >
                <dt class="text-muted fw-normal fs-13">{{ fact.label }}</dt>
                <dd class="mb-0 text-end fs-13 fw-medium">{{ fact.value }}</dd>
              </div>
            </dl>

            <p v-if="detail.description" class="text-muted fs-13 mt-3 mb-0">{{ detail.description }}</p>
          </BCardBody>

          <BCardFooter class="d-flex flex-wrap gap-2">
            <Link v-if="can.update" :href="route('products.edit', product.id)" class="btn btn-sm btn-soft-secondary">
              <i class="ri-pencil-line align-bottom me-1"></i> {{ $t('common.edit') }}
            </Link>
            <Link v-if="can.adjust" :href="route('stock.inventory', { search: product.sku })" class="btn btn-sm btn-soft-warning">
              <i class="ri-list-check-2 align-bottom me-1"></i> {{ $t('stock.products.actions.adjust_stock') }}
            </Link>
            <button
              v-if="can.block"
              type="button"
              class="btn btn-sm"
              :class="product.is_blocked ? 'btn-soft-success' : 'btn-soft-danger'"
              @click="toggleBlock"
            >
              <i class="ri-shield-line align-bottom me-1"></i>
              {{ product.is_blocked ? $t('stock.products.actions.release') : $t('stock.products.actions.block') }}
            </button>
            <button
              v-if="can.delete && product.is_active"
              type="button"
              class="btn btn-sm btn-ghost-danger"
              @click="archive"
            >
              <i class="ri-archive-line align-bottom me-1"></i> {{ $t('stock.products.actions.archive') }}
            </button>
          </BCardFooter>
        </BCard>
      </BCol>

      <BCol xl="8">
        <BCard no-body>
          <BCardHeader class="p-0 border-bottom-0">
            <ul class="nav nav-tabs-custom card-header-tabs border-bottom-0" role="tablist">
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: tab === 'movements' }"
                  @click="tab = 'movements'"
                >
                  <i class="ri-swap-box-line align-bottom me-1"></i>
                  {{ $t('stock.products.detail.movements') }}
                  <span class="badge bg-light text-body ms-1">{{ movements.length }}</span>
                </button>
              </li>
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: tab === 'counts' }"
                  @click="tab = 'counts'"
                >
                  <i class="ri-list-check-2 align-bottom me-1"></i>
                  {{ $t('stock.counts.title') }}
                  <span class="badge bg-light text-body ms-1">{{ counts.length }}</span>
                </button>
              </li>
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: tab === 'receptions' }"
                  @click="tab = 'receptions'"
                >
                  <i class="ri-truck-line align-bottom me-1"></i>
                  {{ $t('stock.receptions.title') }}
                  <span
                    class="badge ms-1"
                    :class="incomingReceptions > 0 ? 'bg-info-subtle text-info' : 'bg-light text-body'"
                  >
                    {{ incomingReceptions > 0 ? incomingReceptions : receptions.length }}
                  </span>
                </button>
              </li>
              <li class="nav-item">
                <button
                  type="button"
                  class="nav-link"
                  :class="{ active: tab === 'history' }"
                  @click="tab = 'history'"
                >
                  <i class="ri-history-line align-bottom me-1"></i>
                  {{ $t('stock.history.title') }}
                  <span class="badge bg-light text-body ms-1">{{ history.length }}</span>
                </button>
              </li>
            </ul>
          </BCardHeader>

          <BCardBody>
            <ProductMovements v-if="tab === 'movements'" :movements="movements" />
            <ProductCounts v-else-if="tab === 'counts'" :counts="counts" />
            <ProductReceptions v-else-if="tab === 'receptions'" :receptions="receptions" />
            <ProductHistory v-else :entries="history" />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
.fact-row + .fact-row {
  border-top: 1px solid var(--vz-border-color, #e9ebec);
}
</style>
