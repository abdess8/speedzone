<script setup>
import { useI18n } from 'vue-i18n';

/**
 * Stock ledger of one reference, newest first.
 *
 * Every line states the same four things a stock dispute turns on: who, when,
 * from what to what, and why. `delta` is signed and coloured because the
 * direction is what the reader is scanning for.
 */
defineProps({
  /** @type {Array<Object>} rows as shaped by ProductController::movements() */
  movements: { type: Array, default: () => [] },
});

const { t } = useI18n();

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t('common.empty_value'));

const signed = (delta) => (delta > 0 ? `+${delta}` : String(delta));
</script>

<template>
  <div v-if="movements.length === 0" class="text-center text-muted py-5">
    <div class="fs-32 mb-2 text-body-tertiary"><i class="ri-swap-box-line"></i></div>
    <p class="mb-0">{{ $t('stock.products.detail.movements_empty') }}</p>
  </div>

  <div v-else class="table-responsive">
    <table class="table align-middle table-nowrap mb-0">
      <thead class="table-light">
        <tr>
          <th>{{ $t('stock.movements.columns.date') }}</th>
          <th>{{ $t('stock.movements.columns.source') }}</th>
          <th>{{ $t('stock.movements.columns.reason') }}</th>
          <th class="text-end">{{ $t('stock.movements.columns.before') }}</th>
          <th class="text-end">{{ $t('stock.movements.columns.delta') }}</th>
          <th class="text-end">{{ $t('stock.movements.columns.after') }}</th>
          <th>{{ $t('stock.movements.columns.author') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="movement in movements" :key="movement.id">
          <td class="text-muted fs-13">{{ formatDate(movement.created_at) }}</td>
          <td>
            <span class="badge" :class="`bg-${movement.source_color}-subtle text-${movement.source_color}`">
              <i :class="`${movement.source_icon} align-bottom me-1`"></i>{{ movement.source_label }}
            </span>
            <!-- The document behind the movement, when there is one: a credit
                 that cannot be traced back to a slip is not auditable. -->
            <span v-if="movement.reception" class="text-muted fs-12 ms-1">{{ movement.reception }}</span>
            <span v-else-if="movement.order" class="text-muted fs-12 ms-1">{{ movement.order }}</span>
          </td>
          <td>
            <span
              v-if="movement.reason_label"
              class="badge"
              :class="`bg-${movement.reason_color}-subtle text-${movement.reason_color}`"
            >
              {{ movement.reason_label }}
            </span>
            <span v-else class="text-muted">{{ $t('common.empty_value') }}</span>
            <div v-if="movement.note" class="text-muted fs-12 text-wrap">{{ movement.note }}</div>
          </td>
          <td class="text-end text-muted">{{ movement.stock_before }}</td>
          <td class="text-end fw-semibold" :class="movement.delta < 0 ? 'text-danger' : 'text-success'">
            {{ signed(movement.delta) }}
          </td>
          <td class="text-end fw-medium">{{ movement.stock_after }}</td>
          <td>{{ movement.author ?? $t('stock.history.system') }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
