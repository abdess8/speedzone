import { getCurrentScope, onScopeDispose, reactive, watch } from 'vue';

/**
 * What the running guide knows about the screen underneath it.
 *
 * A tour that can only say "click here" is a slideshow. To actually *gate* a
 * step — refuse to move on until a file really was parsed, until the wizard
 * really reached the mapping table — the engine has to observe application
 * state it does not own. Passing that state down would couple every guided page
 * to the tour; instead pages publish named signals into this module-level map
 * and the JSON steps refer to them by name. Neither side imports the other.
 *
 * Two kinds of signal, deliberately:
 *
 *   state  mirrors a reactive value for as long as the page is mounted
 *          (`orders.import.step` is 1, 2 or 3), cleared on unmount so a stale
 *          value cannot satisfy a gate on another screen.
 *   pulse  a one-shot event that must outlive its page — `orders.import.saved`
 *          fires while the import screen is being replaced by the order list,
 *          and the closing step is displayed on that new page.
 *
 * Pulses are cleared when a guide starts, so a replay waits for a fresh event
 * instead of walking straight through the gate that a previous run satisfied.
 */

/** name → current value. Reactive so the engine can watch gates directly. */
const signals = reactive({});

/** Names published as pulses, i.e. the ones a new run has to reset. */
const pulseNames = new Set();

/**
 * Publish a value for a named signal.
 *
 * @param {string} name dotted name, e.g. `orders.import.file_ready`
 * @param {*} value anything comparable; `undefined` clears the signal
 */
export function publishSignal(name, value = true) {
  if (value === undefined) {
    delete signals[name];

    return;
  }

  signals[name] = value;
}

/**
 * Fire a one-shot signal that survives the navigation it may trigger.
 *
 * @param {string} name
 */
export function pulseSignal(name) {
  pulseNames.add(name);
  // A timestamp rather than `true`: two consecutive pulses of the same name
  // still register as a change for anything watching it.
  signals[name] = Date.now();
}

/**
 * Drop every pulse, so a starting guide is not satisfied by an earlier run.
 */
export function clearPulses() {
  pulseNames.forEach((name) => {
    delete signals[name];
  });
}

export function signalValue(name) {
  return signals[name];
}

/**
 * Whether a step's `require` clause is currently satisfied.
 *
 * @param {{signal: string, equals?: *}|undefined} requirement
 * @returns {boolean}
 */
export function isRequirementMet(requirement) {
  if (!requirement?.signal) {
    return true;
  }

  const value = signals[requirement.signal];

  if (Object.hasOwn(requirement, 'equals')) {
    return value === requirement.equals;
  }

  return Boolean(value);
}

/**
 * Page-side API.
 *
 * `mirror` is the one every guided page needs: it publishes a reactive value
 * under a name and takes it back down when the page unmounts, which is what
 * keeps `orders.import.step` from claiming the wizard is on step 3 while the
 * reader is looking at the dashboard.
 */
export function useGuideSignals() {
  /**
   * @param {string} name
   * @param {import('vue').Ref|(() => *)} source
   */
  const mirror = (name, source) => {
    const stop = watch(source, (value) => publishSignal(name, value), { immediate: true });

    if (getCurrentScope()) {
      onScopeDispose(() => {
        stop();
        publishSignal(name, undefined);
      });
    }

    return stop;
  };

  return {
    signals,
    mirror,
    signal: publishSignal,
    pulse: pulseSignal,
    value: signalValue,
  };
}

export default useGuideSignals;
