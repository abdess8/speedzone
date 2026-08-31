<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import BottomSheet from '@/Components/BottomSheet.vue';
import PaymentMethodBadge from '@/Components/PaymentMethodBadge.vue';
import FailureReasonBadge from '@/Components/FailureReasonBadge.vue';
import { formatMoney as money } from '@/common/formatMoney';
import { telUrl, whatsAppUrl } from '@/common/phone';
import { useI18n } from 'vue-i18n';

/**
 * Order summary for the mobile list, opened from a card.
 *
 * Rendered from the row already in memory, so tapping a card costs no request.
 * It answers the questions a seller asks about his own order; the full screen
 * (timeline, invoice, change history) stays one tap away for whoever may see it.
 */
const props = defineProps({
  show: { type: Boolean, default: false },
  order: { type: Object, default: null },
  /** UI capability flags from the controller: view_details, print. */
  can: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'print']);

const { t } = useI18n();

const customer = computed(() => props.order?.customer ?? {});

const callLink = computed(() => telUrl(customer.value.phone));

const whatsAppLink = computed(() =>
  props.order
    ? whatsAppUrl(
        customer.value.phone,
        t('orders.driver.whatsapp_message', {
          name: customer.value.full_name ?? '',
          tracking: props.order.tracking_number,
        })
      )
    : null
);

const amountToCollect = computed(() => {
  if (!props.order) {
    return null;
  }

  if (props.order.is_already_paid) {
    return t('orders.driver.already_paid');
  }

  return props.order.amount_to_collect != null
    ? `${money(props.order.amount_to_collect)} ${t('common.currency_mad')}`
    : t('common.empty_value');
});

/** Label / value pairs, so the sheet stays a single readable column. */
const rows = computed(() => {
  if (!props.order) {
    return [];
  }

  const order = props.order;

  return [
    { label: t('orders.show.delivery_city'), value: order.city?.name },
    { label: t('orders.show.delivery_sector'), value: order.sector?.name },
    { label: t('orders.show.address'), value: customer.value.address },
    { label: t('orders.table.to_collect'), value: amountToCollect.value },
    {
      label: t('orders.table.order_value'),
      value: order.order_value != null ? `${money(order.order_value)} ${t('common.currency_mad')}` : null,
    },
    {
      label: t('orders.table.delivery'),
      value: `${money(order.delivery_price)} ${t('common.currency_mad')}`,
    },
    {
      label: t('orders.table.total'),
      value: `${money(order.total_amount)} ${t('common.currency_mad')}`,
    },
    {
      label: t('orders.table.created'),
      value: order.created_at ? new Date(order.created_at).toLocaleString() : null,
    },
  ].filter((row) => row.value);
});
</script>

<template>
  <BottomSheet
    :show="show"
    :title="order?.tracking_number"
    :subtitle="customer.full_name"
    @close="emit('close')"
  >
    <template v-if="order">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
          {{ order.status_label }}
        </span>
        <FailureReasonBadge :order="order" />
        <PaymentMethodBadge
          :label="order.payment_method_label"
          :emoji="order.payment_method_emoji"
          :color="order.payment_method_color"
        />
        <span v-if="order.is_fragile" class="badge bg-danger-subtle text-danger">
          {{ $t('orders.badges.fragile') }}
        </span>
        <span v-if="order.can_be_opened" class="badge bg-info-subtle text-info">
          {{ $t('orders.badges.openable') }}
        </span>
      </div>

      <div class="d-flex gap-2 mb-3">
        <a v-if="callLink" :href="callLink" class="btn btn-soft-success flex-fill sheet-action">
          <i class="ri-phone-line align-bottom me-1"></i>{{ $t('orders.driver.call') }}
        </a>
        <a
          v-if="whatsAppLink"
          :href="whatsAppLink"
          target="_blank"
          rel="noopener"
          class="btn btn-soft-success flex-fill sheet-action"
        >
          <i class="ri-whatsapp-line align-bottom me-1"></i>{{ $t('orders.driver.whatsapp') }}
        </a>
      </div>

      <dl class="mb-0">
        <div v-for="row in rows" :key="row.label" class="d-flex justify-content-between gap-3 py-2 sheet-row">
          <dt class="text-muted fw-normal fs-13">{{ row.label }}</dt>
          <dd class="mb-0 text-end fs-13 fw-medium">{{ row.value }}</dd>
        </div>
      </dl>
    </template>

    <template #footer>
      <div class="d-flex gap-2">
        <button
          v-if="can.print"
          type="button"
          class="btn btn-light sheet-action"
          :title="$t('orders.actions.print_label')"
          @click="emit('print', order)"
        >
          <i class="ri-printer-line align-bottom"></i>
        </button>
        <Link
          v-if="can.view_details && order"
          :href="route('orders.show', order.id)"
          class="btn btn-primary flex-fill sheet-action d-flex align-items-center justify-content-center"
        >
          {{ $t('orders.show.open_full_view') }}
          <i class="ri-arrow-right-line align-bottom ms-1"></i>
        </Link>
        <button v-else type="button" class="btn btn-light flex-fill sheet-action" @click="emit('close')">
          {{ $t('common.close') }}
        </button>
      </div>
    </template>
  </BottomSheet>
</template>

<style scoped>
.sheet-action {
  min-height: 48px;
}

.sheet-row + .sheet-row {
  border-top: 1px solid var(--vz-border-color, #e9ebec);
}
</style>
