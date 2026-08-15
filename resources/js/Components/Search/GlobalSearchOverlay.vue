<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useGlobalSearch } from '@/composables/useGlobalSearch';

/**
 * Global search on a touch screen: a trigger in the topbar that opens a
 * full-screen search view, the way the Facebook app does it.
 *
 * The desktop bar cannot simply be narrowed. Its dropdown panel would leave a
 * phone keyboard covering most of the results, its scope picker would eat the
 * field it belongs to, and the preview column has no room at all. So the whole
 * screen becomes the search: field on top, object filters as a scrollable row of
 * chips under it, results filling the rest at thumb-sized height. Tapping a
 * result opens the record directly, which is what the preview column was for.
 */
const {
  term,
  scope,
  scopes,
  groups,
  minLength,
  open,
  loading,
  failed,
  recent,
  hits,
  placeholder,
  tooShort,
  scopeLabelFor,
  rememberSearch,
  forgetSearch,
  clearRecent,
  runSearch,
  activate,
  close,
  reset,
  clear,
  pickScope,
  replay,
  select,
} = useGlobalSearch();

const page = usePage();

const field = ref(null);
const results = ref(null);

/**
 * Locking the body keeps the page behind the overlay from scrolling under the
 * finger on iOS Safari. It follows the overlay rather than the two entry points,
 * so a search closed by opening a result also releases the page.
 */
function setBodyLock(locked) {
  document.body.style.overflow = locked ? 'hidden' : '';
}

watch(open, setBodyLock);

async function reveal() {
  activate();

  // The field only exists once the overlay has rendered, and it has to be
  // focused in the same task as the tap or iOS will not raise the keyboard.
  await nextTick();
  field.value?.focus();
}

function dismiss() {
  close();
  reset();
}

/**
 * Watching the page rather than Inertia's visit events: a poll or a partial
 * reload of the page underneath must not throw away what is being typed, and
 * only a URL that actually changed means the search is over. The browser's back
 * button lands here too.
 */
watch(() => page.url, dismiss);

function openHit(hit) {
  select(hit);
  // A result on the page already open changes no URL, so nothing above would
  // fire and the view would come back holding a spent search.
  dismiss();
}

function onClear() {
  clear();
  field.value?.focus();
}

function chooseScope(key) {
  pickScope(key);
  results.value?.scrollTo({ top: 0 });
}

function onRecent(entry) {
  replay(entry);
  field.value?.focus();
}

/**
 * The results are already live, so the search key has nothing left to fetch: it
 * banks the term and drops the keyboard, handing the screen to the results.
 */
function submit() {
  rememberSearch();
  field.value?.blur();
}

function onKeydown(event) {
  if (event.key === 'Escape' && open.value) {
    dismiss();
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  setBodyLock(false);
});
</script>

<template>
  <button
    type="button"
    class="global-search-trigger"
    :aria-label="$t('search.open_search')"
    @click="reveal"
  >
    <i class="ri-search-line global-search-trigger-icon" aria-hidden="true"></i>
    <span class="global-search-trigger-label">{{ $t('search.placeholder') }}</span>
  </button>

  <Teleport to="body">
    <Transition name="search-view">
      <div
        v-if="open"
        class="search-view"
        role="dialog"
        aria-modal="true"
        :aria-label="$t('search.open_search')"
      >
        <div class="search-view-bar">
          <button
            type="button"
            class="search-view-back"
            :aria-label="$t('search.close')"
            @click="dismiss"
          >
            <i class="ri-arrow-left-line"></i>
          </button>

          <div class="search-view-field">
            <i class="ri-search-line search-view-field-icon" aria-hidden="true"></i>
            <input
              ref="field"
              v-model="term"
              type="text"
              class="search-view-input"
              :placeholder="placeholder"
              :aria-label="placeholder"
              autocomplete="off"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
              enterkeyhint="search"
              @keydown.enter.prevent="submit"
            />
            <button
              v-if="term"
              type="button"
              class="search-view-field-clear"
              :aria-label="$t('search.clear_input')"
              @click="onClear"
            >
              <i class="ri-close-circle-fill"></i>
            </button>
          </div>
        </div>

        <!-- Object filters as chips: a dropdown would hide the one thing that
             tells the user why a list is short. The row is here from the first
             frame, before the objects have loaded, so the results do not jump
             down a line under a thumb already on its way to one. -->
        <div class="search-view-scopes" role="group" :aria-label="$t('search.filter_by_object')">
          <button
            type="button"
            class="search-view-chip"
            :class="{ active: scope === 'all' }"
            :aria-pressed="scope === 'all'"
            @click="chooseScope('all')"
          >
            <i class="ri-apps-2-line"></i>{{ $t('search.all_objects') }}
          </button>

          <button
            v-for="entry in scopes"
            :key="entry.key"
            type="button"
            class="search-view-chip"
            :class="{ active: scope === entry.key }"
            :aria-pressed="scope === entry.key"
            @click="chooseScope(entry.key)"
          >
            <i :class="entry.icon"></i>{{ entry.label }}
          </button>
        </div>

        <div ref="results" class="search-view-body">
          <div v-if="failed" class="search-view-state">
            <span class="search-view-state-icon text-danger"><i class="ri-wifi-off-line"></i></span>
            <p class="search-view-state-text">{{ $t('search.error') }}</p>
            <button type="button" class="btn btn-soft-primary btn-sm" @click="runSearch">
              {{ $t('search.retry') }}
            </button>
          </div>

          <!-- Nothing typed yet: the five things this user looked for last. -->
          <template v-else-if="tooShort">
            <template v-if="recent.length > 0">
              <div class="search-view-caption">
                <span>{{ $t('search.recent') }}</span>
                <button type="button" class="btn btn-link btn-sm p-0" @click="clearRecent">
                  {{ $t('search.clear_recent') }}
                </button>
              </div>

              <div
                v-for="entry in recent"
                :key="`${entry.scope}:${entry.term}`"
                class="search-view-row"
              >
                <button type="button" class="search-view-row-main" @click="onRecent(entry)">
                  <span class="search-view-row-figure"><i class="ri-history-line"></i></span>
                  <span class="search-view-row-body">
                    <span class="search-view-row-title">{{ entry.term }}</span>
                    <span class="search-view-row-subtitle">{{ scopeLabelFor(entry.scope) }}</span>
                  </span>
                </button>
                <button
                  type="button"
                  class="search-view-row-remove"
                  :aria-label="$t('search.remove_recent')"
                  @click="forgetSearch(entry)"
                >
                  <i class="ri-close-line"></i>
                </button>
              </div>
            </template>

            <div v-else class="search-view-state">
              <span class="search-view-state-icon"><i class="ri-search-2-line"></i></span>
              <p class="search-view-state-title">{{ $t('search.empty_title') }}</p>
              <p class="search-view-state-text">{{ $t('search.min_length', { count: minLength }) }}</p>
            </div>
          </template>

          <template v-else>
            <!-- Placeholder rows rather than a spinner: the list keeps its shape,
                 so the results do not shove the finger's target as they land. -->
            <div v-if="loading && hits.length === 0" aria-hidden="true">
              <div v-for="n in 4" :key="n" class="search-view-skeleton placeholder-glow">
                <span class="search-view-skeleton-figure placeholder"></span>
                <span class="d-block flex-grow-1">
                  <span class="placeholder col-7 d-block mb-1"></span>
                  <span class="placeholder col-4 d-block"></span>
                </span>
              </div>
            </div>

            <template v-else-if="hits.length > 0">
              <div v-for="group in groups" :key="group.key">
                <div class="search-view-caption">
                  <span><i :class="group.icon" class="me-1"></i>{{ group.label }}</span>
                  <span class="text-muted">{{ group.hits.length }}</span>
                </div>

                <div v-for="hit in group.hits" :key="`${group.key}:${hit.id}`" class="search-view-row">
                  <button
                    type="button"
                    class="search-view-row-main"
                    @click="openHit({ ...hit, groupKey: group.key })"
                  >
                    <span class="search-view-row-figure"><i :class="group.icon"></i></span>
                    <span class="search-view-row-body">
                      <span class="search-view-row-title">{{ hit.title }}</span>
                      <span v-if="hit.subtitle" class="search-view-row-subtitle">{{ hit.subtitle }}</span>
                    </span>
                    <span
                      v-if="hit.badge"
                      class="badge flex-shrink-0"
                      :class="`bg-${hit.badge_color ?? 'secondary'}-subtle text-${hit.badge_color ?? 'secondary'}`"
                    >
                      {{ hit.badge }}
                    </span>
                    <i class="ri-arrow-right-s-line search-view-row-chevron"></i>
                  </button>
                </div>
              </div>
            </template>

            <div v-else class="search-view-state">
              <span class="search-view-state-icon"><i class="ri-file-search-line"></i></span>
              <p class="search-view-state-title">{{ $t('search.no_results', { term: term.trim() }) }}</p>
              <p class="search-view-state-text">{{ $t('search.no_results_hint') }}</p>
              <button
                v-if="scope !== 'all'"
                type="button"
                class="btn btn-soft-primary btn-sm"
                @click="chooseScope('all')"
              >
                {{ $t('search.search_all') }}
              </button>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
