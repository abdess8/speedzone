<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * Inbound shipments carrying this reference, the travelling ones first.
 *
 * A vendor looking at a shelf that is nearly empty asks one question before any
 * other: is more of it already on its way? The three quantities are shown side
 * by side rather than collapsed into one, because a slip where ten were
 * declared and eight collected is already telling him something.
 */
const props = defineProps({
  /** @type {Array<Object>} rows as shaped by ProductController::receptions() */
  receptions: { type: Array, default: () => [] },
});

const { t } = useI18n();

const inProgress = computed(() => props.receptions.filter((reception) => reception.in_progress));
const closed = computed(() => props.receptions.filter((reception) => !reception.in_progress));

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : t('common.empty_value'));

const pending = (value) => (value === null ? '—' : value);

/** Units this slip still owes the shelf, once collected and rejected are known. */
const incoming = (reception) =>
  reception.in_progress ? (reception.quantity_collected ?? reception.quantity_sent) : reception.quantity_received;
</script>

<template>
  <div v-if="receptions.length === 0" class="text-center text-muted py-5">
    <div class="fs-32 mb-2 text-body-tertiary"><i class="ri-truck-line"></i></div>
    <p class="mb-0">{{ $t('stock.products.detail.receptions_empty') }}</p>
  </div>

  <template v-else>
    <div v-if="inProgress.length" class="alert alert-info d-flex align-items-center gap-2 py-2">
      <i class="ri-truck-line fs-18"></i>
      <span class="fs-13">
        {{
          $t('stock.products.detail.receptions_incoming', {
            count: inProgress.length,
            units: inProgress.reduce((total, reception) => total + incoming(reception), 0),
          })
        }}
      </span>
    </div>

    <div class="table-responsive">
      <table class="table align-middle table-nowrap mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ $t('stock.receptions.columns.reference') }}</th>
            <th>{{ $t('stock.receptions.columns.status') }}</th>
            <th>{{ $t('stock.receptions.columns.destination') }}</th>
            <th class="text-end">{{ $t('stock.receptions.columns.sent') }}</th>
            <th class="text-end">{{ $t('stock.receptions.columns.collected') }}</th>
            <th class="text-end">{{ $t('stock.receptions.columns.received') }}</th>
            <th>{{ $t('stock.receptions.columns.sent_at') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="reception in [...inProgress, ...closed]"
            :key="reception.id"
            :class="{ 'table-active': reception.in_progress }"
          >
            <td>
              <Link :href="route('stock-receptions.show', reception.id)" class="fw-medium">
                {{ reception.reference }}
              </Link>
            </td>
            <td>
              <span class="badge" :class="`bg-${reception.status_color}-subtle text-${reception.status_color}`">
                <i :class="`${reception.status_icon} align-bottom me-1`"></i>{{ reception.status_label }}
              </span>
            </td>
            <td>{{ reception.destination_city ?? $t('common.empty_value') }}</td>
            <td class="text-end">{{ reception.quantity_sent }}</td>
            <td class="text-end">{{ pending(reception.quantity_collected) }}</td>
            <td class="text-end fw-medium">
              {{ reception.in_progress ? '—' : reception.quantity_received }}
              <span v-if="reception.quantity_rejected > 0" class="text-danger fs-12 ms-1">
                (-{{ reception.quantity_rejected }})
              </span>
            </td>
            <td class="text-muted fs-13">{{ formatDate(reception.sent_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </template>
</template>
