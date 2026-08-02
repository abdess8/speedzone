<script setup>
import { computed } from 'vue';
import { IMPORT_FIELDS } from '@/composables/useOrderImport';

/**
 * Step 2: bind each file column to an order field.
 *
 * The selection arrives pre-filled by the auto-matcher; everything here exists
 * so the seller can see what was guessed and override it.
 */

const props = defineProps({
  headers: { type: Array, default: () => [] },
  /** field key → header index, mutated in place like the order form's `form`. */
  mapping: { type: Object, required: true },
  /** First data row of the file, used for the preview column. */
  sample: { type: Array, default: () => [] },
  /** Snapshot of the mapping right after auto-matching. */
  autoMatched: { type: Object, default: () => ({}) },
  rowCount: { type: Number, default: 0 },
});

const emit = defineEmits(['reset']);

const fields = IMPORT_FIELDS;

const usedHeaders = computed(() => {
  const index = new Map();

  for (const [key, header] of Object.entries(props.mapping)) {
    if (header !== null && header !== undefined) {
      index.set(Number(header), key);
    }
  }

  return index;
});

const mappedCount = computed(() => usedHeaders.value.size);

const ignoredHeaders = computed(() =>
  props.headers
    .map((header, index) => ({ header, index }))
    .filter(({ index }) => !usedHeaders.value.has(index))
);

const missingRequired = computed(() =>
  fields.filter((field) => field.required && props.mapping[field.key] == null)
);

function isAutoMatched(key) {
  return props.mapping[key] != null && props.mapping[key] === props.autoMatched[key];
}

function preview(key) {
  const index = props.mapping[key];

  if (index === null || index === undefined) {
    return '';
  }

  return String(props.sample?.[index] ?? '').trim();
}

function select(key, value) {
  props.mapping[key] = value === '' ? null : Number(value);
}
</script>

<template>
  <BRow class="g-4">
    <BCol xl="8">
      <BCard data-guide="import-mapping" no-body>
        <BCardHeader class="d-flex flex-wrap align-items-center gap-2">
          <h5 class="card-title mb-0 flex-grow-1">{{ $t('orders.import.mapping.title') }}</h5>
          <span class="badge bg-primary-subtle text-primary">
            {{ $t('orders.import.mapping.mapped_count', { mapped: mappedCount, total: headers.length }) }}
          </span>
          <button type="button" class="btn btn-sm btn-ghost-secondary" @click="emit('reset')">
            <i class="ri-magic-line align-bottom me-1"></i>
            {{ $t('orders.import.mapping.rerun_auto') }}
          </button>
        </BCardHeader>

        <BCardBody class="p-0">
          <div class="table-responsive">
            <table class="table align-middle table-nowrap mb-0">
              <thead class="table-light text-muted">
                <tr>
                  <th style="width: 30%">{{ $t('orders.import.mapping.system_field') }}</th>
                  <th style="width: 35%">{{ $t('orders.import.mapping.file_column') }}</th>
                  <th>{{ $t('orders.import.mapping.preview') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="field in fields" :key="field.key">
                  <td>
                    <span class="fw-medium">{{ $t(`orders.import.fields.${field.key}`) }}</span>
                    <span v-if="field.required" class="text-danger ms-1">*</span>
                    <span v-else class="text-muted fs-12 ms-1">
                      {{ $t('orders.import.mapping.optional') }}
                    </span>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <select
                        class="form-select form-select-sm"
                        :class="{ 'is-invalid': field.required && mapping[field.key] == null }"
                        :value="mapping[field.key] ?? ''"
                        @change="select(field.key, $event.target.value)"
                      >
                        <option value="">{{ $t('orders.import.mapping.not_mapped') }}</option>
                        <option
                          v-for="(header, index) in headers"
                          :key="index"
                          :value="index"
                          :disabled="usedHeaders.has(index) && usedHeaders.get(index) !== field.key"
                        >
                          {{ header }}
                        </option>
                      </select>
                      <i
                        v-if="isAutoMatched(field.key)"
                        class="ri-sparkling-fill text-warning"
                        :title="$t('orders.import.mapping.auto_matched')"
                      ></i>
                    </div>
                  </td>
                  <td class="text-muted text-truncate" style="max-width: 220px" :title="preview(field.key)">
                    {{ preview(field.key) || '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCardBody>
      </BCard>
    </BCol>

    <BCol xl="4">
      <BCard no-body>
        <BCardBody>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="text-muted">{{ $t('orders.import.mapping.rows_to_import') }}</span>
            <span class="fs-18 fw-bold text-primary">{{ rowCount }}</span>
          </div>

          <div v-if="missingRequired.length" class="alert alert-danger mb-0">
            <h6 class="alert-heading fs-14">
              <i class="ri-error-warning-line align-bottom me-1"></i>
              {{ $t('orders.import.mapping.missing_required') }}
            </h6>
            <ul class="mb-0 ps-3">
              <li v-for="field in missingRequired" :key="field.key">
                {{ $t(`orders.import.fields.${field.key}`) }}
              </li>
            </ul>
          </div>
          <div v-else class="alert alert-success mb-0">
            <i class="ri-checkbox-circle-line align-bottom me-1"></i>
            {{ $t('orders.import.mapping.all_required_mapped') }}
          </div>
        </BCardBody>
      </BCard>

      <BCard v-if="ignoredHeaders.length" no-body>
        <BCardHeader>
          <h6 class="card-title mb-0">{{ $t('orders.import.mapping.ignored_columns') }}</h6>
        </BCardHeader>
        <BCardBody>
          <p class="text-muted fs-13">{{ $t('orders.import.mapping.ignored_hint') }}</p>
          <div class="d-flex flex-wrap gap-1">
            <span
              v-for="column in ignoredHeaders"
              :key="column.index"
              class="badge bg-light text-body border"
            >
              {{ column.header }}
            </span>
          </div>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
