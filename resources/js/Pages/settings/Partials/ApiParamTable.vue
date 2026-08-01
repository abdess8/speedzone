<script setup>
import { renderInlineCode } from './apiText';

defineProps({
  title: { type: String, required: true },
  params: { type: Array, default: () => [] },
});
</script>

<template>
  <div v-if="params.length" class="api-params">
    <h6 class="api-params-title">{{ title }}</h6>

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0 api-params-table">
        <thead>
          <tr>
            <th scope="col">{{ $t('api_docs.labels.name') }}</th>
            <th scope="col">{{ $t('api_docs.labels.type') }}</th>
            <th scope="col">{{ $t('api_docs.labels.description') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="param in params" :key="param.name">
            <td class="text-nowrap">
              <code class="api-inline-code">{{ param.name }}</code>
              <span v-if="param.required === true" class="api-required">
                {{ $t('api_docs.labels.required') }}
              </span>
              <span v-else-if="param.required === 'conditional'" class="api-conditional">
                {{ $t('api_docs.labels.required') }}*
              </span>
            </td>
            <td class="text-nowrap">
              <span class="api-type">{{ param.type }}</span>
            </td>
            <td>
              <span v-html="renderInlineCode($t(`api_docs.${param.desc}`))"></span>
              <span v-if="param.default" class="d-block text-muted small mt-1">
                {{ $t('api_docs.labels.default') }}:
                <code class="api-inline-code">{{ param.default }}</code>
              </span>
              <span v-if="param.rule" class="d-block text-muted small mt-1">
                <code class="api-inline-code">{{ param.rule }}</code>
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.api-params-title {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--vz-secondary-color);
  margin-bottom: 0.5rem;
}

.api-params-table th {
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--vz-secondary-color);
  border-bottom-width: 1px;
}

.api-params-table td {
  font-size: 0.8125rem;
  vertical-align: top;
}

.api-type {
  font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
  font-size: 0.75rem;
  color: var(--vz-secondary-color);
}

.api-required,
.api-conditional {
  display: block;
  margin-top: 0.15rem;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.api-required {
  color: var(--vz-danger);
}

.api-conditional {
  color: var(--vz-warning);
}
</style>
