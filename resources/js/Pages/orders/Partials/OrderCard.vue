<script setup>
import { computed } from 'vue';
import { formatMoney as money } from '@/common/formatMoney';
import PaymentMethodBadge from '@/Components/PaymentMethodBadge.vue';
import FailureReasonBadge from '@/Components/FailureReasonBadge.vue';

/**
 * Order card for the mobile list of sellers and back-office staff.
 *
 * The desktop table trades a dozen columns against a small font, which does not
 * survive a phone: it becomes a horizontal scroll with the primary action off
 * screen. This card keeps the identifying and financial figures visible and
 * defers everything else to a bottom sheet opened by tapping it.
 */
const props = defineProps({
  order: { type: Object, required: true },
  /** Offer a checkbox so bulk export / label printing also works on mobile. */
  selectable: { type: Boolean, default: false },
  selected: { type: Boolean, default: false },
});

const emit = defineEmits(['open', 'toggle-select']);

const customer = computed(() => props.order.customer ?? {});

const collected = computed(() => {
  if (props.order.is_already_paid) {
    return { alreadyPaid: true, amount: money(0) };
  }

  return {
    alreadyPaid: false,
    amount: props.order.amount_to_collect != null ? money(props.order.amount_to_collect) : null,
  };
});

const createdAt = computed(() =>
  props.order.created_at ? new Date(props.order.created_at).toLocaleDateString() : null
);
</script>

<template>
  <div
    class="card order-card mb-3"
    :class="{ 'order-card-selected': selected }"
    role="button"
    :aria-label="order.tracking_number"
    @click="emit('open', order)"
  >
    <div class="card-body p-3">
      <div class="d-flex align-items-start gap-2">
        <input
          v-if="selectable"
          class="form-check-input mt-1 flex-shrink-0"
          type="checkbox"
          :checked="selected"
          :aria-label="order.tracking_number"
          @click.stop
          @change="emit('toggle-select', order)"
        />

        <div class="min-w-0 flex-grow-1">
          <div class="fw-semibold fs-14">{{ order.tracking_number }}</div>
          <div class="text-muted fs-12 mt-1 text-truncate">
            {{ customer.full_name }}
            <template v-if="order.city"> · {{ order.city.name }}</template>
          </div>
        </div>

        <div class="text-end flex-shrink-0">
          <span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
            {{ order.status_label }}
          </span>
          <div v-if="order.failure_reason" class="mt-1">
            <FailureReasonBadge :order="order" />
          </div>
        </div>
      </div>

      <div class="d-flex align-items-end justify-content-between gap-2 mt-3">
        <div class="min-w-0">
          <PaymentMethodBadge
            :label="order.payment_method_label"
            :emoji="order.payment_method_emoji"
            :color="order.payment_method_color"
          />
          <div v-if="createdAt" class="text-muted fs-11 mt-1">{{ createdAt }}</div>
        </div>

        <div class="text-end flex-shrink-0">
          <div class="text-muted fs-11 text-uppercase">{{ $t('orders.table.to_collect') }}</div>
          <div class="fw-semibold">
            {{ collected.amount ?? $t('common.empty_value') }}
            <span class="fs-11 text-muted">{{ $t('common.currency_mad') }}</span>
          </div>
          <span v-if="collected.alreadyPaid" class="badge bg-success-subtle text-success mt-1">
            {{ $t('orders.driver.already_paid') }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Elevation comes from the global `.card` rule; see EntityCard.vue. */
.order-card {
  border-radius: 0.85rem;
  cursor: pointer;
}

.order-card-selected {
  border-color: var(--vz-primary, #0d4a9d);
}

.min-w-0 {
  min-width: 0;
}
</style>
