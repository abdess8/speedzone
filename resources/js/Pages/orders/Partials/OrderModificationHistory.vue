<script setup>
import { useI18n } from "vue-i18n";
import { usePage } from "@inertiajs/vue3";

const { t } = useI18n();
const page = usePage();

defineProps({
  history: { type: Array, default: () => [] },
});

const formatDate = (value) => {
  if (!value) return t("common.empty_value");
  const locale = page.props.locale === "en" ? "en-GB" : "fr-FR";
  return new Date(value).toLocaleString(locale, {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const displayValue = (value) => (value != null && value !== "" ? value : t("common.empty_value"));
</script>

<template>
  <div v-if="history.length === 0" class="text-muted text-center py-4">
    {{ $t('orders.history.empty') }}
  </div>

  <div v-else class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>{{ $t('orders.history.field') }}</th>
          <th>{{ $t('orders.history.old_value') }}</th>
          <th>{{ $t('orders.history.new_value') }}</th>
          <th>{{ $t('orders.history.modified_by') }}</th>
          <th>{{ $t('orders.history.date_time') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="entry in history" :key="entry.id">
          <td class="fw-medium">{{ entry.field_label }}</td>
          <td class="text-muted">{{ displayValue(entry.old_value) }}</td>
          <td class="fw-semibold">{{ displayValue(entry.new_value) }}</td>
          <td>{{ entry.changed_by?.name ?? $t('common.empty_value') }}</td>
          <td class="text-muted text-nowrap">{{ formatDate(entry.created_at) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
