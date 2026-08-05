<script setup>
import { computed } from 'vue';
import { PRODUCT_IMPORT_FIELDS } from '@/composables/useProductImport';

/**
 * Step 2: bind each file column to a product field.
 *
 * The selection arrives pre-filled by the auto-matcher; everything here exists
 * so the seller can see what was guessed and override it.
 */

const props = defineProps({
  headers: { type: Array, default: () => [] },
  /** field key → header index, mutated in place like the product form's `form`. */
  mapping: { type: Object, required: true },
  /** First data row of the file, used for the preview column. */
  sample: { type: Array, default: () => [] },
  /** Snapshot of the mapping right after auto-matching. */
  autoMatched: { type: Object, default: () => ({}) },
  rowCount: { type: Number, default: 0 },
});

const emit = defineEmits(['reset']);

const fields = PRODUCT_IMPORT_FIELDS;

const usedHeaders = computed(() => {
  const index = new Map();

  for (const [key, header] of Object.entries(props.mapping)) {
    if (header !== null && header !== undefined) {
      index.set(Number(header), key);
    }
  }

  return index;
});

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
      <BCard no-body>
        <BCardHeader class="d-flex flex-wrap align-items-center gap-2">
          <div class="flex-grow-1">
            <h5 class="card-title mb-0">{{ $t('stock.products.import.mapping.title') }}</h5>
            <p class="text-muted fs-13 mb-0">{{ $t('stock.products.import.mapping.hint') }}</p>
          </div>
          <button type="button" class="btn btn-sm btn-ghost-secondary" @click="emit('reset')">
            <i class="ri-magic-line align-bottom me-1"></i>
            {{ $t('stock.products.import.mapping.reset') }}
          </button>
        </BCardHeader>

        <BCardBody class="p-0">
          <div class="table-responsive">
            <table class="table align-middle table-nowrap mb-0">
              <thead class="table-light text-muted">
                <tr>
                  <th style="width: 30%">{{ $t('stock.products.import.mapping.field') }}</th>
                  <th style="width: 35%">{{ $t('stock.products.import.mapping.column') }}</th>
                  <th>{{ $t('stock.products.import.mapping.sample') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="field in fields" :key="field.key">
                  <td>
                    <span class="fw-medium">{{ $t(`stock.products.import.fields.${field.key}`) }}</span>
                    <span v-if="field.required" class="text-danger ms-1">*</span>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <select
                        class="form-select form-select-sm"
                        :class="{ 'is-invalid': field.required && mapping[field.key] == null }"
                        :value="mapping[field.key] ?? ''"
                        @change="select(field.key, $event.target.value)"
                      >
                        <option value="">{{ $t('stock.products.import.mapping.ignore') }}</option>
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
                        :title="$t('stock.products.import.mapping.auto_matched')"
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
            <span class="text-muted">{{ $t('stock.products.import.mapping.rows_ready', { count: rowCount }) }}</span>
            <span class="fs-18 fw-bold text-primary">{{ rowCount }}</span>
          </div>

          <div v-if="missingRequired.length" class="alert alert-danger mb-0">
            <i class="ri-error-warning-line align-bottom me-1"></i>
            {{
              $t('stock.products.import.mapping.missing_required', {
                fields: missingRequired.map((field) => $t(`stock.products.import.fields.${field.key}`)).join(', '),
              })
            }}
          </div>
          <div v-else class="alert alert-success mb-0">
            <i class="ri-checkbox-circle-line align-bottom me-1"></i>
            {{ $t('stock.products.import.mapping.rows_ready', { count: rowCount }) }}
          </div>
        </BCardBody>
      </BCard>

      <BCard v-if="ignoredHeaders.length" no-body>
        <BCardHeader>
          <h6 class="card-title mb-0">{{ $t('stock.products.import.mapping.ignore') }}</h6>
        </BCardHeader>
        <BCardBody>
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
