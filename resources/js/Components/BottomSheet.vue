<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * iOS-style bottom sheet used instead of a centered modal on mobile.
 *
 * A centered dialog forces a one-handed user to reach the middle of the screen;
 * a sheet anchored to the bottom keeps every control inside thumb reach, which
 * matters for drivers processing orders on the move.
 *
 * Supports drag-to-dismiss, backdrop tap, Escape, body scroll lock and the iOS
 * home-indicator safe area.
 */
const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  /**
   * When false the sheet can only be closed programmatically — used while a
   * request is in flight so a stray swipe cannot abandon a pending action.
   */
  dismissible: { type: Boolean, default: true },
  /**
   * Width once the sheet becomes a centered card on tablets and up. Ignored on
   * phones, where it is always full-width. `lg`/`xl` exist for the pickers that
   * embed a selection table and cannot work in a single narrow column.
   *
   * @values md, lg, xl
   */
  size: { type: String, default: 'md' },
});

const emit = defineEmits(['close']);

const panel = ref(null);
const dragOffset = ref(0);
const isDragging = ref(false);

let dragStartY = 0;
/** Past this many pixels the release is treated as a dismiss rather than a snap-back. */
const DISMISS_THRESHOLD = 110;

// The drag travels through a custom property rather than an inline `transform`,
// because on a touch-capable tablet the sheet is also centered with a translate
// and an inline transform would drop that centering mid-drag.
const panelStyle = computed(() => ({
  '--sheet-drag': `${dragOffset.value}px`,
  transition: isDragging.value ? 'none' : '',
}));

function close() {
  if (!props.dismissible) {
    return;
  }

  emit('close');
}

function onTouchStart(event) {
  if (!props.dismissible) {
    return;
  }

  isDragging.value = true;
  dragStartY = event.touches[0].clientY;
}

function onTouchMove(event) {
  if (!isDragging.value) {
    return;
  }

  // Downward drags only: an upward pull should not detach the sheet.
  dragOffset.value = Math.max(0, event.touches[0].clientY - dragStartY);
}

function onTouchEnd() {
  if (!isDragging.value) {
    return;
  }

  isDragging.value = false;

  if (dragOffset.value > DISMISS_THRESHOLD) {
    dragOffset.value = 0;
    close();

    return;
  }

  dragOffset.value = 0;
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    close();
  }
}

/**
 * Locking the body prevents the page behind the sheet from scrolling under the
 * finger on iOS Safari.
 */
function setBodyLock(locked) {
  if (typeof document === 'undefined') {
    return;
  }

  document.body.style.overflow = locked ? 'hidden' : '';
}

watch(
  () => props.show,
  async (visible) => {
    setBodyLock(visible);
    dragOffset.value = 0;

    if (visible) {
      document.addEventListener('keydown', onKeydown);
    } else {
      document.removeEventListener('keydown', onKeydown);
    }
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  setBodyLock(false);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="sheet-backdrop">
      <div v-if="show" class="sheet-backdrop" @click="close"></div>
    </Transition>

    <Transition name="sheet-panel">
      <div
        v-if="show"
        ref="panel"
        class="sheet-panel"
        :class="`sheet-panel--${size}`"
        role="dialog"
        aria-modal="true"
        :aria-label="title || undefined"
        :style="panelStyle"
      >
        <div
          class="sheet-grabber-area"
          @touchstart.passive="onTouchStart"
          @touchmove.passive="onTouchMove"
          @touchend="onTouchEnd"
          @touchcancel="onTouchEnd"
        >
          <span class="sheet-grabber" aria-hidden="true"></span>
        </div>

        <div v-if="title || subtitle || $slots.header" class="sheet-header">
          <slot name="header">
            <h5 class="sheet-title">{{ title }}</h5>
            <p v-if="subtitle" class="sheet-subtitle">{{ subtitle }}</p>
          </slot>
        </div>

        <div class="sheet-body">
          <slot></slot>
        </div>

        <div v-if="$slots.footer" class="sheet-footer">
          <slot name="footer"></slot>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.sheet-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1055;
  background-color: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(2px);
}

.sheet-panel {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 1056;
  display: flex;
  max-height: 92vh;
  flex-direction: column;
  border-radius: 1.25rem 1.25rem 0 0;
  background-color: var(--vz-modal-bg, var(--vz-card-bg, #fff));
  box-shadow: 0 -0.5rem 1.75rem rgba(0, 0, 0, 0.22);
  /* Keep the last control clear of the iOS home indicator. */
  padding-bottom: env(safe-area-inset-bottom, 0);
  transform: translateY(var(--sheet-drag, 0px));
  transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1);
}

/* On tablets and up the sheet becomes a centered, width-capped card. */
@media (min-width: 768px) {
  .sheet-panel {
    right: auto;
    left: 50%;
    transform: translate(-50%, var(--sheet-drag, 0px));
    border-radius: 1.25rem;
    bottom: 1.5rem;
  }

  .sheet-panel--md {
    width: 30rem;
  }

  .sheet-panel--lg {
    width: 40rem;
  }

  .sheet-panel--xl {
    width: min(56rem, calc(100vw - 3rem));
  }
}

.sheet-grabber-area {
  display: flex;
  justify-content: center;
  padding: 0.625rem 0 0.25rem;
  /* Enlarged hit area: the visible bar alone is too small a touch target. */
  touch-action: none;
  cursor: grab;
}

.sheet-grabber {
  display: block;
  width: 2.5rem;
  height: 0.3125rem;
  border-radius: 999px;
  background-color: var(--vz-border-color, #e9ebec);
}

.sheet-header {
  padding: 0.25rem 1.25rem 0.75rem;
  text-align: center;
}

.sheet-title {
  margin-bottom: 0.125rem;
  font-size: 1rem;
  font-weight: 600;
}

.sheet-subtitle {
  margin-bottom: 0;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.8125rem;
}

.sheet-body {
  overflow-y: auto;
  padding: 0.25rem 1.25rem 1rem;
  -webkit-overflow-scrolling: touch;
}

.sheet-footer {
  padding: 0.75rem 1.25rem 1rem;
  border-top: 1px solid var(--vz-border-color, #e9ebec);
}

.sheet-backdrop-enter-active,
.sheet-backdrop-leave-active {
  transition: opacity 0.25s ease;
}

.sheet-backdrop-enter-from,
.sheet-backdrop-leave-to {
  opacity: 0;
}

.sheet-panel-enter-active,
.sheet-panel-leave-active {
  transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
}

.sheet-panel-enter-from,
.sheet-panel-leave-to {
  transform: translateY(100%);
}

@media (min-width: 768px) {
  .sheet-panel-enter-from,
  .sheet-panel-leave-to {
    transform: translate(-50%, calc(100% + 1.5rem));
  }
}

@media (prefers-reduced-motion: reduce) {
  .sheet-panel,
  .sheet-panel-enter-active,
  .sheet-panel-leave-active,
  .sheet-backdrop-enter-active,
  .sheet-backdrop-leave-active {
    transition: none;
  }
}
</style>
