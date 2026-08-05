<script setup>
import { useI18n } from 'vue-i18n';

/**
 * Every inventory confirmation on this reference, newest first.
 *
 * Deliberately separate from the movement ledger next door. The ledger answers
 * "what changed and why"; this answers "who last verified it, from where, and
 * did they find anything" — including the counts that found nothing, which the
 * ledger never sees and which are the majority of any inventory.
 *
 * The machine and the position are shown because that is what a disputed count
 * comes down to: two people each certain they counted the same shelf.
 */
defineProps({
  /** @type {Array<Object>} rows as shaped by ProductController::inventoryCounts() */
  counts: { type: Array, default: () => [] },
});

const { t } = useI18n();

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t('common.empty_value'));

const signed = (delta) => (delta > 0 ? `+${delta}` : String(delta));

const coordinates = (count) =>
  count.latitude === null || count.longitude === null
    ? null
    : `${Number(count.latitude).toFixed(5)}, ${Number(count.longitude).toFixed(5)}`;

/** Opens the coordinates where anyone can recognise them: on a map. */
const mapUrl = (count) =>
  `https://www.google.com/maps/search/?api=1&query=${count.latitude},${count.longitude}`;
</script>

<template>
  <div v-if="counts.length === 0" class="text-center text-muted py-5">
    <div class="fs-32 mb-2 text-body-tertiary"><i class="ri-list-check-2"></i></div>
    <p class="mb-0">{{ $t('stock.products.detail.counts_empty') }}</p>
  </div>

  <div v-else class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="text-nowrap">{{ $t('stock.counts.columns.date') }}</th>
          <th>{{ $t('stock.counts.columns.author') }}</th>
          <th class="text-end">{{ $t('stock.counts.columns.recorded') }}</th>
          <th class="text-end">{{ $t('stock.counts.columns.counted') }}</th>
          <th class="text-end">{{ $t('stock.counts.columns.result') }}</th>
          <th>{{ $t('stock.counts.columns.device') }}</th>
          <th>{{ $t('stock.counts.columns.location') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="count in counts" :key="count.id">
          <td class="text-muted fs-13 text-nowrap">{{ formatDate(count.created_at) }}</td>
          <td>{{ count.author ?? $t('stock.history.system') }}</td>
          <td class="text-end text-muted">{{ count.stock_before }}</td>
          <td class="text-end fw-medium">{{ count.counted_quantity }}</td>
          <td class="text-end">
            <span v-if="count.delta === 0" class="badge bg-success-subtle text-success">
              {{ $t('stock.counts.confirmed') }}
            </span>
            <span
              v-else
              class="badge"
              :class="count.delta < 0 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning'"
            >
              {{ signed(count.delta) }}
            </span>
          </td>
          <td>
            <span class="fs-13">{{ count.device ?? $t('common.empty_value') }}</span>
            <div v-if="count.ip_address" class="text-muted fs-12">{{ count.ip_address }}</div>
          </td>
          <td>
            <template v-if="coordinates(count)">
              <a :href="mapUrl(count)" target="_blank" rel="noopener" class="fs-13">
                <i class="ri-map-pin-2-line align-bottom me-1"></i>{{ coordinates(count) }}
              </a>
              <div v-if="count.location_accuracy_m" class="text-muted fs-12">
                {{ $t('stock.counts.accuracy', { meters: count.location_accuracy_m }) }}
              </div>
            </template>
            <span v-else class="text-muted fs-13">{{ $t('stock.counts.no_location') }}</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
