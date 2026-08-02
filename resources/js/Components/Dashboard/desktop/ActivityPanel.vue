<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import PanelCard from './PanelCard.vue';
import { formatMoneyRounded } from '@/common/formatMoney';

/**
 * The last orders and the last events, sharing one panel and one tab bar.
 *
 * They are two views of the same stream — an order and the event that moved it
 * — and are never read at the same time, so giving each its own card would
 * spend twice the width to answer one question. Rows replace the eleven-column
 * table the detailed section still carries: the fields that decide whether to
 * open an order are the customer, where it is going, its status and its
 * amount, and the rest is a click away.
 */
defineProps({
  ordersTabLabel: { type: String, required: true },
  eventsTabLabel: { type: String, required: true },
  viewAllLabel: { type: String, required: true },
  ordersHref: { type: String, default: '/orders' },
  orders: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
  currency: { type: String, default: 'MAD' },
  emptyOrdersLabel: { type: String, required: true },
  emptyEventsLabel: { type: String, required: true },
  loading: { type: Boolean, default: false },
});

const tab = ref('orders');
</script>

<template>
  <PanelCard flush fill>
    <template #action>
      <div class="ddash-activity-head">
        <div class="ddash-activity-tabs" role="tablist">
          <button
            id="ddash-activity-tab-orders"
            type="button"
            role="tab"
            class="ddash-activity-tab"
            :class="{ 'ddash-activity-tab-active': tab === 'orders' }"
            :aria-selected="tab === 'orders'"
            aria-controls="ddash-activity-panel"
            @click="tab = 'orders'"
          >
            {{ ordersTabLabel }}
          </button>
          <button
            id="ddash-activity-tab-events"
            type="button"
            role="tab"
            class="ddash-activity-tab"
            :class="{ 'ddash-activity-tab-active': tab === 'events' }"
            :aria-selected="tab === 'events'"
            aria-controls="ddash-activity-panel"
            @click="tab = 'events'"
          >
            {{ eventsTabLabel }}
          </button>
        </div>

        <Link :href="ordersHref" class="ddash-activity-all">
          {{ viewAllLabel }}<i class="ri-arrow-right-s-line"></i>
        </Link>
      </div>
    </template>

    <div
      id="ddash-activity-panel"
      role="tabpanel"
      :aria-labelledby="`ddash-activity-tab-${tab}`"
    >
      <div v-if="loading" class="ddash-activity-skeletons">
        <div v-for="n in 4" :key="n" class="ddash-activity-skeleton" aria-hidden="true"></div>
      </div>

      <template v-else-if="tab === 'orders'">
        <p v-if="!orders.length" class="ddash-activity-empty">{{ emptyOrdersLabel }}</p>

        <ul v-else class="ddash-activity-list">
          <li v-for="order in orders" :key="order.id">
            <Link :href="`/orders/${order.id}`" class="ddash-activity-row">
              <span
                class="ddash-activity-icon"
                :class="`bg-${order.status_color}-subtle text-${order.status_color}`"
              >
                <i :class="order.status_icon || 'ri-box-3-line'"></i>
              </span>

              <span class="ddash-activity-main">
                <span class="ddash-activity-title">{{ order.customer_name || order.tracking_number }}</span>
                <span class="ddash-activity-subtitle">{{ order.tracking_number }}</span>
              </span>

              <span class="ddash-activity-tag">{{ order.destination_city || '—' }}</span>

              <span class="ddash-activity-amount">
                {{ formatMoneyRounded(order.amount) }}<span class="ddash-activity-currency">{{ currency }}</span>
              </span>

              <span class="ddash-activity-badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
                {{ order.status_label }}
              </span>

              <i class="ri-arrow-right-s-line ddash-activity-chevron"></i>
            </Link>
          </li>
        </ul>
      </template>

      <template v-else>
        <p v-if="!events.length" class="ddash-activity-empty">{{ emptyEventsLabel }}</p>

        <ul v-else class="ddash-activity-list">
          <li v-for="event in events" :key="event.id">
            <component
              :is="event.order_id ? Link : 'div'"
              :href="event.order_id ? `/orders/${event.order_id}` : undefined"
              class="ddash-activity-row"
            >
              <span
                class="ddash-activity-icon"
                :class="`bg-${event.status_color}-subtle text-${event.status_color}`"
              >
                <i :class="event.status_icon || 'ri-history-line'"></i>
              </span>

              <span class="ddash-activity-main">
                <span class="ddash-activity-title">{{ event.status_label }}</span>
                <span class="ddash-activity-subtitle">{{ event.tracking_number }}</span>
              </span>

              <span class="ddash-activity-tag">{{ event.actor_name }}</span>

              <span class="ddash-activity-time">{{ event.created_at_human }}</span>

              <i v-if="event.order_id" class="ri-arrow-right-s-line ddash-activity-chevron"></i>
            </component>
          </li>
        </ul>
      </template>
    </div>
  </PanelCard>
</template>

<style scoped>
/* The tab bar is the panel's whole header, so it claims the full width the
   card's header slot would otherwise share with a title. */
.ddash-activity-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 1rem;
}

.ddash-activity-tabs {
  display: flex;
  gap: 1.5rem;
}

.ddash-activity-tab {
  position: relative;
  padding: 0 0 0.625rem;
  border: 0;
  background: transparent;
  color: var(--ddash-muted, #878a99);
  font-size: 0.9375rem;
  font-weight: 600;
}

.ddash-activity-tab-active {
  color: var(--vz-heading-color, #495057);
}

/* Underlines only the label, the way the tab reads in the design, rather than
   the full-width border a Bootstrap nav would draw. */
.ddash-activity-tab-active::after {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 2px;
  border-radius: 2px;
  background-color: var(--vz-heading-color, #495057);
  content: '';
}

.ddash-activity-all {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  color: var(--vz-primary, #0d4a9d);
  font-size: 0.8125rem;
  font-weight: 600;
  text-decoration: none;
}

.ddash-activity-list {
  margin: 0;
  padding: 0 0.75rem;
  list-style: none;
}

.ddash-activity-row {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.625rem;
  border-radius: 0.875rem;
  color: inherit;
  text-decoration: none;
  transition: background-color 0.15s ease;
}

a.ddash-activity-row:hover {
  background-color: var(--vz-light, #f3f6f9);
}

.ddash-activity-icon {
  display: inline-flex;
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.875rem;
  font-size: 1.125rem;
}

.ddash-activity-main {
  display: flex;
  min-width: 0;
  flex: 1 1 0;
  flex-direction: column;
}

.ddash-activity-title,
.ddash-activity-subtitle,
.ddash-activity-tag,
.ddash-activity-time {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ddash-activity-title {
  color: var(--vz-heading-color, #495057);
  font-size: 0.875rem;
  font-weight: 600;
}

.ddash-activity-subtitle {
  color: var(--ddash-muted, #878a99);
  font-size: 0.75rem;
}

.ddash-activity-tag,
.ddash-activity-time {
  flex: 0 0 8rem;
  color: var(--ddash-muted, #878a99);
  font-size: 0.75rem;
}

.ddash-activity-time {
  flex-basis: 7rem;
  text-align: right;
}

.ddash-activity-amount {
  flex-shrink: 0;
  color: var(--vz-heading-color, #495057);
  font-size: 0.875rem;
  font-weight: 700;
  white-space: nowrap;
}

.ddash-activity-currency {
  margin-left: 0.1875rem;
  color: var(--ddash-muted, #878a99);
  font-size: 0.6875rem;
  font-weight: 500;
}

.ddash-activity-badge {
  overflow: hidden;
  max-width: 9rem;
  flex-shrink: 0;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ddash-activity-chevron {
  flex-shrink: 0;
  color: var(--ddash-muted, #878a99);
  font-size: 1.125rem;
}

.ddash-activity-empty {
  margin: 0;
  padding: 3rem 0;
  color: var(--ddash-muted, #878a99);
  font-size: 0.8125rem;
  text-align: center;
}

.ddash-activity-skeletons {
  padding: 0 1.375rem;
}

.ddash-activity-skeleton {
  height: 2.75rem;
  margin-bottom: 0.75rem;
  border-radius: 0.75rem;
  background-color: var(--vz-light, #f3f6f9);
  animation: ddash-pulse 1.4s ease-in-out infinite;
}

/* Tablet widths cannot hold the middle columns without squeezing the name. */
@media (max-width: 1399.98px) {
  .ddash-activity-tag {
    display: none;
  }
}

@keyframes ddash-pulse {
  50% {
    opacity: 0.5;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ddash-activity-skeleton {
    animation: none;
  }

  .ddash-activity-row {
    transition: none;
  }
}
</style>
