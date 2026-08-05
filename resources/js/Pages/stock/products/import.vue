<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import StepUpload from './Partials/import/StepUpload.vue';
import StepMapping from './Partials/import/StepMapping.vue';
import StepReview from './Partials/import/StepReview.vue';
import { isSupportedFile, MAX_FILE_SIZE, readSpreadsheet } from '@/common/spreadsheet';
import { useProductImport } from '@/composables/useProductImport';

/**
 * Bulk product catalog import wizard.
 *
 * Upload → column mapping → review, all three steps against the same client
 * side store. Nothing reaches the server until the review table has been
 * verified clean, and the server validates the whole batch again before a single
 * reference is written.
 */

const { t } = useI18n();

const props = defineProps({
  categories: { type: Array, default: () => [] },
  maxRows: { type: Number, default: 1000 },
});

const importer = useProductImport(props);

const {
  step,
  file,
  headers,
  rawRows,
  mapping,
  rows,
  errorCount,
  invalidRowCount,
  canSave,
  canVerify,
  mappingIsValid,
  autoMap,
  buildRows,
  validateAll,
  applyServerErrors,
  payload,
  reset,
} = importer;

const parsing = ref(false);
const parseError = ref('');
const saving = ref(false);
/** Mapping as the auto-matcher left it, so step 2 can flag its own guesses. */
const autoMatched = ref({});

const steps = computed(() => [
  { number: 1, label: t('stock.products.import.steps.upload') },
  { number: 2, label: t('stock.products.import.steps.mapping') },
  { number: 3, label: t('stock.products.import.steps.review') },
]);

/* ------------------------------------------------------------- step 1 */

async function selectFile(selected) {
  parseError.value = '';

  if (!isSupportedFile(selected)) {
    parseError.value = t('stock.products.import.upload.unsupported');

    return;
  }

  if (selected.size > MAX_FILE_SIZE) {
    parseError.value = t('stock.products.import.upload.too_large');

    return;
  }

  parsing.value = true;

  try {
    const parsed = await readSpreadsheet(selected);

    if (parsed.rows.length === 0) {
      parseError.value = t('stock.products.import.upload.empty_file');

      return;
    }

    if (parsed.rows.length > props.maxRows) {
      parseError.value = t('stock.products.import.upload.too_many_rows', { max: props.maxRows });

      return;
    }

    file.value = selected;
    headers.value = parsed.headers;
    rawRows.value = parsed.rows;
    autoMap();
    autoMatched.value = { ...mapping.value };
  } catch (error) {
    parseError.value = t('stock.products.import.upload.parse_failed', { message: error.message });
  } finally {
    parsing.value = false;
  }
}

function clearFile() {
  reset();
  parseError.value = '';
}

function rerunAutoMatch() {
  autoMap();
  autoMatched.value = { ...mapping.value };
}

/* --------------------------------------------------------- navigation */

const canGoNext = computed(() => {
  if (step.value === 1) {
    return !!file.value && rawRows.value.length > 0 && !parsing.value;
  }

  return mappingIsValid.value;
});

function next() {
  if (!canGoNext.value) {
    return;
  }

  if (step.value === 1) {
    step.value = 2;

    return;
  }

  buildRows();
  validateAll();
  step.value = 3;
}

function back() {
  step.value = Math.max(1, step.value - 1);
}

/* ------------------------------------------------------------ actions */

function verify() {
  const clean = validateAll();

  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: clean ? 'success' : 'error',
    title: clean
      ? t('stock.products.import.review.verify_success', { count: rows.value.length })
      : t('stock.products.import.review.verify_failed', { count: invalidRowCount.value }),
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
  });
}

async function save() {
  if (!canSave.value) {
    return;
  }

  const confirmation = await Swal.fire({
    icon: 'question',
    title: t('stock.products.import.confirm.title'),
    text: t('stock.products.import.confirm.text', { count: rows.value.length }),
    showCancelButton: true,
    confirmButtonText: t('stock.products.import.confirm.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-success', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (!confirmation.isConfirmed) {
    return;
  }

  saving.value = true;

  router.post(
    route('products.import.store'),
    { products: payload() },
    {
      onError: (serverErrors) => {
        const mapped = applyServerErrors(serverErrors);

        Swal.fire({
          icon: 'error',
          title: t('stock.products.import.save_failed'),
          text: mapped
            ? t('stock.products.import.save_failed_rows', { count: mapped })
            : Object.values(serverErrors).flat().join('\n'),
        });
      },
      onFinish: () => {
        saving.value = false;
      },
    }
  );
}
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stock.products.import.title')" :pageTitle="$t('stock.page_title')" />

    <BCard no-body>
      <BCardBody>
        <ul class="nav nav-pills wizard-nav flex-nowrap align-items-center gap-2 mb-0">
          <li v-for="item in steps" :key="item.number" class="nav-item flex-grow-1">
            <span class="nav-link" :class="{ active: step === item.number, done: step > item.number }">
              <span class="step-number">
                <i v-if="step > item.number" class="ri-check-line"></i>
                <template v-else>{{ item.number }}</template>
              </span>
              <span class="step-label ms-2">{{ item.label }}</span>
            </span>
          </li>
        </ul>
      </BCardBody>
    </BCard>

    <StepUpload
      v-if="step === 1"
      :file="file"
      :row-count="rawRows.length"
      :parsing="parsing"
      :error="parseError"
      :max-rows="maxRows"
      @select="selectFile"
      @clear="clearFile"
    />

    <StepMapping
      v-else-if="step === 2"
      :headers="headers"
      :mapping="mapping"
      :sample="rawRows[0]"
      :auto-matched="autoMatched"
      :row-count="rawRows.length"
      @reset="rerunAutoMatch"
    />

    <StepReview v-else :importer="importer" />

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 my-4">
      <div class="hstack gap-2">
        <Link :href="route('products.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
        <BButton v-if="step > 1" type="button" variant="light" @click="back">
          <i class="ri-arrow-left-line align-bottom me-1"></i>
          {{ $t('stock.products.import.back') }}
        </BButton>
      </div>

      <div class="hstack gap-2">
        <BButton v-if="step < 3" type="button" variant="primary" :disabled="!canGoNext" @click="next">
          {{ step === 1 ? $t('stock.products.import.next') : $t('stock.products.import.validate_mapping') }}
          <i class="ri-arrow-right-line align-bottom ms-1"></i>
        </BButton>

        <template v-else>
          <BButton type="button" variant="warning" :disabled="!canVerify" @click="verify">
            <i class="ri-shield-check-line align-bottom me-1"></i>
            {{ $t('stock.products.import.verify') }}
          </BButton>
          <BButton type="button" variant="success" :disabled="!canSave || saving" @click="save">
            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="ri-save-3-line align-bottom me-1"></i>
            {{ $t('stock.products.import.save', { count: rows.length }) }}
          </BButton>
        </template>
      </div>
    </div>

    <!-- Says out loud why "Import" is greyed out; without it the pair of buttons
         reads as a bug rather than as a required verification pass. -->
    <p v-if="step === 3 && !canSave" class="text-muted text-center fs-13">
      <i class="ri-information-line align-bottom me-1"></i>
      {{
        errorCount > 0
          ? $t('stock.products.import.save_blocked_errors')
          : $t('stock.products.import.save_blocked_dirty')
      }}
    </p>
  </Layout>
</template>

<style scoped>
.wizard-nav .nav-link {
  display: flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
  border-radius: 0.375rem;
  color: var(--vz-body-color);
  background: var(--vz-light);
}

.wizard-nav .nav-link.active {
  background: var(--vz-primary);
  color: #fff;
}

.wizard-nav .nav-link.done {
  background: rgba(var(--vz-success-rgb), 0.15);
  color: var(--vz-success);
}

.wizard-nav .step-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.08);
  font-size: 0.75rem;
}

.wizard-nav .step-label {
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
