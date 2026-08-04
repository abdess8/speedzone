import { computed, ref, shallowRef, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { getGuide, guideI18nKey } from '@/guides/index.js';
import { clearPulses, isRequirementMet } from '@/composables/useGuideSignals';
import {
  guideProgressFor,
  markGuideAbandoned,
  markGuideCompleted,
  markGuideStarted,
  markGuideStep,
} from '@/composables/useGuideProgress';

/**
 * The interactive tour engine.
 *
 * Module-level state, like the assistant: a tour outlives the page it started
 * on. The bulk import guide begins on `/orders/import` and ends on the order
 * list, because that is where the application lands once the import succeeds —
 * had the state lived in a component, the closing step would have died with the
 * screen that triggered it.
 *
 * This file owns *sequence and rules*: which step, whether it may be left, what
 * gets remembered. Geometry — where the spotlight sits, which side the tooltip
 * opens on — belongs to `GuideHost.vue`, because only the DOM knows.
 */

/** Survives a hard reload between "start this guide" and the target screen. */
const PENDING_KEY = 'owl.guide.pending';

const activeGuide = shallowRef(null);
const stepIndex = ref(0);
const running = ref(false);

/** Set once the closing step is reached, so leaving is not an abandon. */
const completedThisRun = ref(false);

const currentStep = computed(() => activeGuide.value?.steps[stepIndex.value] ?? null);
const totalSteps = computed(() => activeGuide.value?.steps.length ?? 0);
const isFirstStep = computed(() => stepIndex.value === 0);
const isLastStep = computed(() => stepIndex.value >= totalSteps.value - 1);

/** Gate of the current step: false while the reader still owes us an action. */
const requirementMet = computed(() => isRequirementMet(currentStep.value?.require));
const isWaiting = computed(() => running.value && !requirementMet.value);

/**
 * The element the spotlight should sit on right now.
 *
 * While a gate is unmet a step may point somewhere else entirely — the mapping
 * step highlights the wizard's "Next" button until the mapping table it really
 * wants actually exists.
 */
const activeTarget = computed(() => {
  const step = currentStep.value;

  if (!step) {
    return null;
  }

  if (!requirementMet.value && step.pendingTarget) {
    return step.pendingTarget;
  }

  return step.target ?? null;
});

/** i18n branch of the running guide, e.g. `guides.tours.orders_import`. */
const i18nBase = computed(() =>
  activeGuide.value ? `guides.tours.${guideI18nKey(activeGuide.value.key)}` : ''
);

/*
|--------------------------------------------------------------------------
| Routing helpers
|--------------------------------------------------------------------------
*/

function guideUrl(guide) {
  return typeof route === 'function' ? route(guide.route) : null;
}

function isOnGuideRoute(guide) {
  if (typeof route !== 'function') {
    return false;
  }

  try {
    return route().current(guide.route);
  } catch {
    return false;
  }
}

function savePending(key, index) {
  try {
    window.sessionStorage.setItem(PENDING_KEY, JSON.stringify({ key, index }));
  } catch {
    /* the visit is an SPA one anyway; this is only a safety net */
  }
}

function clearPending() {
  try {
    window.sessionStorage.removeItem(PENDING_KEY);
  } catch {
    /* see savePending */
  }
}

/*
|--------------------------------------------------------------------------
| Sequence
|--------------------------------------------------------------------------
*/

function enterStep(index) {
  const guide = activeGuide.value;

  if (!guide) {
    return;
  }

  stepIndex.value = Math.min(Math.max(index, 0), guide.steps.length - 1);

  const step = guide.steps[stepIndex.value];

  // Reaching the closing card *is* finishing: someone who closes the browser on
  // the congratulations screen has been through the whole thing, and being
  // offered the guide again as unread would be plainly wrong.
  if (step?.kind === 'finish') {
    completedThisRun.value = true;
    markGuideCompleted(guide.key);

    return;
  }

  markGuideStep(guide.key, stepIndex.value);
}

function begin() {
  running.value = true;
  completedThisRun.value = false;
  markGuideStarted(activeGuide.value.key, stepIndex.value);
  enterStep(stepIndex.value);
}

/**
 * Run a guide, travelling to its screen first if we are not on it.
 *
 * @param {string} key
 * @param {{ stepIndex?: number }} options
 * @returns {boolean} false when no such guide is registered
 */
export function startGuide(key, { stepIndex: from = 0 } = {}) {
  const guide = getGuide(key);

  if (!guide) {
    return false;
  }

  // A replay must wait for its own events, not inherit the ones that satisfied
  // the previous run.
  clearPulses();

  activeGuide.value = guide;
  stepIndex.value = from;

  if (isOnGuideRoute(guide)) {
    begin();

    return true;
  }

  const url = guideUrl(guide);

  if (!url) {
    activeGuide.value = null;

    return false;
  }

  // Hold the overlay back until the destination is on screen, otherwise the
  // first step would spotlight an element that does not exist yet.
  running.value = false;
  savePending(key, from);

  router.visit(url, {
    onSuccess: () => begin(),
    onError: () => {
      activeGuide.value = null;
    },
    onFinish: () => clearPending(),
  });

  return true;
}

/**
 * Pick a guide back up after a full page load (not an Inertia visit).
 */
export function resumePendingGuide() {
  let pending = null;

  try {
    pending = JSON.parse(window.sessionStorage.getItem(PENDING_KEY) ?? 'null');
  } catch {
    pending = null;
  }

  clearPending();

  if (!pending?.key) {
    return;
  }

  const guide = getGuide(pending.key);

  if (guide && isOnGuideRoute(guide)) {
    startGuide(pending.key, { stepIndex: pending.index ?? 0 });
  }
}

export function nextStep() {
  if (!running.value) {
    return;
  }

  if (isLastStep.value) {
    stopGuide();

    return;
  }

  enterStep(stepIndex.value + 1);
}

export function previousStep() {
  if (running.value && !isFirstStep.value) {
    enterStep(stepIndex.value - 1);
  }
}

export function goToStep(index) {
  if (running.value) {
    enterStep(index);
  }
}

/**
 * Close the tour, remembering where it was left unless it was finished.
 */
export function stopGuide() {
  const guide = activeGuide.value;

  if (guide && !completedThisRun.value) {
    markGuideAbandoned(guide.key, stepIndex.value);
  }

  running.value = false;
  activeGuide.value = null;
  stepIndex.value = 0;
  completedThisRun.value = false;
  clearPending();
}

/**
 * Where a "resume" button should drop the reader back in.
 *
 * @param {string} key
 * @returns {number}
 */
export function resumeIndexFor(key) {
  return guideProgressFor(key).last_step_index ?? 0;
}

/*
|--------------------------------------------------------------------------
| Auto-advance
|--------------------------------------------------------------------------
*/

// Only a change that happens *while the step is on screen* moves the tour on.
// Comparing the step index rules out the other way the gate can flip — landing
// on a step whose requirement was already satisfied — which would otherwise
// skip that step before the reader had read a word of it.
watch([stepIndex, requirementMet], ([index, met], [previousIndex, wasMet]) => {
  if (!running.value || index !== previousIndex) {
    return;
  }

  if (met && !wasMet && currentStep.value?.autoAdvance) {
    nextStep();
  }
});

export function useGuide() {
  return {
    activeGuide,
    currentStep,
    stepIndex,
    totalSteps,
    running,
    isFirstStep,
    isLastStep,
    isWaiting,
    requirementMet,
    activeTarget,
    i18nBase,
    startGuide,
    nextStep,
    previousStep,
    goToStep,
    stopGuide,
    resumePendingGuide,
    resumeIndexFor,
  };
}

export default useGuide;
