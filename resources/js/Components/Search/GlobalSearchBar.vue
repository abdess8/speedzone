<script setup>
import { nextTick, ref } from 'vue';
import { useGlobalSearch } from '@/composables/useGlobalSearch';

/**
 * Global search as it appears on a pointer-driven screen: a field in the topbar,
 * a scope segment on its left and a two-column panel underneath — results on the
 * left, a preview of the row under the cursor on the right.
 *
 * The preview arrives with the results rather than being fetched on hover: it
 * has to be on screen the instant a row lights up, and one request per row would
 * trail the cursor down the list.
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
  activeKey,
  recent,
  hits,
  activeHit,
  scopeLabel,
  placeholder,
  tooShort,
  scopeLabelFor,
  clearRecent,
  activate,
  close,
  clear,
  pickScope,
  replay,
  select,
  move,
} = useGlobalSearch();

const field = ref(null);

function chooseScope(key) {
  pickScope(key);
  field.value?.focus();
}

function onClear() {
  clear();
  field.value?.focus();
}

function walk(offset) {
  move(offset);

  nextTick(() => {
    document.querySelector('.global-search-row.active')?.scrollIntoView({ block: 'nearest' });
  });
}

function onEnter() {
  if (activeHit.value) {
    select(activeHit.value);
  }
}
</script>

<template>
  <div v-click-outside="close" class="global-search">
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
          @click="chooseScope('all')"
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
          @click="chooseScope(entry.key)"
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
          @focus="activate"
          @keydown.down.prevent="walk(1)"
          @keydown.up.prevent="walk(-1)"
          @keydown.enter.prevent="onEnter"
          @keydown.esc="close"
        />
        <button
          v-if="term"
          type="button"
          class="global-search-clear"
          :aria-label="$t('search.clear_input')"
          @click="onClear"
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
                <span class="global-search-row-subtitle">{{ scopeLabelFor(entry.scope) }}</span>
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
