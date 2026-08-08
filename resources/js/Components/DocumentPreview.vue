<script setup>
import { computed, ref } from 'vue';
import DocumentLightbox from './DocumentLightbox.vue';

const props = defineProps({
  url: { type: String, default: null },
  /** Shown above the thumbnail and read out by the "open" action. */
  label: { type: String, required: true },
  /** Hide the caption when the surrounding form already labels the field. */
  showLabel: { type: Boolean, default: true },
});

const lightbox = ref(false);

const isPdf = computed(() => /\.pdf(\?|#|$)/i.test(props.url ?? ''));
</script>

<template>
  <div class="doc-preview">
    <span v-if="showLabel" class="doc-preview__caption">{{ label }}</span>

    <button
      v-if="url"
      type="button"
      class="doc-preview__frame"
      :aria-label="$t('documents.preview.open_aria', { label })"
      @click="lightbox = true"
    >
      <img v-if="!isPdf" :src="url" :alt="label" class="doc-preview__image" />
      <span v-else class="doc-preview__pdf">
        <i class="ri-file-pdf-line"></i>
        <span class="doc-preview__pdf-label">{{ $t('documents.preview.pdf_document') }}</span>
      </span>

      <span class="doc-preview__overlay" aria-hidden="true">
        <i class="ri-zoom-in-line"></i>
      </span>
    </button>

    <div v-else class="doc-preview__empty">
      <i class="ri-file-forbid-line me-1"></i>{{ $t('documents.preview.missing') }}
    </div>

    <DocumentLightbox v-if="url" v-model="lightbox" :url="url" :title="label" />
  </div>
</template>

<style scoped>
.doc-preview {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.doc-preview__caption {
  font-size: 0.75rem;
  color: var(--vz-secondary-color);
}

.doc-preview__frame {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 9rem;
  padding: 0;
  overflow: hidden;
  background-color: var(--vz-light);
  border: 1px solid var(--vz-border-color);
  border-radius: var(--vz-border-radius);
  cursor: zoom-in;
  transition: border-color 0.15s ease;
}

.doc-preview__frame:hover,
.doc-preview__frame:focus-visible {
  border-color: var(--vz-primary);
}

.doc-preview__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.doc-preview__pdf {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  color: var(--vz-danger);
  font-size: 2rem;
  line-height: 1;
}

.doc-preview__pdf-label {
  font-size: 0.75rem;
  color: var(--vz-secondary-color);
}

.doc-preview__overlay {
  position: absolute;
  right: 0.375rem;
  bottom: 0.375rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  color: var(--vz-body-color);
  background-color: var(--vz-card-bg);
  border: 1px solid var(--vz-border-color);
  border-radius: 50%;
  opacity: 0.9;
}

.doc-preview__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 9rem;
  font-size: 0.75rem;
  color: var(--vz-secondary-color);
  background-color: var(--vz-light);
  border: 1px dashed var(--vz-border-color);
  border-radius: var(--vz-border-radius);
}
</style>
