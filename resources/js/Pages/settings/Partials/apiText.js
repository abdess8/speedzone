const escapeHtml = (value) =>
  String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

/**
 * Renders the `backtick` spans used throughout the api_docs translations as
 * inline `<code>`, so field names and header values stand out in prose without
 * every string having to carry HTML.
 *
 * The input is escaped first: the output is only ever fed to `v-html`.
 *
 * @param {string} text
 * @returns {string} HTML
 */
export function renderInlineCode(text) {
  return escapeHtml(text ?? '').replace(/`([^`]+)`/g, '<code class="api-inline-code">$1</code>');
}
