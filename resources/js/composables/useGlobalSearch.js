import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import i18n from '@/i18n';

/**
 * Global search without its chrome: one field for the whole platform, narrowable
 * to a single object, with a preview of whatever the cursor is on.
 *
 * The server decides what is searchable and what matches — each object gates
 * itself on the same read permission as its list screen, so the scope picker
 * only ever offers what this account may already open.
 *
 * The behaviour lives here rather than in the component because a topbar
 * dropdown and a full-screen overlay are two shapes around identical logic: the
 * dropdown has nowhere to go on a 360px viewport, and the overlay would waste a
 * 1440px one. Only the shape the viewport calls for is ever mounted.
 */
export const ALL_SCOPES = 'all';

const RECENT_LIMIT = 5;
const DEBOUNCE_MS = 250;

export function useGlobalSearch() {
  const page = usePage();

  const term = ref('');
  const scope = ref(ALL_SCOPES);
  const scopes = ref([]);
  const groups = ref([]);
  const minLength = ref(2);

  const open = ref(false);
  const loading = ref(false);
  const failed = ref(false);
  const activeKey = ref(null);
  const recent = ref([]);

  const hits = computed(() =>
    groups.value.flatMap((group) => group.hits.map((hit) => ({ ...hit, groupKey: group.key })))
  );

  const activeHit = computed(
    () => hits.value.find((hit) => hitKey(hit) === activeKey.value) ?? hits.value[0] ?? null
  );

  const currentScope = computed(() => scopes.value.find((entry) => entry.key === scope.value) ?? null);

  const scopeLabel = computed(() => currentScope.value?.label ?? t('search.all_objects'));

  const placeholder = computed(() =>
    currentScope.value
      ? t('search.placeholder_scoped', { object: currentScope.value.label })
      : t('search.placeholder')
  );

  const tooShort = computed(() => term.value.trim().length < minLength.value);

  /** Recent searches belong to the person, not to the browser profile. */
  const storageKey = computed(() => `speedzone.search.recent.${page.props.auth?.user?.id ?? 'guest'}`);

  /** vue-i18n runs in legacy mode, so `$t` is a template helper only. */
  function t(key, params) {
    return i18n.global.t(key, params);
  }

  function hitKey(hit) {
    return `${hit.groupKey}:${hit.id}`;
  }

  function scopeLabelFor(key) {
    return scopes.value.find((entry) => entry.key === key)?.label ?? t('search.all_objects');
  }

  function loadRecent() {
    try {
      const stored = JSON.parse(window.localStorage.getItem(storageKey.value) ?? '[]');
      recent.value = Array.isArray(stored) ? stored.slice(0, RECENT_LIMIT) : [];
    } catch {
      recent.value = [];
    }
  }

  function persistRecent() {
    try {
      window.localStorage.setItem(storageKey.value, JSON.stringify(recent.value));
    } catch {
      // A full or disabled storage costs the history, not the search.
    }
  }

  function rememberSearch() {
    const value = term.value.trim();

    if (value.length < minLength.value) {
      return;
    }

    const entry = { term: value, scope: scope.value };

    recent.value = [
      entry,
      ...recent.value.filter((item) => !(item.term === entry.term && item.scope === entry.scope)),
    ].slice(0, RECENT_LIMIT);

    persistRecent();
  }

  function forgetSearch(entry) {
    recent.value = recent.value.filter(
      (item) => !(item.term === entry.term && item.scope === entry.scope)
    );

    persistRecent();
  }

  function clearRecent() {
    recent.value = [];
    window.localStorage.removeItem(storageKey.value);
  }

  // Only the newest answer may paint: an earlier one landing late would otherwise
  // replace the results of the term the user has already moved on to.
  let currentRequest = 0;
  let debounce = null;

  async function runSearch() {
    const value = term.value.trim();
    const request = ++currentRequest;

    // Whatever was queued is now redundant: this call already carries the
    // current term, and letting the timer fire would search it twice.
    window.clearTimeout(debounce);

    if (value.length < minLength.value) {
      groups.value = [];
      loading.value = false;

      return;
    }

    loading.value = true;
    failed.value = false;

    try {
      const { data } = await axios.get(route('search.global'), {
        params: { q: value, scope: scope.value === ALL_SCOPES ? null : scope.value },
      });

      if (request !== currentRequest) {
        return;
      }

      scopes.value = data.scopes;
      minLength.value = data.min_length;
      groups.value = data.groups;
      activeKey.value = hits.value.length > 0 ? hitKey(hits.value[0]) : null;
    } catch {
      if (request === currentRequest) {
        failed.value = true;
        groups.value = [];
      }
    } finally {
      if (request === currentRequest) {
        loading.value = false;
      }
    }
  }

  /** The scope list is only needed once the bar is actually used. */
  async function loadScopes() {
    if (scopes.value.length > 0) {
      return;
    }

    try {
      const { data } = await axios.get(route('search.global'));
      scopes.value = data.scopes;
      minLength.value = data.min_length;
    } catch {
      // The picker stays on "All", which is the useful default anyway.
    }
  }

  function schedule() {
    window.clearTimeout(debounce);
    debounce = window.setTimeout(runSearch, DEBOUNCE_MS);
  }

  // Typing reveals the results, but emptying the field must not: `clear()` and
  // the overlay's reset both blank the term, and neither is a request to reopen.
  watch(term, (value) => {
    if (value.trim().length > 0) {
      open.value = true;
    }

    schedule();
  });

  /** Called when the field is first reached, on focus or on opening the overlay. */
  function activate() {
    open.value = true;
    loadRecent();
    loadScopes();
  }

  function close() {
    open.value = false;
  }

  /** Back to the state the field is in before anything has been typed. */
  function reset() {
    term.value = '';
    groups.value = [];
    failed.value = false;
    loading.value = false;
    activeKey.value = null;
    currentRequest += 1;
    window.clearTimeout(debounce);
  }

  function clear() {
    term.value = '';
    groups.value = [];
  }

  function pickScope(key) {
    if (scope.value === key) {
      return;
    }

    scope.value = key;
    runSearch();
  }

  function replay(entry) {
    scope.value = entry.scope ?? ALL_SCOPES;
    term.value = entry.term;
    runSearch();
  }

  function select(hit) {
    rememberSearch();
    close();
    router.visit(hit.url);
  }

  /**
   * Keyboard walk through the flattened result list, wrapping at both ends.
   *
   * @param {number} offset -1 or 1
   */
  function move(offset) {
    if (hits.value.length === 0) {
      return;
    }

    open.value = true;

    const index = hits.value.findIndex((hit) => hitKey(hit) === activeKey.value);
    const next = (index + offset + hits.value.length) % hits.value.length;

    activeKey.value = hitKey(hits.value[next]);
  }

  onBeforeUnmount(() => window.clearTimeout(debounce));

  return {
    ALL_SCOPES,
    term,
    scope,
    scopes,
    groups,
    minLength,
    open,
    loading,
    failed,
    activeKey,
    recent,
    hits,
    activeHit,
    currentScope,
    scopeLabel,
    placeholder,
    tooShort,
    t,
    hitKey,
    scopeLabelFor,
    loadRecent,
    rememberSearch,
    forgetSearch,
    clearRecent,
    runSearch,
    loadScopes,
    activate,
    close,
    reset,
    clear,
    pickScope,
    replay,
    select,
    move,
  };
}
