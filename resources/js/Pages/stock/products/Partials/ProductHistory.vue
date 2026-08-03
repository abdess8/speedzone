<script setup>
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * Field-level audit trail of a product sheet.
 *
 * Same table as the order modification history, for the same reason: "the price
 * was never 249" is an argument that only a before/after pair with a name and a
 * timestamp on it can settle.
 */
defineProps({
  /** @type {Array<Object>} rows as shaped by ProductController::show() */
  entries: { type: Array, default: () => [] },
});

const { t } = useI18n();
const page = usePage();

const formatDate = (value) => {
  if (!value) {
    return t('common.empty_value');
  }

  const locale = page.props.locale === 'en' ? 'en-GB' : 'fr-FR';

  return new Date(value).toLocaleString(locale, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const displayValue = (value) => (value != null && value !== '' ? value : t('common.empty_value'));
</script>

<template>
  <div v-if="entries.length === 0" class="text-center text-muted py-5">
    <div class="fs-32 mb-2 text-body-tertiary"><i class="ri-history-line"></i></div>
    <p class="mb-0">{{ $t('stock.history.empty') }}</p>
  </div>

  <div v-else class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>{{ $t('stock.history.columns.field') }}</th>
          <th>{{ $t('stock.history.columns.old_value') }}</th>
          <th>{{ $t('stock.history.columns.new_value') }}</th>
          <th>{{ $t('stock.history.columns.author') }}</th>
          <th>{{ $t('stock.history.columns.date') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="entry in entries" :key="entry.id">
          <td class="fw-medium">{{ entry.field_label }}</td>
          <td class="text-muted">{{ displayValue(entry.old_value) }}</td>
          <td class="fw-semibold">{{ displayValue(entry.new_value) }}</td>
          <td>{{ entry.author ?? $t('stock.history.system') }}</td>
          <td class="text-muted text-nowrap">{{ formatDate(entry.created_at) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
