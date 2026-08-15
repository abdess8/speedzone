<script setup>
import { computed } from 'vue';

/**
 * What the batch actually did, item by item.
 *
 * The failures are the point of this panel: a refused parcel is listed with the
 * reason it was refused, so the operator knows which ones to chase rather than
 * re-running the whole batch and hoping.
 */
const props = defineProps({
  result: { type: Object, required: true },
});

defineEmits(['restart']);

const failures = computed(() => props.result.results?.filter((row) => !row.successful) ?? []);
const successes = computed(() => props.result.results?.filter((row) => row.successful) ?? []);
</script>

<template>
  <BCard no-body>
    <BCardBody>
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h5 class="mb-0">{{ $t('bulk_status.result.title') }}</h5>
        <button type="button" class="btn btn-sm btn-soft-primary" @click="$emit('restart')">
          <i class="ri-refresh-line align-bottom me-1"></i>{{ $t('bulk_status.result.restart') }}
        </button>
      </div>

      <div class="row g-2 mb-3">
        <BCol sm="6">
          <div class="border rounded p-3 h-100 d-flex align-items-center gap-3">
            <span class="avatar-xs flex-shrink-0">
              <span class="avatar-title bg-success-subtle text-success rounded-circle">
                <i class="ri-check-line"></i>
              </span>
            </span>
            <div>
              <div class="fs-18 fw-semibold">{{ result.succeeded }}</div>
              <div class="text-muted small">
                {{ $t('bulk_status.result.succeeded', { count: result.succeeded }) }}
              </div>
            </div>
          </div>
        </BCol>
        <BCol sm="6">
          <div class="border rounded p-3 h-100 d-flex align-items-center gap-3">
            <span class="avatar-xs flex-shrink-0">
              <span
                class="avatar-title rounded-circle"
                :class="result.failed ? 'bg-danger-subtle text-danger' : 'bg-light text-muted'"
              >
                <i class="ri-close-line"></i>
              </span>
            </span>
            <div>
              <div class="fs-18 fw-semibold">{{ result.failed }}</div>
              <div class="text-muted small">
                {{ $t('bulk_status.result.failed', { count: result.failed }) }}
              </div>
            </div>
          </div>
        </BCol>
      </div>

      <div v-if="failures.length" class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ $t('bulk_status.columns.reference') }}</th>
              <th>{{ $t('bulk_status.columns.current_status') }}</th>
              <th>{{ $t('bulk_status.result.reason') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in failures" :key="`${row.id}-${row.reference}`">
              <td class="fw-medium">{{ row.reference ?? `#${row.id}` }}</td>
              <td>{{ row.from_status_label ?? $t('common.empty_value') }}</td>
              <td class="text-danger">{{ row.failure_message }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <details v-if="successes.length" class="mt-3">
        <summary class="text-muted small">
          {{ $t('bulk_status.result.succeeded', { count: successes.length }) }}
        </summary>
        <div class="d-flex flex-wrap gap-1 mt-2">
          <span v-for="row in successes" :key="row.id" class="badge bg-success-subtle text-success">
            {{ row.reference ?? `#${row.id}` }}
          </span>
        </div>
      </details>
    </BCardBody>
  </BCard>
</template>
