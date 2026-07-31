<script setup>
/**
 * The rounded surface every mobile dashboard section sits on.
 *
 * Sections differ in content but not in chrome, so the radius, padding and
 * elevation live here rather than being restated — and re-tuned inconsistently
 * — in each one.
 */
defineProps({
  title: { type: String, default: '' },
  caption: { type: String, default: '' },
  /** Sections that scroll horizontally bleed to the card edge themselves. */
  flush: { type: Boolean, default: false },
});
</script>

<template>
  <section class="mdash-card">
    <header v-if="title || $slots.action" class="mdash-card-head">
      <div class="mdash-card-heading">
        <h2 class="mdash-card-title">{{ title }}</h2>
        <p v-if="caption" class="mdash-card-caption">{{ caption }}</p>
      </div>
      <slot name="action" />
    </header>

    <div :class="flush ? 'mdash-card-body-flush' : 'mdash-card-body'">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.mdash-card {
  overflow: hidden;
  border-radius: var(--mdash-radius);
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--mdash-shadow);
}

.mdash-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1rem 1.125rem 0.375rem;
}

/* Lets a long title ellipsize instead of shoving the action off the card. */
.mdash-card-heading {
  min-width: 0;
}

.mdash-card-title {
  overflow: hidden;
  margin: 0;
  color: var(--vz-heading-color, #495057);
  font-size: 1.0625rem;
  font-weight: 600;
  letter-spacing: -0.01em;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mdash-card-caption {
  margin: 0.125rem 0 0;
  color: var(--mdash-muted);
  font-size: 0.75rem;
}

.mdash-card-body {
  padding: 0.75rem 1.125rem 1.125rem;
}

.mdash-card-body-flush {
  padding: 0.75rem 0 1.125rem;
}
</style>
