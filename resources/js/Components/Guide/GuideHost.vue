<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useIsMobile } from '@/composables/useMediaQuery';
import { useGuide } from '@/composables/useGuide';
import { publishSignal } from '@/composables/useGuideSignals';
import { bindGuideUser } from '@/composables/useGuideProgress';
import GuideStepCard from './GuideStepCard.vue';
import GuideConfetti from './GuideConfetti.vue';

/**
 * The tour overlay: spotlight, tooltip, bottom sheet.
 *
 * Mounted once next to the assistant in `Layouts/main.vue`, because a guide
 * crosses screens — the bulk import tour starts on the wizard and ends on the
 * order list — and anything mounted inside a page would die halfway through.
 *
 * The dimming is four panels around the highlighted element rather than one
 * overlay with a hole punched in it: with a hole, the element underneath is
 * still covered and every click has to be forwarded by hand. With four panels
 * the element is genuinely uncovered, so the reader really does drop his file
 * on the real dropzone — which is the entire point of a guide that gates its
 * steps on actions actually being performed.
 */

const { t } = useI18n();
const page = usePage();
const isMobile = useIsMobile();

const {
  activeGuide,
  currentStep,
  stepIndex,
  totalSteps,
  running,
  isFirstStep,
  isLastStep,
  isWaiting,
  activeTarget,
  i18nBase,
  nextStep,
  previousStep,
  stopGuide,
  resumePendingGuide,
} = useGuide();

/* ------------------------------------------------------------------ wording */

const stepI18n = computed(() =>
  currentStep.value ? `${i18nBase.value}.${currentStep.value.id}` : ''
);

const title = computed(() => (stepI18n.value ? t(`${stepI18n.value}.title`) : ''));
const body = computed(() => (stepI18n.value ? t(`${stepI18n.value}.body`) : ''));

// Only gated steps carry a hint, and every gated step defines one — so this is
// never asked for a key that does not exist.
const hint = computed(() =>
  currentStep.value?.require ? t(`${stepI18n.value}.hint`) : ''
);

const ctaLabel = computed(() =>
  currentStep.value?.cta ? t(`${stepI18n.value}.cta`) : ''
);

const labels = computed(() => ({
  progress: t('guides.tour.progress', {
    current: stepIndex.value + 1,
    total: totalSteps.value,
  }),
  start: t('guides.tour.start'),
  next: t('guides.tour.next'),
  previous: t('guides.tour.previous'),
  finish: t('guides.tour.finish'),
  quit: t('guides.tour.quit'),
  quitShort: t('guides.tour.quit_short'),
  waiting: t('guides.tour.waiting'),
}));

/* ----------------------------------------------------------------- geometry */

const SPOTLIGHT_PADDING = 8;
const CARD_GAP = 14;
const VIEWPORT_MARGIN = 16;

const rect = ref(null);
const cardEl = ref(null);
const cardSize = ref({ width: 360, height: 220 });
const confirmingQuit = ref(false);

let targetEl = null;
let frame = 0;

function sameRect(a, b) {
  if (!a || !b) {
    return a === b;
  }

  return (
    Math.abs(a.top - b.top) < 0.5 &&
    Math.abs(a.left - b.left) < 0.5 &&
    Math.abs(a.width - b.width) < 0.5 &&
    Math.abs(a.height - b.height) < 0.5
  );
}

/**
 * Re-read the target every frame.
 *
 * Cheaper than it looks — one `querySelector` and one `getBoundingClientRect`
 * on a single node — and it covers scrolling, resizing, a collapsing card, a
 * step whose target has not been rendered yet and a target that Vue replaced
 * with a new node, all without a single listener or observer.
 */
function track() {
  frame = requestAnimationFrame(track);

  if (cardEl.value) {
    const { offsetWidth, offsetHeight } = cardEl.value;

    if (offsetWidth && offsetHeight) {
      cardSize.value = { width: offsetWidth, height: offsetHeight };
    }
  }

  const selector = activeTarget.value;
  const element = selector ? document.querySelector(selector) : null;

  if (!element) {
    targetEl = null;
    rect.value = null;

    return;
  }

  if (element !== targetEl) {
    targetEl = element;
    element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
  }

  const box = element.getBoundingClientRect();
  const next = { top: box.top, left: box.left, width: box.width, height: box.height };

  if (!sameRect(rect.value, next)) {
    rect.value = next;
  }
}

watch(
  running,
  (isRunning) => {
    cancelAnimationFrame(frame);
    targetEl = null;
    rect.value = null;
    confirmingQuit.value = false;

    if (isRunning) {
      frame = requestAnimationFrame(track);
    }
  },
  { immediate: false }
);

/** The highlighted area, padded and kept inside the viewport. */
const hole = computed(() => {
  if (!rect.value || rect.value.width === 0 || rect.value.height === 0) {
    return null;
  }

  const padding = currentStep.value?.padding ?? SPOTLIGHT_PADDING;
  const top = Math.max(0, rect.value.top - padding);
  const left = Math.max(0, rect.value.left - padding);
  const bottom = Math.min(window.innerHeight, rect.value.top + rect.value.height + padding);
  const right = Math.min(window.innerWidth, rect.value.left + rect.value.width + padding);

  return { top, left, bottom, right, width: right - left, height: bottom - top };
});

const holeStyle = computed(() =>
  hole.value
    ? {
        top: `${hole.value.top}px`,
        left: `${hole.value.left}px`,
        width: `${hole.value.width}px`,
        height: `${hole.value.height}px`,
      }
    : null
);

/** Blocking mask panels: top, bottom, left, right of the spotlight. */
const masks = computed(() => {
  if (!hole.value) {
    return [{ top: 0, left: 0, right: 0, bottom: 0 }];
  }

  const { top, left, bottom, right } = hole.value;

  return [
    { top: 0, left: 0, width: '100%', height: `${top}px` },
    { top: `${bottom}px`, left: 0, width: '100%', bottom: 0 },
    { top: `${top}px`, left: 0, width: `${left}px`, height: `${bottom - top}px` },
    { top: `${top}px`, left: `${right}px`, right: 0, height: `${bottom - top}px` },
  ];
});

/**
 * Side the tooltip opens on, preferring the step's own wish but never off
 * screen. Falls back to the side with the most room.
 */
const placement = computed(() => {
  if (!hole.value) {
    return 'center';
  }

  const preferred = currentStep.value?.placement ?? 'auto';
  const { width, height } = cardSize.value;

  const room = {
    bottom: window.innerHeight - hole.value.bottom - CARD_GAP - VIEWPORT_MARGIN,
    top: hole.value.top - CARD_GAP - VIEWPORT_MARGIN,
    right: window.innerWidth - hole.value.right - CARD_GAP - VIEWPORT_MARGIN,
    left: hole.value.left - CARD_GAP - VIEWPORT_MARGIN,
  };

  const needed = (side) => (side === 'top' || side === 'bottom' ? height : width);
  const order =
    preferred === 'auto'
      ? ['bottom', 'top', 'right', 'left']
      : [preferred, 'bottom', 'top', 'right', 'left'];

  return order.find((side) => room[side] >= needed(side)) ?? 'center';
});

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), Math.max(min, max));
}

const cardStyle = computed(() => {
  if (isMobile.value || placement.value === 'center' || !hole.value) {
    return null;
  }

  const { width, height } = cardSize.value;
  const maxLeft = window.innerWidth - width - VIEWPORT_MARGIN;
  const maxTop = window.innerHeight - height - VIEWPORT_MARGIN;

  if (placement.value === 'top' || placement.value === 'bottom') {
    const top =
      placement.value === 'bottom'
        ? hole.value.bottom + CARD_GAP
        : hole.value.top - CARD_GAP - height;

    return {
      top: `${clamp(top, VIEWPORT_MARGIN, maxTop)}px`,
      left: `${clamp(
        hole.value.left + hole.value.width / 2 - width / 2,
        VIEWPORT_MARGIN,
        maxLeft
      )}px`,
    };
  }

  const left =
    placement.value === 'right' ? hole.value.right + CARD_GAP : hole.value.left - CARD_GAP - width;

  return {
    top: `${clamp(
      hole.value.top + hole.value.height / 2 - height / 2,
      VIEWPORT_MARGIN,
      maxTop
    )}px`,
    left: `${clamp(left, VIEWPORT_MARGIN, maxLeft)}px`,
  };
});

/* ------------------------------------------------------------------ actions */

/**
 * Ask before dropping a tour, in the card itself.
 *
 * A SweetAlert dialog would open *underneath* the overlay — the guide sits on
 * its own stacking layer above every modal in the app — and raising its z-index
 * from here would mean shipping a global style for one confirmation.
 */
function requestQuit() {
  confirmingQuit.value = true;
}

function confirmQuit() {
  confirmingQuit.value = false;
  stopGuide();
}

function followCta() {
  const target = currentStep.value?.cta?.route;

  stopGuide();

  if (target && typeof route === 'function') {
    router.visit(route(target));
  }
}

function onKeydown(event) {
  if (!running.value) {
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    confirmingQuit.value ? confirmQuit() : requestQuit();
  }
}

/* -------------------------------------------------------------------- setup */

const userId = computed(() => page.props.auth?.user?.id ?? null);

watch(userId, (id) => bindGuideUser(id), { immediate: true });

/**
 * Where the application currently stands, published as an ordinary signal.
 *
 * This is what lets a guide cross screens without instrumenting any of them: a
 * step that says "click Create" simply waits for `app.route` to become
 * `stores.create`. Pages that already redirect on success — and most of them do
 * — therefore need no guide-specific code at all.
 */
function currentRouteName() {
  if (typeof route !== 'function') {
    return null;
  }

  try {
    return route().current() || null;
  } catch {
    return null;
  }
}

watch(
  () => page.url,
  async () => {
    // Inertia updates the history entry around the component swap; waiting a
    // tick guarantees Ziggy reads the new location and not the old one.
    await nextTick();
    publishSignal('app.route', currentRouteName());
    publishSignal('app.path', window.location.pathname);
  },
  { immediate: true }
);

onMounted(() => {
  document.addEventListener('keydown', onKeydown);
  // Only fires after a full page load: an Inertia visit keeps the engine's
  // module state, so there is nothing to pick back up.
  resumePendingGuide();
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  cancelAnimationFrame(frame);
});

const isCelebrating = computed(() => running.value && currentStep.value?.kind === 'finish');
const blocksTarget = computed(() => currentStep.value?.interactive === false);
</script>

<template>
  <Teleport to="body">
    <Transition name="guide-fade">
      <div v-if="running" class="guide-layer" :class="{ 'guide-layer--mobile': isMobile }">
        <div
          v-for="(mask, index) in masks"
          :key="index"
          class="guide-mask"
          :style="mask"
          @click="requestQuit"
        ></div>

        <div v-if="holeStyle" class="guide-ring" :style="holeStyle"></div>

        <!-- Steps that only explain something keep their hands off the page. -->
        <div v-if="holeStyle && blocksTarget" class="guide-shield" :style="holeStyle"></div>

        <div
          ref="cardEl"
          class="guide-pop"
          :class="[
            isMobile ? 'guide-pop--sheet' : `guide-pop--${placement}`,
            { 'guide-pop--centered': !isMobile && placement === 'center' },
          ]"
          :style="cardStyle"
          role="dialog"
          aria-modal="false"
          :aria-label="title"
        >
          <span v-if="isMobile" class="guide-pop__grabber" aria-hidden="true"></span>

          <div v-if="confirmingQuit" class="guide-confirm">
            <h5 class="guide-confirm__title">{{ $t('guides.tour.quit_confirm_title') }}</h5>
            <p class="guide-confirm__text">{{ $t('guides.tour.quit_confirm_text') }}</p>
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-sm btn-light" @click="confirmingQuit = false">
                {{ $t('guides.tour.quit_confirm_no') }}
              </button>
              <button type="button" class="btn btn-sm btn-danger" @click="confirmQuit">
                {{ $t('guides.tour.quit_confirm_yes') }}
              </button>
            </div>
          </div>

          <GuideStepCard
            v-else
            :title="title"
            :body="body"
            :hint="hint"
            :waiting="isWaiting"
            :step-number="stepIndex + 1"
            :total-steps="totalSteps"
            :is-first="isFirstStep"
            :is-last="isLastStep"
            :kind="currentStep?.kind ?? 'step'"
            :cta-label="ctaLabel"
            :labels="labels"
            @next="nextStep"
            @previous="previousStep"
            @quit="requestQuit"
            @cta="followCta"
          />
        </div>

        <GuideConfetti v-if="isCelebrating" />
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Above every modal and sheet in the application: the guide is the layer that
   explains the others, so nothing may cover it. */
.guide-layer {
  position: fixed;
  inset: 0;
  z-index: 2060;
  pointer-events: none;
}

.guide-mask {
  position: fixed;
  background: rgba(15, 23, 42, 0.55);
  pointer-events: auto;
}

.guide-ring {
  position: fixed;
  z-index: 2070;
  border-radius: 0.5rem;
  box-shadow: 0 0 0 2px var(--vz-primary, #405189), 0 0 0 6px rgba(64, 81, 137, 0.25);
  pointer-events: none;
  transition: top 0.18s ease, left 0.18s ease, width 0.18s ease, height 0.18s ease;
}

.guide-shield {
  position: fixed;
  z-index: 2071;
  pointer-events: auto;
}

.guide-pop {
  position: fixed;
  z-index: 2075;
  width: 22.5rem;
  max-width: calc(100vw - 2rem);
  padding: 1rem;
  border-radius: 0.75rem;
  background: var(--vz-card-bg, #fff);
  box-shadow: 0 1.25rem 2.5rem rgba(15, 23, 42, 0.28);
  pointer-events: auto;
}

.guide-pop--centered {
  top: 50%;
  left: 50%;
  width: 26rem;
  transform: translate(-50%, -50%);
}

/* Mobile: a sheet in thumb reach instead of a tooltip too small to read, and
   deliberately non-modal — the reader has to be able to touch the element the
   step is pointing at. */
.guide-pop--sheet {
  right: 0;
  bottom: 0;
  left: 0;
  top: auto;
  width: auto;
  max-width: none;
  max-height: 70vh;
  overflow-y: auto;
  padding: 0.5rem 1.125rem 1rem;
  border-radius: 1.25rem 1.25rem 0 0;
  padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
  box-shadow: 0 -0.5rem 1.75rem rgba(15, 23, 42, 0.3);
}

.guide-pop__grabber {
  display: block;
  width: 2.5rem;
  height: 0.3125rem;
  margin: 0.25rem auto 0.75rem;
  border-radius: 999px;
  background-color: var(--vz-border-color, #e9ebec);
}

.guide-confirm {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.guide-confirm__title {
  margin: 0;
  font-size: 0.9375rem;
}

.guide-confirm__text {
  margin: 0 0 0.5rem;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.8125rem;
}

.guide-fade-enter-active,
.guide-fade-leave-active {
  transition: opacity 0.2s ease;
}

.guide-fade-enter-from,
.guide-fade-leave-to {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .guide-ring,
  .guide-fade-enter-active,
  .guide-fade-leave-active {
    transition: none;
  }
}
</style>
