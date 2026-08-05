<script setup>
import { computed } from 'vue';
import Prism from 'prismjs';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-markup-templating';
import 'prismjs/components/prism-php';
import { useClipboard } from '@/composables/useClipboard';

const props = defineProps({
  code: { type: String, required: true },
  language: { type: String, default: 'json' },
  caption: { type: String, default: '' },
});

const { copy, copied } = useClipboard();

const escapeHtml = (value) =>
  value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const highlighted = computed(() => {
  const grammar = Prism.languages[props.language];

  return grammar
    ? Prism.highlight(props.code, grammar, props.language)
    : escapeHtml(props.code);
});
</script>

<template>
  <div class="api-code">
    <div v-if="caption" class="api-code-caption">
      <span class="text-uppercase">{{ caption }}</span>
    </div>

    <button
      type="button"
      class="api-code-copy"
      :class="{ 'is-copied': copied === 'code' }"
      :title="copied === 'code' ? $t('api_docs.actions.copied') : $t('api_docs.actions.copy')"
      @click="copy(code, 'code')"
    >
      <i :class="copied === 'code' ? 'ri-check-line' : 'ri-file-copy-line'"></i>
      <span class="visually-hidden">{{ $t('api_docs.actions.copy') }}</span>
    </button>

    <pre class="api-code-pre"><code v-html="highlighted"></code></pre>
  </div>
</template>

<style scoped lang="scss">
.api-code {
  position: relative;
  background: #0f172a;
  border: 1px solid rgba(148, 163, 184, 0.18);
  border-radius: 0.5rem;
  overflow: hidden;
}

.api-code-caption {
  display: flex;
  align-items: center;
  padding: 0.4rem 0.85rem;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.05em;
  color: #94a3b8;
  background: rgba(148, 163, 184, 0.08);
  border-bottom: 1px solid rgba(148, 163, 184, 0.14);
}

.api-code-copy {
  position: absolute;
  top: 0.4rem;
  right: 0.4rem;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.9rem;
  height: 1.9rem;
  padding: 0;
  color: #94a3b8;
  background: rgba(148, 163, 184, 0.12);
  border: 0;
  border-radius: 0.35rem;
  transition: color 0.15s ease, background-color 0.15s ease;

  &:hover {
    color: #e2e8f0;
    background: rgba(148, 163, 184, 0.24);
  }

  &.is-copied {
    color: #4ade80;
  }
}

.api-code-pre {
  margin: 0;
  padding: 0.9rem 3rem 0.9rem 1rem;
  max-height: 30rem;
  overflow: auto;
  font-size: 0.8125rem;
  line-height: 1.65;
  color: #e2e8f0;
  background: transparent;

  code {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
    color: inherit;
    text-shadow: none;
    white-space: pre;
  }
}

// The Velzon Prism theme paints tokens for a light background; these override
// it inside the dark pane only. Prism writes the markup, so :deep() is required.
.api-code-pre :deep(.token) {
  text-shadow: none;
  background: transparent;
}

.api-code-pre :deep(.token.comment),
.api-code-pre :deep(.token.prolog),
.api-code-pre :deep(.token.doctype),
.api-code-pre :deep(.token.cdata) {
  color: #64748b;
}

.api-code-pre :deep(.token.punctuation) {
  color: #94a3b8;
}

.api-code-pre :deep(.token.property),
.api-code-pre :deep(.token.tag),
.api-code-pre :deep(.token.constant),
.api-code-pre :deep(.token.symbol),
.api-code-pre :deep(.token.deleted) {
  color: #7dd3fc;
}

.api-code-pre :deep(.token.boolean),
.api-code-pre :deep(.token.number) {
  color: #fbbf24;
}

.api-code-pre :deep(.token.selector),
.api-code-pre :deep(.token.attr-name),
.api-code-pre :deep(.token.string),
.api-code-pre :deep(.token.char),
.api-code-pre :deep(.token.builtin),
.api-code-pre :deep(.token.inserted) {
  color: #86efac;
}

.api-code-pre :deep(.token.operator),
.api-code-pre :deep(.token.entity),
.api-code-pre :deep(.token.url),
.api-code-pre :deep(.token.variable) {
  color: #e2e8f0;
}

.api-code-pre :deep(.token.atrule),
.api-code-pre :deep(.token.attr-value),
.api-code-pre :deep(.token.function),
.api-code-pre :deep(.token.class-name) {
  color: #c4b5fd;
}

.api-code-pre :deep(.token.keyword) {
  color: #f9a8d4;
}
</style>
