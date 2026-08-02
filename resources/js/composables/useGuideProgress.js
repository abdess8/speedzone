import axios from 'axios';
import { reactive, readonly } from 'vue';

/**
 * Which guides this reader has already been through.
 *
 * Stored twice on purpose. The database is the record — it survives a new
 * device and feeds the Help Center. localStorage is the *cache*, and it exists
 * because the question the engine asks most often ("has he already seen this
 * one, may I offer it again?") has to be answerable without a round trip, and
 * without paying for a query on every single Inertia response.
 *
 * The server wins whenever both speak: the Help Center hydrates this store from
 * its own payload on every visit.
 */

const STORAGE_KEY = 'speedzone.guides.v1';

/** guide key → { completed, completed_count, last_step_index } */
const progress = reactive({});

/** Owner of the cached rows, so a shared browser cannot leak progress. */
let boundUserId = null;

function readStorage() {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);

    return raw ? JSON.parse(raw) : null;
  } catch {
    // Private browsing, disabled storage, corrupted value — the database copy
    // is still there, so this is never worth surfacing.
    return null;
  }
}

function writeStorage() {
  try {
    window.localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ uid: boundUserId, guides: progress })
    );
  } catch {
    /* see readStorage */
  }
}

function blank() {
  return { completed: false, completed_count: 0, last_step_index: 0 };
}

function entry(key) {
  progress[key] ??= blank();

  return progress[key];
}

/**
 * Attach the cache to a user, loading whatever it already holds for him.
 *
 * @param {number|string|null} userId
 */
export function bindGuideUser(userId) {
  if (boundUserId === userId) {
    return;
  }

  boundUserId = userId ?? null;

  Object.keys(progress).forEach((key) => delete progress[key]);

  const cached = readStorage();

  if (cached && cached.uid === boundUserId && cached.guides) {
    Object.assign(progress, cached.guides);
  }
}

/**
 * Replace the cache with the server's version.
 *
 * @param {Record<string, {completed: boolean, completed_count: number, last_step_index: number}>} serverProgress
 */
export function hydrateGuideProgress(serverProgress = {}) {
  Object.keys(progress).forEach((key) => delete progress[key]);
  Object.entries(serverProgress).forEach(([key, row]) => {
    progress[key] = {
      completed: Boolean(row.completed),
      completed_count: Number(row.completed_count ?? 0),
      last_step_index: Number(row.last_step_index ?? 0),
    };
  });

  writeStorage();
}

/**
 * Push one guide's state to the server.
 *
 * Fire and forget: a tour must not stall because the network did, and the
 * local cache already answers every question the interface asks next.
 *
 * @param {string} key
 * @param {'started'|'in_progress'|'completed'} status
 * @param {number} step
 */
function sync(key, status, step) {
  if (typeof route !== 'function') {
    return;
  }

  axios
    .post(route('guides.progress.store', key), { status, step })
    .catch(() => {
      /* cached locally; the next completion will reconcile */
    });
}

export function isGuideCompleted(key) {
  return progress[key]?.completed === true;
}

export function guideProgressFor(key) {
  return progress[key] ?? blank();
}

export function markGuideStarted(key, step = 0) {
  const row = entry(key);
  row.last_step_index = step;
  writeStorage();
  sync(key, 'started', step);
}

/**
 * Remember where the reader is, without telling the server on every step: the
 * cache is enough to offer "resume at step 4" in the same session, and the
 * server hears about it when the tour is left or finished.
 */
export function markGuideStep(key, step) {
  entry(key).last_step_index = step;
  writeStorage();
}

export function markGuideAbandoned(key, step) {
  entry(key).last_step_index = step;
  writeStorage();
  sync(key, 'in_progress', step);
}

export function markGuideCompleted(key) {
  const row = entry(key);
  row.completed = true;
  row.completed_count += 1;
  row.last_step_index = 0;
  writeStorage();
  sync(key, 'completed', 0);
}

/**
 * Forget a guide entirely, on both sides.
 *
 * @param {string} key
 * @returns {Promise<void>}
 */
export async function resetGuideProgress(key) {
  delete progress[key];
  writeStorage();

  if (typeof route === 'function') {
    await axios.delete(route('guides.progress.destroy', key)).catch(() => {});
  }
}

export function useGuideProgress() {
  return {
    progress: readonly(progress),
    isGuideCompleted,
    guideProgressFor,
    resetGuideProgress,
    hydrateGuideProgress,
    bindGuideUser,
  };
}

export default useGuideProgress;
