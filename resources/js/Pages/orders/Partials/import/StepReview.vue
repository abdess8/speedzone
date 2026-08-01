<script setup>
import { computed, ref, watch } from 'vue';
import { formatMoney as money } from '@/common/formatMoney';
import { IMPORT_FIELDS } from '@/composables/useOrderImport';
import ImportCell from './ImportCell.vue';

/**
 * Step 3: the review table.
 *
 * Every cell is editable in place and re-validated as it changes, but the
 * "needs verifying" latch owned by the composable is what actually gates the
 * save button — see the wizard footer.
 */

const props = defineProps({
  importer: { type: Object, required: true },
  paymentMethods: { type: Array, default: () => [] },
});

const {
  rows,
  errors,
  cities,
  sectorOptionsFor,
  invalidRowIds,
  invalidRowCount,
  errorCount,
  duplicateRowIds,
  totalAmount,
  checked,
  dirty,
  touch,
  removeRow,
  removeInvalidRows,
} = props.importer;

const PAGE_SIZES = [25, 50, 100];

const search = ref('');
const onlyErrors = ref(false);
const perPage = ref(25);
const page = ref(1);

const columns = IMPORT_FIELDS;

const cityOptions = computed(() =>
  cities.value.map((city) => ({ value: city.id, label: city.name }))
);

const paymentOptions = computed(() =>
  props.paymentMethods.map((method) => ({ value: method.value, label: method.label }))
);

function sectorOptions(row) {
  return sectorOptionsFor(row.city_id).map((sector) => ({ value: sector.id, label: sector.name }));
}

const searchableFields = ['customer_first_name', 'customer_last_name', 'customer_phone', 'customer_address'];

const filteredRows = computed(() => {
  const needle = search.value.trim().toLowerCase();

  return rows.value.filter((row) => {
    if (onlyErrors.value && !invalidRowIds.value.has(row._id)) {
      return false;
    }

    if (needle === '') {
      return true;
    }

    return searchableFields.some((field) => String(row[field] ?? '').toLowerCase().includes(needle));
  });
});

const pageCount = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / perPage.value)));

const visibleRows = computed(() => {
  const start = (page.value - 1) * perPage.value;

  return filteredRows.value.slice(start, start + perPage.value);
});

// A filter change can leave the cursor past the end of the shortened list.
watch([filteredRows, perPage], () => {
  page.value = Math.min(page.value, pageCount.value);
});

/** Page numbers around the current one, so a 40 page list stays one line. */
const pageWindow = computed(() => {
  const total = pageCount.value;
  const start = Math.max(1, Math.min(page.value - 2, total - 4));
  const end = Math.min(total, start + 4);

  return Array.from({ length: end - start + 1 }, (_, offset) => start + offset);
});

function errorFor(row, key) {
  return errors.value[row._id]?.[key] ?? '';
}

function rowClass(row) {
  if (invalidRowIds.value.has(row._id)) {
    return 'import-row--invalid';
  }

  return duplicateRowIds.value.has(row._id) ? 'import-row--warning' : '';
}

function cellType(field) {
  return { city: 'city', sector: 'sector', payment: 'payment', boolean: 'boolean' }[field.type] ?? field.type;
}

function isDisabled(field, row) {
  return field.key === 'sector_id' && !row.city_id;
}
</script>

<template>
  <div>
    <BRow class="g-3 mb-3">
      <BCol sm="6" xl="3">
        <BCard no-body class="mb-0">
          <BCardBody class="d-flex align-items-center gap-3">
            <div class="avatar-sm rounded bg-primary-subtle text-primary d-flex align-items-center justify-content-center fs-20">
              <i class="ri-file-list-3-line"></i>
            </div>
            <div>
              <p class="text-muted mb-0 fs-13">{{ $t('orders.import.review.total_rows') }}</p>
              <h4 class="mb-0">{{ rows.length }}</h4>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol sm="6" xl="3">
        <BCard no-body class="mb-0">
          <BCardBody class="d-flex align-items-center gap-3">
            <div class="avatar-sm rounded bg-success-subtle text-success d-flex align-items-center justify-content-center fs-20">
              <i class="ri-checkbox-circle-line"></i>
            </div>
            <div>
              <p class="text-muted mb-0 fs-13">{{ $t('orders.import.review.valid_rows') }}</p>
              <h4 class="mb-0">{{ rows.length - invalidRowCount }}</h4>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol sm="6" xl="3">
        <BCard no-body class="mb-0">
          <BCardBody class="d-flex align-items-center gap-3">
            <div class="avatar-sm rounded bg-danger-subtle text-danger d-flex align-items-center justify-content-center fs-20">
              <i class="ri-error-warning-line"></i>
            </div>
            <div>
              <p class="text-muted mb-0 fs-13">{{ $t('orders.import.review.invalid_rows') }}</p>
              <h4 class="mb-0">{{ invalidRowCount }}</h4>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol sm="6" xl="3">
        <BCard no-body class="mb-0">
          <BCardBody class="d-flex align-items-center gap-3">
            <div class="avatar-sm rounded bg-info-subtle text-info d-flex align-items-center justify-content-center fs-20">
              <i class="ri-money-dollar-circle-line"></i>
            </div>
            <div>
              <p class="text-muted mb-0 fs-13">{{ $t('orders.import.review.total_to_collect') }}</p>
              <h4 class="mb-0">{{ money(totalAmount) }}</h4>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <div v-if="errorCount > 0" class="alert alert-danger d-flex flex-wrap align-items-center gap-2">
      <i class="ri-error-warning-line fs-18"></i>
      <span class="flex-grow-1">
        {{ $t('orders.import.review.errors_found', { errors: errorCount, rows: invalidRowCount }) }}
      </span>
      <button type="button" class="btn btn-sm btn-danger" @click="removeInvalidRows">
        <i class="ri-delete-bin-line align-bottom me-1"></i>
        {{ $t('orders.import.review.remove_invalid') }}
      </button>
    </div>
    <div v-else-if="dirty" class="alert alert-warning">
      <i class="ri-edit-2-line align-bottom me-1"></i>
      {{ $t('orders.import.review.needs_verify') }}
    </div>
    <div v-else-if="checked" class="alert alert-success">
      <i class="ri-checkbox-circle-line align-bottom me-1"></i>
      {{ $t('orders.import.review.all_valid', { count: rows.length }) }}
    </div>

    <BCard no-body>
      <BCardHeader class="d-flex flex-wrap align-items-center gap-2">
        <div class="search-box">
          <input
            v-model="search"
            type="text"
            class="form-control form-control-sm search"
            :placeholder="$t('orders.import.review.search_placeholder')"
          />
          <i class="ri-search-line search-icon"></i>
        </div>

        <div class="form-check form-switch mb-0">
          <input id="onlyErrors" v-model="onlyErrors" class="form-check-input" type="checkbox" />
          <label class="form-check-label" for="onlyErrors">
            {{ $t('orders.import.review.only_errors') }}
          </label>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2">
          <span class="text-muted fs-13">{{ $t('orders.import.review.per_page') }}</span>
          <select v-model.number="perPage" class="form-select form-select-sm w-auto">
            <option v-for="size in PAGE_SIZES" :key="size" :value="size">{{ size }}</option>
          </select>
        </div>
      </BCardHeader>

      <BCardBody class="p-0">
        <div v-if="rows.length === 0" class="text-center text-muted py-5">
          {{ $t('orders.import.review.empty') }}
        </div>

        <div v-else class="table-responsive import-table">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="import-table__line">#</th>
                <th v-for="field in columns" :key="field.key" :class="`import-col--${field.type}`">
                  {{ $t(`orders.import.fields.${field.key}`) }}
                  <span v-if="field.required" class="text-danger">*</span>
                </th>
                <th class="text-center" style="width: 48px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in visibleRows" :key="row._id" :class="rowClass(row)">
                <td class="import-table__line text-muted">
                  {{ row._line }}
                  <i
                    v-if="duplicateRowIds.has(row._id)"
                    class="ri-file-copy-line text-warning ms-1"
                    :title="$t('orders.import.review.duplicate_phone')"
                  ></i>
                </td>

                <td v-for="field in columns" :key="field.key" :class="`import-col--${field.type}`">
                  <ImportCell
                    v-model="row[field.key]"
                    :type="cellType(field)"
                    :error="errorFor(row, field.key)"
                    :raw="row._raw[field.key]"
                    :multiline="field.key === 'customer_address' || field.key === 'notes'"
                    :disabled="isDisabled(field, row)"
                    :disabled-hint="$t('orders.import.review.select_city_first')"
                    :options="
                      field.type === 'city'
                        ? cityOptions
                        : field.type === 'sector'
                          ? sectorOptions(row)
                          : field.type === 'payment'
                            ? paymentOptions
                            : []
                    "
                    @change="touch(row._id, field.key)"
                  />
                </td>

                <td class="text-center">
                  <button
                    type="button"
                    class="btn btn-sm btn-ghost-danger"
                    :title="$t('orders.import.review.remove_row')"
                    @click="removeRow(row._id)"
                  >
                    <i class="ri-delete-bin-line"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BCardBody>

      <BCardFooter v-if="rows.length" class="d-flex flex-wrap align-items-center gap-2">
        <span class="text-muted fs-13">
          {{ $t('orders.import.review.showing', { shown: visibleRows.length, total: filteredRows.length }) }}
        </span>

        <ul v-if="pageCount > 1" class="pagination pagination-sm mb-0 ms-auto">
          <li class="page-item" :class="{ disabled: page === 1 }">
            <button type="button" class="page-link" @click="page -= 1">
              <i class="ri-arrow-left-s-line"></i>
            </button>
          </li>
          <li v-for="number in pageWindow" :key="number" class="page-item" :class="{ active: number === page }">
            <button type="button" class="page-link" @click="page = number">{{ number }}</button>
          </li>
          <li class="page-item" :class="{ disabled: page === pageCount }">
            <button type="button" class="page-link" @click="page += 1">
              <i class="ri-arrow-right-s-line"></i>
            </button>
          </li>
        </ul>
      </BCardFooter>
    </BCard>
  </div>
</template>

<style scoped>
.import-table {
  max-height: 62vh;
}

.import-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  white-space: nowrap;
}

.import-table__line {
  position: sticky;
  left: 0;
  z-index: 1;
  width: 56px;
  /* Opaque, so the cells scrolling underneath the frozen column stay hidden;
     row tints are painted on top of it as a background image. */
  background-color: var(--vz-card-bg, #fff);
}

.import-table thead .import-table__line {
  background-color: var(--vz-light);
}

.import-table thead .import-table__line {
  z-index: 3;
}

.import-col--text,
.import-col--phone {
  min-width: 150px;
}

.import-col--city,
.import-col--sector,
.import-col--payment {
  min-width: 150px;
}

.import-col--amount {
  min-width: 110px;
}

.import-col--boolean {
  min-width: 90px;
  text-align: center;
}

.import-row--invalid > td {
  background-image: linear-gradient(
    rgba(var(--vz-danger-rgb), 0.07),
    rgba(var(--vz-danger-rgb), 0.07)
  );
}

.import-row--warning > td {
  background-image: linear-gradient(
    rgba(var(--vz-warning-rgb), 0.09),
    rgba(var(--vz-warning-rgb), 0.09)
  );
}
</style>
