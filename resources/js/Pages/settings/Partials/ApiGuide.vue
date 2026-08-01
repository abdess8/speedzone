<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import ApiCodeBlock from './ApiCodeBlock.vue';
import { renderInlineCode } from './apiText';

const props = defineProps({
  baseUrl: { type: String, required: true },
  token: { type: String, default: '' },
  storeHeader: { type: String, default: 'X-Store-Id' },
  rateLimit: { type: Number, default: 60 },
  tokensUrl: { type: String, required: true },
  stores: { type: Array, default: () => [] },
  orderStatuses: { type: Array, default: () => [] },
  statusGroups: { type: Array, default: () => [] },
});

const { t } = useI18n();

const tokenValue = computed(() => props.token || t('api_docs.console.token_placeholder'));

const ERROR_CODES = ['c200', 'c201', 'c204', 'c401', 'c403', 'c404', 'c422', 'c429', 'c500'];

const codeVariant = (code) => {
  const status = Number(code.slice(1));

  if (status < 300) return 'success';
  if (status < 500) return 'danger';

  return 'warning';
};

const CONVENTIONS = ['json', 'wrapper', 'dates', 'amounts', 'ids'];

const convention = (key) =>
  renderInlineCode(
    t(`api_docs.sections.introduction.conventions.${key}`, {
      example: '`2026-08-01T09:24:11+00:00`',
    }),
  );

/**
 * The "create a token" step embeds a link. The sentence is escaped first, so
 * the anchor is spliced in afterwards through a sentinel the translations
 * never contain.
 */
const openTokensStep = computed(() => {
  const sentinel = '@@TOKENS_LINK@@';
  const sentence = renderInlineCode(
    t('api_docs.sections.authentication.create_steps.open', { link: sentinel }),
  );
  const anchor = `<a href="${props.tokensUrl}" class="link-primary fw-medium">${t('api_docs.actions.manage_tokens')}</a>`;

  return sentence.replace(sentinel, anchor);
});

const authExample = computed(
  () => `Authorization: Bearer ${tokenValue.value}
Accept: application/json
Content-Type: application/json`,
);

const storeExample = computed(() => {
  const storeId = props.stores[0]?.id ?? 12;

  return `curl --request GET \\
  --url '${props.baseUrl}/api/orders' \\
  --header 'Authorization: Bearer ${tokenValue.value}' \\
  --header 'Accept: application/json' \\
  --header '${props.storeHeader}: ${storeId}'`;
});

const validationExample = JSON.stringify(
  {
    message: 'The customer phone field is required. (and 1 more error)',
    errors: {
      customer_phone: ['The customer phone field is required.'],
      sector_id: ['The selected sector does not belong to the chosen city or is inactive.'],
    },
  },
  null,
  2,
);

const groupLabel = (group) => t(`api_docs.sections.statuses.group_${group}`);
</script>

<template>
  <div class="api-guide">
    <!-- Introduction -->
    <section id="introduction" class="api-section">
      <h3 class="api-section-title">{{ $t('api_docs.sections.introduction.title') }}</h3>
      <p class="api-section-lead">{{ $t('api_docs.sections.introduction.lead') }}</p>

      <BRow class="g-4">
        <BCol xl="7">
          <h6 class="api-subtitle">{{ $t('api_docs.sections.introduction.conventions_title') }}</h6>
          <ul class="api-list">
            <li v-for="key in CONVENTIONS" :key="key" v-html="convention(key)"></li>
          </ul>

          <div class="alert alert-warning d-flex gap-2 mt-3 mb-0" role="alert">
            <i class="ri-alert-line fs-16 mt-1"></i>
            <div>
              <strong class="d-block mb-1">{{ $t('api_docs.sections.introduction.accept_title') }}</strong>
              <span v-html="renderInlineCode($t('api_docs.sections.introduction.accept_body'))"></span>
            </div>
          </div>
        </BCol>

        <BCol xl="5">
          <ApiCodeBlock
            :code="`${baseUrl}/api`"
            language="bash"
            :caption="$t('api_docs.console.base_url')"
          />
        </BCol>
      </BRow>
    </section>

    <!-- Authentication -->
    <section id="authentication" class="api-section">
      <h3 class="api-section-title">{{ $t('api_docs.sections.authentication.title') }}</h3>
      <p class="api-section-lead">{{ $t('api_docs.sections.authentication.lead') }}</p>

      <BRow class="g-4">
        <BCol xl="7">
          <h6 class="api-subtitle">{{ $t('api_docs.sections.authentication.create_title') }}</h6>
          <ol class="api-list">
            <li v-html="openTokensStep"></li>
            <li v-html="renderInlineCode($t('api_docs.sections.authentication.create_steps.name'))"></li>
            <li v-html="renderInlineCode($t('api_docs.sections.authentication.create_steps.abilities'))"></li>
            <li v-html="renderInlineCode($t('api_docs.sections.authentication.create_steps.copy'))"></li>
          </ol>

          <h6 class="api-subtitle mt-4">{{ $t('api_docs.sections.authentication.abilities_title') }}</h6>
          <p class="mb-0" v-html="renderInlineCode($t('api_docs.sections.authentication.abilities_body'))"></p>

          <h6 class="api-subtitle mt-4">{{ $t('api_docs.sections.authentication.revoke_title') }}</h6>
          <p class="mb-0">{{ $t('api_docs.sections.authentication.revoke_body') }}</p>
        </BCol>

        <BCol xl="5">
          <h6 class="api-subtitle">{{ $t('api_docs.sections.authentication.usage_title') }}</h6>
          <p class="text-muted small" v-html="renderInlineCode($t('api_docs.sections.authentication.usage_body'))"></p>
          <ApiCodeBlock :code="authExample" language="bash" :caption="$t('api_docs.labels.headers')" />
        </BCol>
      </BRow>
    </section>

    <!-- Multi-store -->
    <section id="stores" class="api-section">
      <h3 class="api-section-title">{{ $t('api_docs.sections.stores.title') }}</h3>
      <p class="api-section-lead">{{ $t('api_docs.sections.stores.lead') }}</p>

      <BRow class="g-4">
        <BCol xl="7">
          <p v-html="renderInlineCode($t('api_docs.sections.stores.header_body'))"></p>

          <div v-if="stores.length" class="api-store-table">
            <h6 class="api-subtitle">{{ $t('api_docs.sections.stores.your_stores') }}</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">{{ $t('api_docs.sections.stores.store_id') }}</th>
                    <th scope="col">{{ $t('api_docs.sections.stores.store_name') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="store in stores" :key="store.id">
                    <td><code class="api-inline-code">{{ store.id }}</code></td>
                    <td>
                      {{ store.name }}
                      <span v-if="store.is_default" class="badge bg-primary-subtle text-primary ms-1">
                        {{ $t('api_docs.sections.stores.store_default') }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <h6 class="api-subtitle mt-4">{{ $t('api_docs.sections.stores.team_title') }}</h6>
          <p class="mb-0">{{ $t('api_docs.sections.stores.team_body') }}</p>
        </BCol>

        <BCol xl="5">
          <ApiCodeBlock :code="storeExample" language="bash" :caption="storeHeader" />
        </BCol>
      </BRow>
    </section>

    <!-- Errors -->
    <section id="errors" class="api-section">
      <h3 class="api-section-title">{{ $t('api_docs.sections.errors.title') }}</h3>
      <p class="api-section-lead" v-html="renderInlineCode($t('api_docs.sections.errors.lead'))"></p>

      <BRow class="g-4">
        <BCol xl="7">
          <h6 class="api-subtitle">{{ $t('api_docs.sections.errors.codes_title') }}</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col">{{ $t('api_docs.labels.status_code') }}</th>
                  <th scope="col">{{ $t('api_docs.labels.meaning') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="code in ERROR_CODES" :key="code">
                  <td>
                    <span class="badge" :class="`bg-${codeVariant(code)}-subtle text-${codeVariant(code)}`">
                      {{ code.slice(1) }}
                    </span>
                  </td>
                  <td
                    class="small"
                    v-html="renderInlineCode($t(`api_docs.sections.errors.codes.${code}`))"
                  ></td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCol>

        <BCol xl="5">
          <h6 class="api-subtitle">{{ $t('api_docs.sections.errors.validation_title') }}</h6>
          <p class="text-muted small" v-html="renderInlineCode($t('api_docs.sections.errors.validation_body'))"></p>
          <ApiCodeBlock :code="validationExample" language="json" caption="422" />
        </BCol>
      </BRow>
    </section>

    <!-- Rate limiting -->
    <section id="rate_limits" class="api-section">
      <h3 class="api-section-title">{{ $t('api_docs.sections.rate_limits.title') }}</h3>
      <p
        class="api-section-lead"
        v-html="renderInlineCode($t('api_docs.sections.rate_limits.lead', { limit: rateLimit }))"
      ></p>

      <BRow class="g-4">
        <BCol xl="6">
          <h6 class="api-subtitle">{{ $t('api_docs.sections.rate_limits.headers_title') }}</h6>
          <p class="mb-0" v-html="renderInlineCode($t('api_docs.sections.rate_limits.headers_body'))"></p>
        </BCol>
        <BCol xl="6">
          <h6 class="api-subtitle">{{ $t('api_docs.sections.rate_limits.advice_title') }}</h6>
          <p class="mb-0" v-html="renderInlineCode($t('api_docs.sections.rate_limits.advice_body'))"></p>
        </BCol>
      </BRow>
    </section>

    <!-- Order statuses -->
    <section id="statuses" class="api-section">
      <h3 class="api-section-title">{{ $t('api_docs.sections.statuses.title') }}</h3>
      <p class="api-section-lead" v-html="renderInlineCode($t('api_docs.sections.statuses.lead'))"></p>

      <div class="api-status-grid">
        <div v-for="status in orderStatuses" :key="status.value" class="api-status-chip">
          <span class="api-status-dot" :class="`bg-${status.color}`"></span>
          <code class="api-inline-code">{{ status.value }}</code>
          <span class="text-muted small">{{ status.label }}</span>
        </div>
      </div>

      <BRow class="g-4 mt-1">
        <BCol xl="7">
          <h6 class="api-subtitle">{{ $t('api_docs.sections.statuses.groups_title') }}</h6>
          <p class="text-muted small" v-html="renderInlineCode($t('api_docs.sections.statuses.groups_body'))"></p>

          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col">{{ $t('api_docs.labels.value') }}</th>
                  <th scope="col">{{ $t('api_docs.labels.label') }}</th>
                  <th scope="col">{{ $t('api_docs.labels.meaning') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="group in statusGroups" :key="group.value">
                  <td><code class="api-inline-code">{{ group.value }}</code></td>
                  <td class="small">{{ groupLabel(group.value) }}</td>
                  <td>
                    <code
                      v-for="status in group.statuses"
                      :key="status"
                      class="api-inline-code me-1"
                    >{{ status }}</code>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </BCol>

        <BCol xl="5">
          <div class="alert alert-info d-flex gap-2 mb-0" role="alert">
            <i class="ri-information-line fs-16 mt-1"></i>
            <div>
              <strong class="d-block mb-1">{{ $t('api_docs.sections.statuses.transitions_title') }}</strong>
              <span>{{ $t('api_docs.sections.statuses.transitions_body') }}</span>
            </div>
          </div>
        </BCol>
      </BRow>
    </section>
  </div>
</template>

<style scoped>
.api-section {
  padding-top: 1.5rem;
  scroll-margin-top: 5.5rem;
}

.api-section + .api-section {
  margin-top: 2rem;
  border-top: 1px solid var(--vz-border-color);
}

.api-section-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.api-section-lead {
  color: var(--vz-secondary-color);
  max-width: 68ch;
}

.api-subtitle {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--vz-secondary-color);
  margin-bottom: 0.5rem;
}

.api-list {
  padding-left: 1.15rem;
  margin-bottom: 0;
  font-size: 0.875rem;
}

.api-list li + li {
  margin-top: 0.4rem;
}

.api-store-table {
  margin-top: 1.25rem;
}

.api-status-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.api-status-chip {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.6rem;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.4rem;
  overflow: hidden;
}

.api-status-chip .text-muted {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.api-status-dot {
  flex: 0 0 auto;
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 50%;
}
</style>
