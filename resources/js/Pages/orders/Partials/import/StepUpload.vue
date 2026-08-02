<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ACCEPTED_EXTENSIONS, downloadTemplate } from '@/common/spreadsheet';
import { IMPORT_FIELDS, TEMPLATE_EXAMPLES } from '@/composables/useOrderImport';

const { t } = useI18n();

const props = defineProps({
  file: { type: Object, default: null },
  rowCount: { type: Number, default: 0 },
  parsing: { type: Boolean, default: false },
  error: { type: String, default: '' },
  maxRows: { type: Number, default: 1000 },
});

const emit = defineEmits(['select', 'clear']);

const input = ref(null);
const dragging = ref(false);

const accept = ACCEPTED_EXTENSIONS.join(',');

const fileSize = computed(() => {
  if (!props.file) {
    return '';
  }

  const kilobytes = props.file.size / 1024;

  return kilobytes < 1024 ? `${kilobytes.toFixed(0)} Ko` : `${(kilobytes / 1024).toFixed(1)} Mo`;
});

function pick(fileList) {
  const [selected] = fileList ?? [];

  if (selected) {
    emit('select', selected);
  }
}

function onDrop(event) {
  dragging.value = false;
  pick(event.dataTransfer?.files);
}

function clear() {
  if (input.value) {
    input.value.value = '';
  }

  emit('clear');
}

function download() {
  downloadTemplate(
    IMPORT_FIELDS.map((field) => ({
      header: t(`orders.import.fields.${field.key}`),
      example: TEMPLATE_EXAMPLES[field.key] ?? '',
    }))
  );
}
</script>

<template>
  <BRow class="g-4">
    <BCol lg="8">
      <BCard no-body class="h-100">
        <BCardBody>
          <div
            data-guide="import-dropzone"
            class="upload-zone"
            :class="{ 'upload-zone--active': dragging, 'upload-zone--filled': !!file }"
            @dragover.prevent="dragging = true"
            @dragenter.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
            @click="input?.click()"
          >
            <input
              ref="input"
              type="file"
              class="d-none"
              :accept="accept"
              @change="pick($event.target.files)"
            />

            <template v-if="parsing">
              <div class="spinner-border text-primary mb-3" role="status"></div>
              <h5 class="mb-1">{{ $t('orders.import.upload.parsing') }}</h5>
            </template>

            <template v-else-if="file">
              <div class="upload-zone__icon bg-success-subtle text-success">
                <i class="ri-file-excel-2-line"></i>
              </div>
              <h5 class="mb-1">{{ file.name }}</h5>
              <p class="text-muted mb-3">
                {{ fileSize }} · {{ $t('orders.import.upload.rows_detected', { count: rowCount }) }}
              </p>
              <button type="button" class="btn btn-sm btn-soft-danger" @click.stop="clear">
                <i class="ri-delete-bin-line align-bottom me-1"></i>
                {{ $t('orders.import.upload.remove_file') }}
              </button>
            </template>

            <template v-else>
              <div class="upload-zone__icon bg-primary-subtle text-primary">
                <i class="ri-upload-cloud-2-line"></i>
              </div>
              <h5 class="mb-1">{{ $t('orders.import.upload.drop_title') }}</h5>
              <p class="text-muted mb-0">{{ $t('orders.import.upload.drop_hint') }}</p>
              <span class="badge bg-light text-body border mt-3">{{ accept }}</span>
            </template>
          </div>

          <div v-if="error" class="alert alert-danger mt-3 mb-0">
            <i class="ri-error-warning-line align-bottom me-1"></i>{{ error }}
          </div>
        </BCardBody>
      </BCard>
    </BCol>

    <BCol lg="4">
      <BCard no-body class="h-100">
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('orders.import.upload.template_title') }}</h5>
        </BCardHeader>
        <BCardBody class="d-flex flex-column">
          <p class="text-muted">{{ $t('orders.import.upload.template_hint') }}</p>

          <button
            data-guide="import-template"
            type="button"
            class="btn btn-soft-primary w-100 mb-4"
            @click="download"
          >
            <i class="ri-download-2-line align-bottom me-1"></i>
            {{ $t('orders.import.upload.download_template') }}
          </button>

          <h6 class="text-muted text-uppercase fs-12 mb-2">
            {{ $t('orders.import.upload.rules_title') }}
          </h6>
          <ul class="text-muted ps-3 mb-0 fs-13 vstack gap-1">
            <li>{{ $t('orders.import.upload.rule_header') }}</li>
            <li>{{ $t('orders.import.upload.rule_max_rows', { max: maxRows }) }}</li>
            <li>{{ $t('orders.import.upload.rule_reference') }}</li>
            <li>{{ $t('orders.import.upload.rule_booleans') }}</li>
          </ul>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>

<style scoped>
.upload-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 280px;
  padding: 2rem;
  text-align: center;
  cursor: pointer;
  border: 2px dashed var(--vz-border-color);
  border-radius: 0.5rem;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.upload-zone:hover,
.upload-zone--active {
  border-color: var(--vz-primary);
  background: rgba(var(--vz-primary-rgb), 0.04);
}

.upload-zone--filled {
  border-style: solid;
  border-color: rgba(var(--vz-success-rgb), 0.5);
}

.upload-zone__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  margin-bottom: 1rem;
  border-radius: 50%;
  font-size: 1.75rem;
}
</style>
