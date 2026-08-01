<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatMoney as money } from '@/common/formatMoney';
import { telUrl, whatsAppUrl } from '@/common/phone';

/**
 * Ergonomic order card for the driver's mobile list.
 *
 * This card is the driver's *only* view of an order: he is not allowed to open
 * the detail screen, which also exposes the seller, the billing trail and the
 * change history. Everything he acts on therefore has to be here — where to go,
 * who to reach, how much to collect, what to do next — and it has to stay
 * reachable with one thumb.
 */
const props = defineProps({
  order: { type: Object, required: true },
  /** Whether the quick status action should be offered at all. */
  canUpdateStatus: { type: Boolean, default: false },
  /** Transitions available from this order's current status. */
  transitions: { type: Array, default: () => [] },
  /** Whether a return may be initiated from this order's current status. */
  canCreateReturn: { type: Boolean, default: false },
});

const emit = defineEmits(['change-status', 'create-return']);

const { t } = useI18n();

const customer = computed(() => props.order.customer ?? {});

const hasStatusActions = computed(() => props.canUpdateStatus && props.transitions.length > 0);

/**
 * Cash the driver has to bring back.
 *
 * A blank amount reads as missing data, so a card-paid order says so outright
 * and still shows a zero: the driver must know there is nothing to ask for.
 */
const collect = computed(() => {
  if (props.order.is_already_paid) {
    return { alreadyPaid: true, amount: money(0) };
  }

  return {
    alreadyPaid: false,
    amount: props.order.amount_to_collect != null ? money(props.order.amount_to_collect) : null,
  };
});

const destination = computed(() =>
  [customer.value.address, props.order.sector?.name, props.order.city?.name]
    .filter(Boolean)
    .join(', ')
);

const callLink = computed(() => telUrl(customer.value.phone));

/** Hand the address to whichever maps app the device owns rather than hardcoding a provider. */
const mapsUrl = computed(() =>
  destination.value ? `https://maps.google.com/?q=${encodeURIComponent(destination.value)}` : null
);

const whatsAppLink = computed(() =>
  whatsAppUrl(
    customer.value.phone,
    t('orders.driver.whatsapp_message', {
      name: customer.value.full_name ?? '',
      tracking: props.order.tracking_number,
    })
  )
);
</script>

<template>
  <div class="card driver-card mb-3">
    <div class="card-body p-3">
      <div class="d-flex align-items-start justify-content-between gap-2">
        <div class="min-w-0">
          <!-- Plain text, not a link: the detail screen is out of bounds here. -->
          <div class="fw-semibold fs-15">{{ order.tracking_number }}</div>
          <div class="text-muted fs-12 mt-1">
            <i class="ri-map-pin-2-line align-bottom"></i>
            {{ order.city?.name ?? $t('common.empty_value') }}
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

      <div class="d-flex align-items-start justify-content-between gap-3">
        <div class="min-w-0">
          <div class="fw-medium text-truncate">{{ customer.full_name }}</div>
          <a
            v-if="callLink"
            :href="callLink"
            class="d-inline-block fs-13 text-body text-decoration-underline"
          >
            {{ customer.phone }}
          </a>
          <div v-if="customer.address" class="text-muted fs-12 driver-card-address">
            {{ customer.address }}
          </div>
        </div>

        <div class="text-end flex-shrink-0">
          <div class="text-muted fs-11 text-uppercase">{{ $t('orders.table.to_collect') }}</div>
          <div class="fw-semibold fs-15">
            {{ collect.amount ?? $t('common.empty_value') }}
            <span class="fs-11 text-muted">{{ $t('common.currency_mad') }}</span>
          </div>
          <span v-if="collect.alreadyPaid" class="badge bg-success-subtle text-success mt-1">
            <i class="ri-checkbox-circle-line align-bottom me-1"></i>{{ $t('orders.driver.already_paid') }}
          </span>
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
          v-if="callLink"
          :href="callLink"
          class="btn btn-soft-success flex-fill driver-card-action"
        >
          <i class="ri-phone-line align-bottom me-1"></i>{{ $t('orders.driver.call') }}
        </a>
        <a
          v-if="whatsAppLink"
          :href="whatsAppLink"
          target="_blank"
          rel="noopener"
          class="btn btn-soft-success flex-fill driver-card-action"
        >
          <i class="ri-whatsapp-line align-bottom me-1"></i>{{ $t('orders.driver.whatsapp') }}
        </a>
        <a
          v-if="mapsUrl"
          :href="mapsUrl"
          target="_blank"
          rel="noopener"
          class="btn btn-soft-info driver-card-action"
          :title="$t('orders.driver.navigate')"
          :aria-label="$t('orders.driver.navigate')"
        >
          <i class="ri-navigation-line align-bottom"></i>
        </a>
      </div>

      <button
        v-if="hasStatusActions"
        type="button"
        class="btn btn-primary w-100 mt-2 driver-card-action"
        @click="emit('change-status', order)"
      >
        <i class="ri-refresh-line align-bottom me-1"></i>{{ $t('orders.driver.update_status') }}
      </button>

      <button
        v-if="canCreateReturn"
        type="button"
        class="btn btn-soft-warning w-100 mt-2 driver-card-action"
        @click="emit('create-return', order)"
      >
        <i class="ri-arrow-go-back-line align-bottom me-1"></i>{{ $t('orders.driver.create_return') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
/* Elevation comes from the global `.card` rule; see EntityCard.vue. */
.driver-card {
  border-radius: 0.85rem;
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
