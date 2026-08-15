<script setup>
import { computed } from 'vue';
import { scanSoundMuted, toggleScanSound } from '@/utils/scanSound';

/**
 * The camera window of a QR scanner: an aiming frame, a sweeping beam, and the
 * flash that confirms a parcel just landed in the batch.
 *
 * An operator scanning a trolley holds the phone in one hand and a parcel in the
 * other, and cannot read a list while aiming. Everything here exists so that
 * "did that scan work?" is answered in peripheral vision, without looking away
 * from the label.
 *
 * The `<video>` itself stays with the caller and is passed through the default
 * slot, because the scan loop needs a ref on it.
 */
const props = defineProps({
  /** Drives the aiming frame: no camera running, nothing to aim at. */
  scanning: { type: Boolean, default: false },
  /**
   * `{ kind: 'success'|'error'|'warning', label?: string, id: number }`. The id
   * has to change on every event, since that is what replays the animation when
   * two parcels in a row give the same outcome.
   */
  feedback: { type: Object, default: null },
  /** Short aiming instruction shown under the frame while scanning. */
  hint: { type: String, default: '' },
});

const PULSE_ICONS = {
  success: 'ri-checkbox-circle-line',
  error: 'ri-close-circle-line',
  warning: 'ri-error-warning-line',
};

const pulseIcon = computed(() => PULSE_ICONS[props.feedback?.kind] ?? PULSE_ICONS.warning);
</script>

<template>
  <div class="scanner-viewport ratio ratio-4x3">
    <slot />

    <button
      v-if="scanning"
      type="button"
      class="scanner-mute"
      :aria-label="$t(scanSoundMuted ? 'common.scanner.sound_on' : 'common.scanner.sound_off')"
      :title="$t(scanSoundMuted ? 'common.scanner.sound_on' : 'common.scanner.sound_off')"
      @click="toggleScanSound"
    >
      <i :class="scanSoundMuted ? 'ri-volume-mute-line' : 'ri-volume-up-line'"></i>
    </button>

    <div v-if="scanning" class="scanner-guide">
      <div class="scanner-window" :class="feedback ? `is-${feedback.kind}` : null">
        <span class="scanner-corner corner-tl"></span>
        <span class="scanner-corner corner-tr"></span>
        <span class="scanner-corner corner-bl"></span>
        <span class="scanner-corner corner-br"></span>
        <span class="scanner-beam"></span>
      </div>

      <p v-if="hint" class="scanner-hint">{{ hint }}</p>
    </div>

    <div v-if="!scanning" class="scanner-idle">
      <slot name="idle" />
    </div>

    <div v-if="feedback" :key="feedback.id" class="scanner-pulse" :class="`pulse-${feedback.kind}`">
      <i :class="pulseIcon"></i>
      <span v-if="feedback.label" class="scanner-pulse-label">{{ feedback.label }}</span>
    </div>
  </div>
</template>

<style scoped>
.scanner-viewport {
  overflow: hidden;
  border-radius: var(--vz-border-radius, 0.375rem);
  background-color: #10161d;
}

/* The slotted video carries the caller's scope, so it needs reaching into. */
.scanner-viewport :deep(video) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.scanner-guide,
.scanner-idle,
.scanner-pulse {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}

.scanner-idle {
  padding: 1rem;
  color: rgba(255, 255, 255, 0.75);
  text-align: center;
}

/* Bootstrap stretches every direct child of a .ratio box over the whole frame,
   which a corner button has to opt out of. */
.scanner-viewport > .scanner-mute {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  left: auto;
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background-color: rgba(0, 0, 0, 0.5);
  color: #fff;
  font-size: 1.1rem;
  line-height: 1;
  z-index: 2;
}

/* One box-shadow dims the whole frame except the square the label belongs in. */
.scanner-window {
  position: relative;
  width: 55%;
  max-width: 13rem;
  aspect-ratio: 1;
  border-radius: 0.85rem;
  box-shadow: 0 0 0 100vmax rgba(0, 0, 0, 0.45);
  transition: box-shadow 0.25s ease;
}

.scanner-window.is-success {
  box-shadow: 0 0 0 100vmax rgba(10, 179, 156, 0.35);
}

.scanner-window.is-error {
  box-shadow: 0 0 0 100vmax rgba(240, 101, 72, 0.35);
}

.scanner-window.is-warning {
  box-shadow: 0 0 0 100vmax rgba(247, 184, 75, 0.35);
}

.scanner-corner {
  position: absolute;
  width: 1.6rem;
  height: 1.6rem;
  border: 3px solid #fff;
  transition: border-color 0.25s ease;
}

.corner-tl {
  top: -3px;
  left: -3px;
  border-right: 0;
  border-bottom: 0;
  border-top-left-radius: 0.85rem;
}

.corner-tr {
  top: -3px;
  right: -3px;
  border-left: 0;
  border-bottom: 0;
  border-top-right-radius: 0.85rem;
}

.corner-bl {
  bottom: -3px;
  left: -3px;
  border-right: 0;
  border-top: 0;
  border-bottom-left-radius: 0.85rem;
}

.corner-br {
  bottom: -3px;
  right: -3px;
  border-left: 0;
  border-top: 0;
  border-bottom-right-radius: 0.85rem;
}

.is-success .scanner-corner {
  border-color: #0ab39c;
}

.is-error .scanner-corner {
  border-color: #f06548;
}

.is-warning .scanner-corner {
  border-color: #f7b84b;
}

.scanner-beam {
  position: absolute;
  top: 6%;
  left: 6%;
  right: 6%;
  height: 2px;
  border-radius: 2px;
  background: linear-gradient(90deg, transparent, #0ab39c, transparent);
  box-shadow: 0 0 12px rgba(10, 179, 156, 0.9);
  animation: scanner-sweep 2.2s ease-in-out infinite;
}

@keyframes scanner-sweep {
  0%,
  100% {
    top: 6%;
  }

  50% {
    top: 94%;
  }
}

.scanner-hint {
  margin: 0.5rem 0 0;
  padding: 0.25rem 0.75rem;
  border-radius: 999px;
  background-color: rgba(0, 0, 0, 0.45);
  color: #fff;
  font-size: 0.75rem;
}

.scanner-pulse {
  gap: 0.35rem;
  color: #fff;
  animation: scanner-pulse 900ms ease-out forwards;
}

.scanner-pulse i {
  font-size: 3.25rem;
  line-height: 1;
}

.scanner-pulse-label {
  font-weight: 600;
  font-size: 0.875rem;
}

.pulse-success {
  background-color: rgba(10, 179, 156, 0.55);
}

.pulse-error {
  background-color: rgba(240, 101, 72, 0.55);
}

.pulse-warning {
  background-color: rgba(247, 184, 75, 0.6);
}

@keyframes scanner-pulse {
  0% {
    opacity: 0;
    transform: scale(1.12);
  }

  18% {
    opacity: 1;
    transform: scale(1);
  }

  100% {
    opacity: 0;
  }
}

@keyframes scanner-pulse-fade {
  0%,
  80% {
    opacity: 1;
  }

  100% {
    opacity: 0;
  }
}

/* The sweep and the zoom are decoration; the flash itself still has to show up. */
@media (prefers-reduced-motion: reduce) {
  .scanner-beam {
    animation: none;
    top: 50%;
  }

  .scanner-pulse {
    animation-name: scanner-pulse-fade;
  }
}
</style>
