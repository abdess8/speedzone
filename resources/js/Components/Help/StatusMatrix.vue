<script setup>
import { computed } from "vue";

/**
 * Every status of one workflow, with its meaning, its owner and the permissions
 * that unlock it.
 *
 * A table on desktop and a stack of cards on phones: the four columns are all
 * prose, and prose in a 320px-wide table is unreadable.
 */
const props = defineProps({
  rows: { type: Array, default: () => [] },
  noPermissionLabel: { type: String, required: true },
});

/**
 * Statuses outside the ordered pipeline (a cancellation, say) carry no step
 * number, so the total is the count of those that do.
 */
const totalSteps = computed(() => props.rows.filter((row) => row.step).length);
</script>

<template>
  <div>
    <div class="table-responsive d-none d-lg-block">
      <table class="table align-middle mb-0">
        <thead class="table-light text-muted">
          <tr>
            <th style="width: 18%">{{ $t('help.processes.matrix.status') }}</th>
            <th>{{ $t('help.processes.matrix.meaning') }}</th>
            <th style="width: 20%">{{ $t('help.processes.matrix.actor') }}</th>
            <th style="width: 22%">{{ $t('help.processes.matrix.permissions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.value">
            <td>
              <span class="badge" :class="`bg-${row.color}-subtle text-${row.color}`">
                <i :class="row.icon" class="me-1"></i>{{ row.label }}
              </span>
              <div v-if="row.step" class="text-muted fs-12 mt-1">
                {{ $t('help.processes.step_of', { current: row.step, total: totalSteps }) }}
              </div>
            </td>
            <td class="fs-13">{{ row.description }}</td>
            <td class="fs-13">{{ row.actor }}</td>
            <td>
              <code v-for="permission in row.permissions" :key="permission" class="matrix-permission">
                {{ permission }}
              </code>
              <span v-if="!row.permissions.length" class="text-muted fs-12">{{ noPermissionLabel }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="d-lg-none d-grid gap-2">
      <div v-for="row in rows" :key="row.value" class="matrix-card">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge" :class="`bg-${row.color}-subtle text-${row.color}`">
            <i :class="row.icon" class="me-1"></i>{{ row.label }}
          </span>
          <span v-if="row.step" class="text-muted fs-12">
            {{ $t('help.processes.step_of', { current: row.step, total: totalSteps }) }}
          </span>
        </div>

        <p class="fs-13 mb-2">{{ row.description }}</p>

        <div class="text-muted fs-12 mb-2">
          <i class="ri-user-settings-line me-1"></i>{{ row.actor }}
        </div>

        <div>
          <code v-for="permission in row.permissions" :key="permission" class="matrix-permission">
            {{ permission }}
          </code>
          <span v-if="!row.permissions.length" class="text-muted fs-12">{{ noPermissionLabel }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.matrix-permission {
  display: inline-block;
  margin: 0 0.25rem 0.25rem 0;
  padding: 0.1rem 0.4rem;
  border-radius: 0.3rem;
  background: rgba(var(--vz-primary-rgb), 0.08);
  color: var(--vz-primary);
  font-size: 0.7rem;
  word-break: break-all;
}

.matrix-card {
  padding: 0.9rem;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.6rem;
  background: var(--vz-card-bg);
}
</style>
