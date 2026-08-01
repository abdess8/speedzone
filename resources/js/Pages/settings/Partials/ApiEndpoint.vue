<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ApiCodeBlock from './ApiCodeBlock.vue';
import ApiParamTable from './ApiParamTable.vue';
import { renderInlineCode } from './apiText';
import { LANGUAGES, buildSamples, methodVariant } from './apiCatalog';

const props = defineProps({
  endpoint: { type: Object, required: true },
  baseUrl: { type: String, required: true },
  token: { type: String, required: true },
  storeId: { type: [Number, String], default: null },
  language: { type: String, default: 'curl' },
});

const emit = defineEmits(['update:language']);

const { t } = useI18n();

const activeResponse = ref(props.endpoint.responses?.[0]?.status ?? 200);

const samples = computed(() =>
  buildSamples(props.endpoint, {
    baseUrl: props.baseUrl,
    token: props.token || t('api_docs.console.token_placeholder'),
    storeId: props.storeId,
  }),
);

const activeLanguage = computed(() => LANGUAGES.find((item) => item.id === props.language) ?? LANGUAGES[0]);

const selectedResponse = computed(
  () => props.endpoint.responses?.find((response) => response.status === activeResponse.value) ?? null,
);

const responseBody = computed(() => {
  const response = selectedResponse.value;

  if (!response) return '';
  if (response.raw) return response.raw;
  if (response.sample === null) return '';

  return JSON.stringify(response.sample, null, 2);
});

const requestBody = computed(() =>
  props.endpoint.request ? JSON.stringify(props.endpoint.request, null, 2) : '',
);

const headers = computed(() => {
  const list = [{ name: 'Authorization', type: 'string', required: true, desc: 'headers.authorization' }];

  if (!props.endpoint.binary) {
    list.push({ name: 'Accept', type: 'string', required: true, desc: 'headers.accept' });
  }

  if (props.endpoint.request) {
    list.push({ name: 'Content-Type', type: 'string', required: true, desc: 'headers.content_type' });
  }

  list.push({ name: 'X-Store-Id', type: 'integer', desc: 'headers.store' });

  return list;
});

const statusVariant = (status) => {
  if (status < 300) return 'success';
  if (status < 400) return 'info';
  if (status < 500) return 'danger';

  return 'warning';
};
</script>

<template>
  <section :id="endpoint.id" class="api-endpoint">
    <div class="api-endpoint-head">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="badge api-method" :class="`bg-${methodVariant(endpoint.method)}-subtle text-${methodVariant(endpoint.method)}`">
          {{ endpoint.method }}
        </span>
        <code class="api-endpoint-path">{{ endpoint.path }}</code>
        <span v-if="endpoint.permission" class="badge bg-light text-body fw-normal">
          <i class="ri-shield-keyhole-line align-bottom me-1"></i>
          {{ endpoint.permission }}
        </span>
      </div>

      <h4 class="api-endpoint-title">{{ $t(`api_docs.endpoints.${endpoint.i18n}.title`) }}</h4>
      <p class="api-endpoint-lead" v-html="renderInlineCode($t(`api_docs.endpoints.${endpoint.i18n}.description`))"></p>
    </div>

    <BRow class="g-4">
      <BCol xl="7">
        <ApiParamTable :title="$t('api_docs.labels.headers')" :params="headers" />
        <ApiParamTable
          v-if="endpoint.pathParams?.length"
          :title="$t('api_docs.labels.path_params')"
          :params="endpoint.pathParams"
        />
        <ApiParamTable
          v-if="endpoint.query?.length"
          :title="$t('api_docs.labels.query_params')"
          :params="endpoint.query"
        />
        <ApiParamTable
          v-if="endpoint.body?.length"
          :title="$t('api_docs.labels.body_params')"
          :params="endpoint.body"
        />

        <div v-if="endpoint.notes?.length" class="api-notes">
          <h6 class="api-notes-title">
            <i class="ri-lightbulb-line align-bottom me-1"></i>{{ $t('api_docs.labels.notes') }}
          </h6>
          <ul class="api-notes-list">
            <li v-for="note in endpoint.notes" :key="note" v-html="renderInlineCode($t(`api_docs.notes.${note}`))"></li>
          </ul>
        </div>
      </BCol>

      <BCol xl="5">
        <div class="api-pane">
          <div class="api-pane-tabs">
            <button
              v-for="item in LANGUAGES"
              :key="item.id"
              type="button"
              class="api-pane-tab"
              :class="{ active: item.id === activeLanguage.id }"
              @click="emit('update:language', item.id)"
            >
              {{ item.label }}
            </button>
          </div>

          <ApiCodeBlock
            :code="samples[activeLanguage.id]"
            :language="activeLanguage.highlight"
            :caption="$t('api_docs.labels.request_example')"
          />

          <ApiCodeBlock
            v-if="requestBody"
            class="mt-3"
            :code="requestBody"
            language="json"
            caption="JSON"
          />

          <div v-if="endpoint.responses?.length" class="mt-3">
            <div class="api-pane-tabs api-pane-tabs-status">
              <button
                v-for="response in endpoint.responses"
                :key="response.status"
                type="button"
                class="api-pane-tab"
                :class="{ active: response.status === activeResponse }"
                @click="activeResponse = response.status"
              >
                <span class="api-status-dot" :class="`bg-${statusVariant(response.status)}`"></span>
                {{ response.status }}
              </button>
            </div>

            <ApiCodeBlock
              v-if="responseBody"
              :code="responseBody"
              :language="selectedResponse?.raw ? 'bash' : 'json'"
              :caption="selectedResponse?.contentType ?? $t('api_docs.labels.response_example')"
            />
            <div v-else class="api-empty-response">
              <i class="ri-checkbox-circle-line align-bottom me-1"></i>
              {{ activeResponse }} — {{ $t('api_docs.sections.errors.codes.c204') }}
            </div>
          </div>
        </div>
      </BCol>
    </BRow>
  </section>
</template>

<style scoped>
.api-endpoint {
  padding-top: 1.5rem;
  scroll-margin-top: 5.5rem;
}

.api-endpoint + .api-endpoint {
  margin-top: 2rem;
  border-top: 1px solid var(--vz-border-color);
}

.api-endpoint-head {
  margin-bottom: 1.25rem;
}

.api-method {
  font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
  font-size: 0.6875rem;
  letter-spacing: 0.04em;
}

.api-endpoint-path {
  font-size: 0.875rem;
  color: var(--vz-body-color);
  background: var(--vz-light);
  padding: 0.15rem 0.5rem;
  border-radius: 0.3rem;
}

.api-endpoint-title {
  font-size: 1.0625rem;
  font-weight: 600;
  margin-bottom: 0.35rem;
}

.api-endpoint-lead {
  color: var(--vz-secondary-color);
  margin-bottom: 0;
}

.api-notes {
  margin-top: 1.25rem;
  padding: 0.85rem 1rem;
  background: var(--vz-warning-bg-subtle);
  border: 1px solid var(--vz-warning-border-subtle);
  border-radius: 0.5rem;
}

.api-notes-title {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 0.4rem;
}

.api-notes-list {
  margin: 0;
  padding-left: 1.1rem;
  font-size: 0.8125rem;
}

.api-notes-list li + li {
  margin-top: 0.35rem;
}

.api-pane {
  position: sticky;
  top: 5.5rem;
}

.api-pane-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  margin-bottom: 0.5rem;
}

.api-pane-tab {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.6rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--vz-secondary-color);
  background: transparent;
  border: 1px solid transparent;
  border-radius: 0.3rem;
  transition: color 0.15s ease, background-color 0.15s ease;
}

.api-pane-tab:hover {
  color: var(--vz-body-color);
  background: var(--vz-light);
}

.api-pane-tab.active {
  color: var(--vz-primary);
  background: var(--vz-primary-bg-subtle);
  border-color: var(--vz-primary-border-subtle);
}

.api-status-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 50%;
}

.api-empty-response {
  padding: 0.75rem 1rem;
  font-size: 0.8125rem;
  color: var(--vz-secondary-color);
  background: var(--vz-light);
  border-radius: 0.5rem;
}

@media (max-width: 1199.98px) {
  .api-pane {
    position: static;
  }
}
</style>
