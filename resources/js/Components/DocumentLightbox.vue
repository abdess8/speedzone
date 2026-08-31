<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  url: { type: String, default: null },
  title: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const open = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

/**
 * Query strings survive on signed URLs, so the extension is matched rather
 * than tested with endsWith().
 */
const isPdf = computed(() => /\.pdf(\?|#|$)/i.test(props.url ?? ''));
</script>

<template>
  <BModal v-model="open" :title="title" size="xl" centered hide-footer scrollable>
    <div class="doc-lightbox">
      <!-- An <object> degrades to its own children when the browser has no PDF
           viewer, which is the one case where a bare iframe shows nothing. -->
      <object v-if="isPdf" :data="url" type="application/pdf" class="doc-lightbox__pdf">
        <p class="text-muted mb-0 p-4 text-center">{{ $t('documents.preview.pdf_fallback') }}</p>
      </object>
      <img v-else :src="url" :alt="title" class="doc-lightbox__image" />
    </div>

    <div class="hstack gap-2 justify-content-end mt-3">
      <a :href="url" target="_blank" rel="noopener" class="btn btn-soft-primary btn-sm">
        <i class="ri-external-link-line align-bottom me-1"></i>
        {{ $t('documents.preview.open_new_tab') }}
      </a>
      <BButton variant="light" size="sm" @click="open = false">{{ $t('common.close') }}</BButton>
    </div>
  </BModal>
</template>

<style scoped>
.doc-lightbox {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 12rem;
  background-color: var(--vz-light);
  border: 1px solid var(--vz-border-color);
  border-radius: var(--vz-border-radius);
  overflow: hidden;
}

.doc-lightbox__image {
  max-width: 100%;
  max-height: 70vh;
  object-fit: contain;
}

.doc-lightbox__pdf {
  width: 100%;
  height: 70vh;
  border: 0;
}
</style>
