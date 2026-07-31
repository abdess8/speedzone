<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { formatMoney as money } from '@/common/formatMoney';

/**
 * Ergonomic order card for the driver's mobile list.
 *
 * A data table forces horizontal scrolling on a phone and puts the primary
 * action behind a squint-sized icon. This card front-loads the three things a
 * driver acts on — where to go, who to call, how much to collect — and exposes a
 * single full-width primary button.
 */
const props = defineProps({
  order: { type: Object, required: true },
  /** Whether the quick status action should be offered at all. */
  canUpdateStatus: { type: Boolean, default: false },
  /** Transitions available from this order's current status. */
  transitions: { type: Array, default: () => [] },
});

const emit = defineEmits(['change-status']);

const customer = computed(() => props.order.customer ?? {});

const hasActions = computed(() => props.canUpdateStatus && props.transitions.length > 0);

const amountToCollect = computed(() =>
  props.order.amount_to_collect != null ? money(props.order.amount_to_collect) : null
);

/**
 * Hand the address to whichever maps app the device owns rather than hardcoding
 * a provider.
 */
const mapsUrl = computed(() => {
  const parts = [customer.value.address, props.order.sector?.name, props.order.city?.name]
    .filter(Boolean)
    .join(', ');

  return parts ? `https://maps.google.com/?q=${encodeURIComponent(parts)}` : null;
});
</script>

<template>
  <div class="card driver-card mb-3">
    <div class="card-body p-3">
      <div class="d-flex align-items-start justify-content-between gap-2">
        <div class="min-w-0">
          <Link :href="route('orders.show', order.id)" class="fw-semibold fs-15 text-body">
            {{ order.tracking_number }}
          </Link>
          <div class="text-muted fs-12 mt-1">
            {{ order.city?.name ?? '—' }}
            <template v-if="order.sector"> · {{ order.sector.name }}</template>
          </div>
        </div>
        <span
          class="badge flex-shrink-0"
          :class="`bg-${order.status_color}-subtle text-${order.status_color}`"
        >
          {{ order.status_label }}
        </span>
      </div>

      <hr class="my-3" />

      <div class="d-flex align-items-center justify-content-between gap-3">
        <div class="min-w-0">
          <div class="fw-medium text-truncate">{{ customer.full_name }}</div>
          <div v-if="customer.address" class="text-muted fs-12 driver-card-address">
            {{ customer.address }}
          </div>
        </div>
        <div v-if="amountToCollect" class="text-end flex-shrink-0">
          <div class="text-muted fs-11 text-uppercase">{{ $t('orders.table.to_collect') }}</div>
          <div class="fw-semibold fs-15">{{ amountToCollect }}</div>
        </div>
      </div>

      <div v-if="order.is_fragile || order.can_be_opened" class="mt-2">
        <span v-if="order.is_fragile" class="badge bg-danger-subtle text-danger me-1">
          {{ $t('orders.badges.fragile') }}
        </span>
        <span v-if="order.can_be_opened" class="badge bg-info-subtle text-info">
          {{ $t('orders.badges.openable') }}
        </span>
      </div>

      <!-- Contact shortcuts sized to the 44px minimum touch target. -->
      <div class="d-flex gap-2 mt-3">
        <a
          v-if="customer.phone"
          :href="`tel:${customer.phone}`"
          class="btn btn-soft-success flex-fill driver-card-action"
        >
          <i class="ri-phone-line align-bottom me-1"></i>{{ $t('orders.driver.call') }}
        </a>
        <a
          v-if="mapsUrl"
          :href="mapsUrl"
          target="_blank"
          rel="noopener"
          class="btn btn-soft-info flex-fill driver-card-action"
        >
          <i class="ri-navigation-line align-bottom me-1"></i>{{ $t('orders.driver.navigate') }}
        </a>
      </div>

      <button
        v-if="hasActions"
        type="button"
        class="btn btn-primary w-100 mt-2 driver-card-action"
        @click="emit('change-status', order)"
      >
        <i class="ri-refresh-line align-bottom me-1"></i>{{ $t('orders.driver.update_status') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.driver-card {
  border-radius: 0.85rem;
  box-shadow: 0 1px 2px rgba(56, 65, 74, 0.08);
}

.driver-card-address {
  /* Two lines is enough to recognise a street without pushing the CTA offscreen. */
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.driver-card-action {
  min-height: 44px;
}

.min-w-0 {
  min-width: 0;
}
</style>
