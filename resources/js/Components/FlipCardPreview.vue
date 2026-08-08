<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DocumentLightbox from './DocumentLightbox.vue';

const { t } = useI18n();

const props = defineProps({
  frontUrl: { type: String, default: null },
  backUrl: { type: String, default: null },
  /** Name of the document as a whole, e.g. "CIN". */
  label: { type: String, required: true },
  showLabel: { type: Boolean, default: true },
});

const flipped = ref(false);
const lightbox = ref(false);

const isPdf = (url) => /\.pdf(\?|#|$)/i.test(url ?? '');

const faces = computed(() => [
  {
    side: 'front',
    url: props.frontUrl,
    label: t('documents.preview.recto'),
    missing: t('documents.preview.recto_missing'),
  },
  {
    side: 'back',
    url: props.backUrl,
    label: t('documents.preview.verso'),
    missing: t('documents.preview.verso_missing'),
  },
]);

const hasDocument = computed(() => Boolean(props.frontUrl || props.backUrl));

const visibleFace = computed(() => faces.value[flipped.value ? 1 : 0]);

const flipAriaLabel = computed(() =>
  flipped.value
    ? t('documents.preview.flip_to_recto_aria', { label: props.label })
    : t('documents.preview.flip_to_verso_aria', { label: props.label })
);
</script>

<template>
  <div class="flip-preview">
    <span v-if="showLabel" class="flip-preview__caption">{{ label }}</span>

    <div v-if="hasDocument" class="flip-preview__stage">
      <button
        type="button"
        class="flip-preview__card"
        :class="{ 'is-flipped': flipped }"
        :aria-label="flipAriaLabel"
        :aria-pressed="flipped"
        @click="flipped = !flipped"
      >
        <span class="flip-preview__inner">
          <span
            v-for="face in faces"
            :key="face.side"
            class="flip-preview__face"
            :class="`flip-preview__face--${face.side}`"
          >
            <img v-if="face.url && !isPdf(face.url)" :src="face.url" :alt="`${label} — ${face.label}`" class="flip-preview__image" />
            <span v-else-if="face.url" class="flip-preview__pdf">
              <i class="ri-file-pdf-line"></i>
              <span class="flip-preview__pdf-label">{{ $t('documents.preview.pdf_document') }}</span>
            </span>
            <span v-else class="flip-preview__missing">{{ face.missing }}</span>
          </span>
        </span>
      </button>

      <span class="flip-preview__side" aria-hidden="true">{{ visibleFace.label }}</span>

      <button
        v-if="visibleFace.url"
        type="button"
        class="flip-preview__zoom"
        :aria-label="$t('documents.preview.open_aria', { label: `${label} — ${visibleFace.label}` })"
        @click="lightbox = true"
      >
        <i class="ri-zoom-in-line"></i>
      </button>
    </div>

    <div v-else class="flip-preview__empty">
      <i class="ri-file-forbid-line me-1"></i>{{ $t('documents.preview.missing') }}
    </div>

    <span v-if="hasDocument" class="flip-preview__hint">
      <i class="ri-refresh-line align-middle me-1"></i>{{ $t('documents.preview.flip_hint') }}
    </span>

    <DocumentLightbox
      v-if="visibleFace.url"
      v-model="lightbox"
      :url="visibleFace.url"
      :title="`${label} — ${visibleFace.label}`"
    />
  </div>
</template>

<style scoped>
.flip-preview {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.flip-preview__caption {
  font-size: 0.75rem;
  color: var(--vz-secondary-color);
}

.flip-preview__stage {
  position: relative;
  height: 9rem;
}

.flip-preview__card {
  width: 100%;
  height: 100%;
  padding: 0;
  background: none;
  border: 0;
  border-radius: var(--vz-border-radius);
  /* The depth that makes the turn read as a card rather than a squash. */
  perspective: 1000px;
  cursor: pointer;
}

.flip-preview__inner {
  position: relative;
  display: block;
  width: 100%;
  height: 100%;
  transform-style: preserve-3d;
  transition: transform 0.6s ease;
}

.flip-preview__card.is-flipped .flip-preview__inner {
  transform: rotateY(180deg);
}

.flip-preview__face {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background-color: var(--vz-light);
  border: 1px solid var(--vz-border-color);
  border-radius: var(--vz-border-radius);
  backface-visibility: hidden;
  -webkit-backface-visibility: hidden;
  transition: border-color 0.15s ease;
}

.flip-preview__face--back {
  transform: rotateY(180deg);
}

.flip-preview__card:hover .flip-preview__face,
.flip-preview__card:focus-visible .flip-preview__face {
  border-color: var(--vz-primary);
}

.flip-preview__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.flip-preview__pdf {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  color: var(--vz-danger);
  font-size: 2rem;
  line-height: 1;
}

.flip-preview__pdf-label,
.flip-preview__missing {
  font-size: 0.75rem;
  color: var(--vz-secondary-color);
}

.flip-preview__side {
  position: absolute;
  top: 0.375rem;
  left: 0.375rem;
  padding: 0.125rem 0.5rem;
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--vz-body-color);
  background-color: var(--vz-card-bg);
  border: 1px solid var(--vz-border-color);
  border-radius: 1rem;
  opacity: 0.9;
}

.flip-preview__zoom {
  position: absolute;
  right: 0.375rem;
  bottom: 0.375rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  color: var(--vz-body-color);
  background-color: var(--vz-card-bg);
  border: 1px solid var(--vz-border-color);
  border-radius: 50%;
  opacity: 0.9;
}

.flip-preview__zoom:hover,
.flip-preview__zoom:focus-visible {
  color: var(--vz-primary);
  border-color: var(--vz-primary);
}

.flip-preview__empty {
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

.flip-preview__hint {
  font-size: 0.6875rem;
  color: var(--vz-secondary-color);
}

@media (prefers-reduced-motion: reduce) {
  .flip-preview__inner {
    transition: none;
  }
}
</style>
