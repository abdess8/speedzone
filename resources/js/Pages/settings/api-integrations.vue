<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import { useClipboard } from '@/composables/useClipboard';
import ApiEndpoint from './Partials/ApiEndpoint.vue';
import ApiGuide from './Partials/ApiGuide.vue';
import { GROUPS, endpointsOf, methodVariant, sectionsOf } from './Partials/apiCatalog';
import { buildPostmanCollection, downloadPostmanCollection } from './Partials/postmanCollection';

const props = defineProps({
  apiBaseUrl: { type: String, default: '' },
  storeHeader: { type: String, default: 'X-Store-Id' },
  rateLimit: { type: Number, default: 60 },
  tokensUrl: { type: String, required: true },
  stores: { type: Array, default: () => [] },
  orderStatuses: { type: Array, default: () => [] },
  statusGroups: { type: Array, default: () => [] },
  examples: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { copy, copied } = useClipboard();

/** Pasted by the reader so the samples become copy-and-run. Never persisted. */
const token = ref('');
const search = ref('');
const language = ref('curl');
const activeId = ref('introduction');
const navOpen = ref(false);

const activeStoreId = computed(() => props.stores[0]?.id ?? null);

const tokenValue = computed(() => token.value.trim() || t('api_docs.console.token_placeholder'));

const authHeaderValue = computed(() => `Authorization: Bearer ${tokenValue.value}`);

const storeHeaderValue = computed(() => `${props.storeHeader}: ${activeStoreId.value ?? 12}`);

const matches = (endpoint) => {
  const needle = search.value.trim().toLowerCase();

  if (!needle) return true;

  return [endpoint.path, endpoint.method, t(`api_docs.endpoints.${endpoint.i18n}.title`)]
    .join(' ')
    .toLowerCase()
    .includes(needle);
};

const searching = computed(() => search.value.trim().length > 0);

const visibleEndpoints = computed(() =>
  GROUPS.map((group) => ({
    ...group,
    endpoints: endpointsOf(group.id).filter(matches),
  })).filter((group) => group.endpoints.length > 0),
);

const navGroups = computed(() =>
  GROUPS.map((group) => ({
    ...group,
    items: [
      ...(searching.value
        ? []
        : sectionsOf(group.id).map((section) => ({
            id: section.id,
            icon: section.icon,
            label: t(`api_docs.sections.${section.id}.title`),
          }))),
      ...endpointsOf(group.id)
        .filter(matches)
        .map((endpoint) => ({
          id: endpoint.id,
          method: endpoint.method,
          label: t(`api_docs.endpoints.${endpoint.i18n}.title`),
        })),
    ],
  })).filter((group) => group.items.length > 0),
);

const downloaded = ref(false);
let downloadedTimer = null;

const downloadCollection = () => {
  downloadPostmanCollection(
    buildPostmanCollection({
      baseUrl: props.apiBaseUrl,
      token: token.value.trim(),
      storeId: activeStoreId.value,
      examples: props.examples,
      rateLimit: props.rateLimit,
      t,
    }),
  );

  downloaded.value = true;
  clearTimeout(downloadedTimer);
  downloadedTimer = setTimeout(() => {
    downloaded.value = false;
  }, 2500);
};

const goTo = (id) => {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  activeId.value = id;
  navOpen.value = false;
};

// Highlights the entry whose section sits nearest the top of the viewport. The
// observer alone is not enough: several short sections can be on screen at
// once, so the visible one closest to the header wins.
let observer = null;
const onScreen = new Set();

const syncActiveId = () => {
  const [first] = [...onScreen]
    .map((id) => document.getElementById(id))
    // Searching swaps the whole content tree, so an id can outlive its node.
    .filter(Boolean)
    .sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top);

  if (first) {
    activeId.value = first.id;
  }
};

const observeSections = () => {
  if (!observer) {
    return;
  }

  observer.disconnect();
  onScreen.clear();

  document
    .querySelectorAll('.api-guide section[id], section.api-endpoint[id]')
    .forEach((node) => observer.observe(node));
};

onMounted(() => {
  if (typeof IntersectionObserver === 'undefined') {
    return;
  }

  observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          onScreen.add(entry.target.id);
        } else {
          onScreen.delete(entry.target.id);
        }
      });

      syncActiveId();
    },
    { rootMargin: '-88px 0px -70% 0px', threshold: 0 },
  );

  observeSections();
});

// Filtering rebuilds the endpoint list, so the observer has to be pointed at
// the new nodes once Vue has patched the DOM.
watch(visibleEndpoints, () => nextTick(observeSections));

onBeforeUnmount(() => {
  observer?.disconnect();
  clearTimeout(downloadedTimer);
});
</script>

<template>
  <Layout>
    <Head :title="$t('api_docs.title')" />
    <PageHeader :title="$t('api_docs.title')" :pageTitle="$t('api_docs.page_title')" />

    <BCard no-body class="api-hero">
      <BCardBody>
        <BRow class="g-4 align-items-start">
          <BCol lg="5">
            <div class="d-flex align-items-center gap-3 mb-2">
              <div class="avatar-sm flex-shrink-0">
                <div class="avatar-title bg-primary-subtle text-primary rounded fs-20">
                  <i class="ri-plug-2-line"></i>
                </div>
              </div>
              <div>
                <h5 class="mb-1">{{ $t('api_docs.console.title') }}</h5>
                <p class="text-muted mb-0 small">
                  {{ $t('api_docs.console.description', { header: 'Authorization' }) }}
                </p>
              </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-2">
              <a :href="tokensUrl" class="btn btn-primary btn-sm">
                <i class="ri-key-2-line align-bottom me-1"></i>
                {{ $t('api_docs.actions.manage_tokens') }}
              </a>
              <button type="button" class="btn btn-outline-primary btn-sm" @click="downloadCollection">
                <i :class="downloaded ? 'ri-check-line' : 'ri-download-2-line'" class="align-bottom me-1"></i>
                {{ downloaded ? $t('api_docs.actions.downloaded') : $t('api_docs.actions.download_postman') }}
              </button>
            </div>

            <p class="text-muted small mt-2 mb-0">{{ $t('api_docs.postman.hint') }}</p>

            <div class="alert alert-warning d-flex gap-2 mt-3 mb-0 py-2 px-3 small" role="alert">
              <i class="ri-shield-keyhole-line mt-1"></i>
              <span>
                {{ $t('api_docs.console.token_notice') }}
                <template v-if="token.trim()"> {{ $t('api_docs.postman.token_embedded') }}</template>
              </span>
            </div>
          </BCol>

          <BCol lg="7">
            <div class="api-credential">
              <label class="form-label">{{ $t('api_docs.console.base_url') }}</label>
              <div class="input-group input-group-sm">
                <input type="text" class="form-control font-monospace" :value="`${apiBaseUrl}/api`" readonly />
                <button class="btn btn-outline-secondary" type="button" @click="copy(`${apiBaseUrl}/api`, 'base')">
                  <i :class="copied === 'base' ? 'ri-check-line' : 'ri-file-copy-line'"></i>
                </button>
              </div>
            </div>

            <div class="api-credential">
              <label class="form-label">{{ $t('api_docs.console.auth_header') }}</label>
              <div class="input-group input-group-sm">
                <input
                  v-model="token"
                  type="text"
                  class="form-control font-monospace"
                  :placeholder="$t('api_docs.console.token_placeholder')"
                  autocomplete="off"
                  spellcheck="false"
                />
                <button class="btn btn-outline-secondary" type="button" @click="copy(authHeaderValue, 'auth')">
                  <i :class="copied === 'auth' ? 'ri-check-line' : 'ri-file-copy-line'"></i>
                </button>
              </div>
            </div>

            <div class="api-credential mb-0">
              <label class="form-label">
                {{ $t('api_docs.console.store_header') }}
                <span class="text-muted fw-normal">— {{ $t('api_docs.console.store_hint') }}</span>
              </label>
              <div v-if="stores.length" class="input-group input-group-sm">
                <input type="text" class="form-control font-monospace" :value="storeHeaderValue" readonly />
                <button class="btn btn-outline-secondary" type="button" @click="copy(storeHeaderValue, 'store')">
                  <i :class="copied === 'store' ? 'ri-check-line' : 'ri-file-copy-line'"></i>
                </button>
              </div>
              <p v-else class="text-muted small mb-0">{{ $t('api_docs.console.no_store') }}</p>
            </div>
          </BCol>
        </BRow>
      </BCardBody>
    </BCard>

    <BRow class="g-4">
      <BCol lg="3">
        <button type="button" class="btn btn-outline-secondary btn-sm w-100 d-lg-none mb-2" @click="navOpen = !navOpen">
          <i class="ri-list-unordered align-bottom me-1"></i>
          {{ $t('api_docs.actions.toggle_nav') }}
        </button>

        <nav class="api-nav" :class="{ 'is-open': navOpen }">
          <div class="api-nav-search">
            <div class="position-relative">
              <input
                v-model="search"
                type="search"
                class="form-control form-control-sm ps-4"
                :placeholder="$t('api_docs.search.placeholder')"
              />
              <i class="ri-search-line api-nav-search-icon"></i>
            </div>
          </div>

          <div v-for="group in navGroups" :key="group.id" class="api-nav-group">
            <p class="api-nav-caption">
              <i :class="group.icon" class="align-bottom me-1"></i>{{ $t(group.labelKey) }}
            </p>
            <ul class="api-nav-list">
              <li v-for="item in group.items" :key="item.id">
                <button
                  type="button"
                  class="api-nav-link"
                  :class="{ active: activeId === item.id }"
                  @click="goTo(item.id)"
                >
                  <span
                    v-if="item.method"
                    class="api-nav-method"
                    :class="`text-${methodVariant(item.method)}`"
                  >{{ item.method }}</span>
                  <span class="text-truncate">{{ item.label }}</span>
                </button>
              </li>
            </ul>
          </div>

          <p v-if="!navGroups.length" class="text-muted small mb-0 px-2">
            {{ $t('api_docs.search.empty', { query: search }) }}
          </p>
        </nav>
      </BCol>

      <BCol lg="9">
        <BCard no-body>
          <BCardBody class="api-content">
            <ApiGuide
              v-if="!searching"
              :base-url="apiBaseUrl"
              :token="token"
              :store-header="storeHeader"
              :rate-limit="rateLimit"
              :tokens-url="tokensUrl"
              :stores="stores"
              :order-statuses="orderStatuses"
              :status-groups="statusGroups"
            />

            <template v-for="group in visibleEndpoints" :key="group.id">
              <h2 class="api-group-title">
                <i :class="group.icon" class="align-bottom me-2"></i>{{ $t(group.labelKey) }}
              </h2>

              <ApiEndpoint
                v-for="endpoint in group.endpoints"
                :key="endpoint.id"
                :endpoint="endpoint"
                :base-url="apiBaseUrl"
                :token="token"
                :store-id="activeStoreId"
                :language="language"
                @update:language="language = $event"
              />
            </template>

            <p v-if="searching && !visibleEndpoints.length" class="text-muted mb-0">
              {{ $t('api_docs.search.empty', { query: search }) }}
            </p>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped lang="scss">
.api-hero {
  margin-bottom: 1.5rem;
}

.api-credential + .api-credential {
  margin-top: 0.75rem;
}

.api-credential .form-label {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--vz-secondary-color);
  margin-bottom: 0.25rem;
}

.api-nav {
  position: sticky;
  top: 5.5rem;
  max-height: calc(100vh - 7rem);
  overflow-y: auto;
  padding-right: 0.25rem;
}

.api-nav-search {
  margin-bottom: 1rem;
}

.api-nav-search-icon {
  position: absolute;
  top: 50%;
  left: 0.5rem;
  transform: translateY(-50%);
  font-size: 0.875rem;
  color: var(--vz-secondary-color);
  pointer-events: none;
}

.api-nav-group + .api-nav-group {
  margin-top: 1.25rem;
}

.api-nav-caption {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--vz-secondary-color);
  margin-bottom: 0.35rem;
  padding-left: 0.5rem;
}

.api-nav-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.api-nav-link {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  width: 100%;
  padding: 0.3rem 0.5rem;
  font-size: 0.8125rem;
  text-align: start;
  color: var(--vz-body-color);
  background: transparent;
  border: 0;
  border-radius: 0.3rem;
  transition: color 0.15s ease, background-color 0.15s ease;

  &:hover {
    background: var(--vz-light);
  }

  &.active {
    color: var(--vz-primary);
    background: var(--vz-primary-bg-subtle);
    font-weight: 500;
  }
}

.api-nav-method {
  flex: 0 0 2.6rem;
  font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
  font-size: 0.5625rem;
  font-weight: 700;
  letter-spacing: 0.03em;
}

.api-content {
  padding-top: 0;
}

.api-group-title {
  margin-top: 2.5rem;
  padding-top: 1.5rem;
  font-size: 1.375rem;
  font-weight: 600;
  border-top: 2px solid var(--vz-border-color);
  scroll-margin-top: 5.5rem;
}

@media (max-width: 991.98px) {
  .api-nav {
    position: static;
    max-height: none;
    display: none;

    &.is-open {
      display: block;
    }
  }
}
</style>

<style lang="scss">
// Emitted by `renderInlineCode` into `v-html`, which scoped styles cannot reach.
.api-inline-code {
  padding: 0.1rem 0.3rem;
  font-size: 0.8125em;
  color: var(--vz-primary);
  background: var(--vz-primary-bg-subtle);
  border-radius: 0.25rem;
  word-break: break-word;
}
</style>
