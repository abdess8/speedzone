<script setup>
defineProps({
  history: { type: Array, default: () => [] },
});

const formatDate = (value) => {
  if (!value) return "—";
  const date = new Date(value);
  return date.toLocaleString("en-GB", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const displayValue = (value) => (value != null && value !== "" ? value : "—");
</script>

<template>
  <div v-if="history.length === 0" class="text-muted text-center py-4">
    No modifications recorded yet.
  </div>

  <div v-else class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Field</th>
          <th>Old Value</th>
          <th>New Value</th>
          <th>Modified By</th>
          <th>Date &amp; Time</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="entry in history" :key="entry.id">
          <td class="fw-medium">{{ entry.field_label }}</td>
          <td class="text-muted">{{ displayValue(entry.old_value) }}</td>
          <td class="fw-semibold">{{ displayValue(entry.new_value) }}</td>
          <td>{{ entry.changed_by?.name ?? "—" }}</td>
          <td class="text-muted text-nowrap">{{ formatDate(entry.created_at) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
