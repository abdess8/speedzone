<script setup>
import { Link } from '@inertiajs/vue3';
import SectionCard from './SectionCard.vue';
import { formatMoneyRounded } from '@/common/formatMoney';

/**
 * The last few orders, as rows instead of the desktop's eleven-column table.
 *
 * A table that wide can only be reached on a phone by scrolling sideways, which
 * hides the columns that identify the row. Each order becomes a row carrying
 * the four fields worth knowing before tapping — status, tracking, destination
 * and amount — and the rest waits on the detail screen.
 */
defineProps({
  title: { type: String, required: true },
  orders: { type: Array, default: () => [] },
  href: { type: String, default: '/orders' },
  currency: { type: String, default: 'MAD' },
  viewAllLabel: { type: String, required: true },
  emptyLabel: { type: String, required: true },
  loading: { type: Boolean, default: false },
});
</script>

<template>
  <SectionCard :title="title" flush>
    <template #action>
      <Link :href="href" class="mdash-recent-all">
        {{ viewAllLabel }}<i class="ri-arrow-right-s-line"></i>
      </Link>
    </template>

    <div v-if="loading" class="mdash-recent-skeletons">
      <div v-for="n in 4" :key="n" class="mdash-recent-skeleton" aria-hidden="true"></div>
    </div>

    <p v-else-if="!orders.length" class="mdash-recent-empty">{{ emptyLabel }}</p>

    <ul v-else class="mdash-recent-list">
      <li v-for="order in orders" :key="order.id">
        <Link :href="`/orders/${order.id}`" class="mdash-recent-row">
          <span
            class="mdash-recent-icon"
            :class="`bg-${order.status_color}-subtle text-${order.status_color}`"
          >
            <i :class="order.status_icon || 'ri-box-3-line'"></i>
          </span>

          <span class="mdash-recent-main">
            <span class="mdash-recent-title">{{ order.customer_name || order.tracking_number }}</span>
            <span class="mdash-recent-subtitle">
              {{ order.status_label }}<template v-if="order.destination_city"> · {{ order.destination_city }}</template>
            </span>
          </span>

          <span class="mdash-recent-side">
            <span class="mdash-recent-amount">
              {{ formatMoneyRounded(order.amount) }}<span class="mdash-recent-currency">{{ currency }}</span>
            </span>
            <span class="mdash-recent-payment">{{ order.payment_method_label }}</span>
          </span>
        </Link>
      </li>
    </ul>
  </SectionCard>
</template>

<style scoped>
.mdash-recent-all {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  color: var(--vz-primary, #0d4a9d);
  font-size: 0.75rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
}

.mdash-recent-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.mdash-recent-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 1.125rem;
  color: inherit;
  text-decoration: none;
}

.mdash-recent-icon {
  display: inline-flex;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.75rem;
  font-size: 1.0625rem;
}

.mdash-recent-main {
  display: flex;
  min-width: 0;
  flex-direction: column;
  flex-grow: 1;
}

.mdash-recent-title,
.mdash-recent-subtitle {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mdash-recent-title {
  color: var(--vz-heading-color, #495057);
  font-size: 0.8125rem;
  font-weight: 600;
}

.mdash-recent-subtitle {
  color: var(--mdash-muted);
  font-size: 0.6875rem;
}

.mdash-recent-side {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  align-items: flex-end;
}

.mdash-recent-amount {
  color: var(--vz-heading-color, #495057);
  font-size: 0.8125rem;
  font-weight: 700;
  white-space: nowrap;
}

.mdash-recent-currency {
  margin-left: 0.1875rem;
  color: var(--mdash-muted);
  font-size: 0.625rem;
  font-weight: 500;
}

.mdash-recent-payment {
  color: var(--mdash-muted);
  font-size: 0.6875rem;
}

.mdash-recent-empty {
  margin: 0;
  padding: 1.5rem 1.125rem;
  color: var(--mdash-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.mdash-recent-skeletons {
  padding: 0 1.125rem;
}

.mdash-recent-skeleton {
  height: 2.25rem;
  margin-bottom: 0.75rem;
  border-radius: 0.625rem;
  background-color: var(--vz-light, #f3f6f9);
  animation: mdash-pulse 1.4s ease-in-out infinite;
}

@keyframes mdash-pulse {
  50% {
    opacity: 0.5;
  }
}

@media (prefers-reduced-motion: reduce) {
  .mdash-recent-skeleton {
    animation: none;
  }
}
</style>
