<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import i18n from '@/i18n';

/**
 * Global search, in the Salesforce mould: one field for the whole platform,
 * narrowable to a single object, with a preview of whatever the cursor is on.
 *
 * The server decides what is searchable and what matches — each object gates
 * itself on the same read permission as its list screen, so the scope picker
 * only ever offers what this account may already open.
 *
 * The preview arrives with the results rather than being fetched on hover: it
 * has to be on screen the instant a row lights up, and one request per row
 * would trail the cursor down the list.
 */
const RECENT_LIMIT = 5;
const DEBOUNCE_MS = 250;
const ALL = 'all';

const page = usePage();

const term = ref('');
const scope = ref(ALL);
const scopes = ref([]);
const groups = ref([]);
const minLength = ref(2);

const open = ref(false);
const loading = ref(false);
const failed = ref(false);
const activeKey = ref(null);
const recent = ref([]);

const field = ref(null);

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
const storageKey = computed(() => `owl.search.recent.${page.props.auth?.user?.id ?? 'guest'}`);

/** vue-i18n runs in legacy mode, so `$t` is a template helper only. */
function t(key, params) {
  return i18n.global.t(key, params);
}

function hitKey(hit) {
  return `${hit.groupKey}:${hit.id}`;
}

function loadRecent() {
  try {
    const stored = JSON.parse(window.localStorage.getItem(storageKey.value) ?? '[]');
    recent.value = Array.isArray(stored) ? stored.slice(0, RECENT_LIMIT) : [];
  } catch {
    recent.value = [];
  }
}

function rememberSearch() {
  const value = term.value.trim();

  if (value.length < minLength.value) {
    return;
  }

  const entry = { term: value, scope: scope.value };
  const next = [
    entry,
    ...recent.value.filter((item) => !(item.term === entry.term && item.scope === entry.scope)),
  ].slice(0, RECENT_LIMIT);

  recent.value = next;

  try {
    window.localStorage.setItem(storageKey.value, JSON.stringify(next));
  } catch {
    // A full or disabled storage costs the history, not the search.
  }
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

  if (value.length < minLength.value) {
    groups.value = [];
    loading.value = false;

    return;
  }

  loading.value = true;
  failed.value = false;

  try {
    const { data } = await axios.get(route('search.global'), {
      params: { q: value, scope: scope.value === ALL ? null : scope.value },
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

watch(term, () => {
  open.value = true;
  schedule();
});

watch(scope, () => {
  runSearch();
  field.value?.focus();
});

function onFocus() {
  open.value = true;
  loadRecent();
  loadScopes();
}

function close() {
  open.value = false;
}

function clear() {
  term.value = '';
  groups.value = [];
  field.value?.focus();
}

function pickScope(key) {
  scope.value = key;
}

function replay(entry) {
  scope.value = entry.scope ?? ALL;
  term.value = entry.term;
  runSearch();
}

function select(hit) {
  rememberSearch();
  close();
  router.visit(hit.url);
}

function move(offset) {
  if (hits.value.length === 0) {
    return;
  }

  open.value = true;

  const index = hits.value.findIndex((hit) => hitKey(hit) === activeKey.value);
  const next = (index + offset + hits.value.length) % hits.value.length;

  activeKey.value = hitKey(hits.value[next]);

  nextTick(() => {
    document.querySelector('.global-search-row.active')?.scrollIntoView({ block: 'nearest' });
  });
}

function onEnter() {
  if (activeHit.value) {
    select(activeHit.value);
  }
}

onBeforeUnmount(() => window.clearTimeout(debounce));
</script>

<template>
  <div v-click-outside="close" class="global-search d-none d-md-block">
    <div class="global-search-box" :class="{ 'is-open': open }">
      <BDropdown
        variant="link"
        class="global-search-scope"
        toggle-class="global-search-scope-btn arrow-none"
        menu-class="global-search-scope-menu"
      >
        <template #button-content>
          <span class="text-truncate">{{ $t('search.scope_label') }} {{ scopeLabel }}</span>
          <i class="ri-arrow-down-s-line ms-1"></i>
        </template>

        <BLink
          href="javascript:void(0);"
          class="dropdown-item"
          :class="{ active: scope === 'all' }"
          @click="pickScope('all')"
        >
          <i class="ri-apps-2-line me-2"></i>{{ $t('search.all_objects') }}
        </BLink>

        <div class="dropdown-divider"></div>

        <BLink
          v-for="entry in scopes"
          :key="entry.key"
          href="javascript:void(0);"
          class="dropdown-item"
          :class="{ active: scope === entry.key }"
          @click="pickScope(entry.key)"
        >
          <i :class="entry.icon" class="me-2"></i>{{ entry.label }}
        </BLink>
      </BDropdown>

      <div class="global-search-field">
        <i class="ri-search-line global-search-icon" aria-hidden="true"></i>
        <input
          ref="field"
          v-model="term"
          type="search"
          class="form-control"
          :placeholder="placeholder"
          :aria-label="placeholder"
          autocomplete="off"
          @focus="onFocus"
          @keydown.down.prevent="move(1)"
          @keydown.up.prevent="move(-1)"
          @keydown.enter.prevent="onEnter"
          @keydown.esc="close"
        />
        <button
          v-if="term"
          type="button"
          class="global-search-clear"
          :aria-label="$t('search.clear_recent')"
          @click="clear"
        >
          <i class="ri-close-line"></i>
        </button>
      </div>
    </div>

    <div v-if="open" class="global-search-panel">
      <div class="global-search-results">
        <!-- Nothing typed yet: the five things this user looked for last. -->
        <template v-if="tooShort">
          <div v-if="recent.length > 0">
            <div class="global-search-caption">
              <span>{{ $t('search.recent') }}</span>
              <button type="button" class="btn btn-link btn-sm p-0" @click="clearRecent">
                {{ $t('search.clear_recent') }}
              </button>
            </div>
            <button
              v-for="entry in recent"
              :key="`${entry.scope}:${entry.term}`"
              type="button"
              class="global-search-row"
              @click="replay(entry)"
            >
              <i class="ri-history-line global-search-row-icon"></i>
              <span class="global-search-row-body">
                <span class="global-search-row-title">{{ entry.term }}</span>
                <span class="global-search-row-subtitle">
                  {{ scopes.find((item) => item.key === entry.scope)?.label ?? $t('search.all_objects') }}
                </span>
              </span>
            </button>
          </div>
          <p v-else class="global-search-empty">
            {{ $t('search.min_length', { count: minLength }) }}
          </p>
        </template>

        <template v-else>
          <p v-if="loading && hits.length === 0" class="global-search-empty">
            <span class="spinner-border spinner-border-sm me-2"></span>{{ $t('search.searching') }}
          </p>

          <p v-else-if="failed" class="global-search-empty text-danger">{{ $t('search.error') }}</p>

          <template v-else-if="hits.length > 0">
            <div v-for="group in groups" :key="group.key">
              <div class="global-search-caption">
                <span><i :class="group.icon" class="me-1"></i>{{ group.label }}</span>
                <span class="text-muted">{{ group.hits.length }}</span>
              </div>

              <button
                v-for="hit in group.hits"
                :key="`${group.key}:${hit.id}`"
                type="button"
                class="global-search-row"
                :class="{ active: activeKey === `${group.key}:${hit.id}` }"
                @mouseenter="activeKey = `${group.key}:${hit.id}`"
                @focus="activeKey = `${group.key}:${hit.id}`"
                @click="select({ ...hit, groupKey: group.key })"
              >
                <i :class="group.icon" class="global-search-row-icon"></i>
                <span class="global-search-row-body">
                  <span class="global-search-row-title">{{ hit.title }}</span>
                  <span v-if="hit.subtitle" class="global-search-row-subtitle">{{ hit.subtitle }}</span>
                </span>
                <span v-if="hit.badge" class="badge" :class="`bg-${hit.badge_color ?? 'secondary'}-subtle text-${hit.badge_color ?? 'secondary'}`">
                  {{ hit.badge }}
                </span>
              </button>
            </div>
          </template>

          <div v-else class="global-search-empty">
            <p class="mb-1">{{ $t('search.no_results', { term: term.trim() }) }}</p>
            <p class="mb-0 fs-12">{{ $t('search.no_results_hint') }}</p>
          </div>
        </template>
      </div>

      <!-- Preview of the row under the cursor, as on a Salesforce lookup. -->
      <div class="global-search-preview">
        <template v-if="activeHit">
          <div class="global-search-preview-head">
            <h6 class="mb-1">{{ activeHit.title }}</h6>
            <p v-if="activeHit.subtitle" class="text-muted fs-12 mb-2">{{ activeHit.subtitle }}</p>
            <span
              v-if="activeHit.badge"
              class="badge"
              :class="`bg-${activeHit.badge_color ?? 'secondary'}-subtle text-${activeHit.badge_color ?? 'secondary'}`"
            >
              {{ activeHit.badge }}
            </span>
          </div>

          <dl class="global-search-preview-list">
            <template v-for="row in activeHit.preview" :key="row.label">
              <dt>{{ row.label }}</dt>
              <dd>{{ row.value }}</dd>
            </template>
          </dl>

          <button type="button" class="btn btn-primary btn-sm w-100" @click="select(activeHit)">
            {{ $t('search.open') }}
          </button>
        </template>

        <p v-else class="global-search-empty">{{ $t('search.preview_hint') }}</p>
      </div>
    </div>
  </div>
</template>
