<script setup>
/**
 * The rounded surface every panel of the desktop summary sits on.
 *
 * Panels differ in content but not in chrome, so the radius, padding and
 * elevation live here rather than being restated — and re-tuned inconsistently
 * — in each one. This is the desktop counterpart of the mobile `SectionCard`;
 * the two are kept apart because their padding and title scale differ.
 */
defineProps({
  title: { type: String, default: '' },
  caption: { type: String, default: '' },
  /** Panels whose rows run to the card edge pad themselves instead. */
  flush: { type: Boolean, default: false },
  /** Lets a panel stretch to the tallest sibling in its grid row. */
  fill: { type: Boolean, default: false },
});
</script>

<template>
  <section class="ddash-panel" :class="{ 'ddash-panel-fill': fill }">
    <header v-if="title || $slots.action" class="ddash-panel-head">
      <div v-if="title" class="ddash-panel-heading">
        <h2 class="ddash-panel-title">{{ title }}</h2>
        <p v-if="caption" class="ddash-panel-caption">{{ caption }}</p>
      </div>
      <slot name="action" />
    </header>

    <div class="ddash-panel-body" :class="{ 'ddash-panel-body-flush': flush }">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.ddash-panel {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  border-radius: var(--ddash-radius, 1.25rem);
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--ddash-shadow, 0 1px 2px rgba(13, 42, 77, 0.08));
}

.ddash-panel-fill {
  height: 100%;
}

.ddash-panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.375rem 0.5rem;
}

/* Lets a long title ellipsize instead of shoving the action off the card. */
.ddash-panel-heading {
  min-width: 0;
}

.ddash-panel-title {
  overflow: hidden;
  margin: 0;
  color: var(--vz-heading-color, #495057);
  font-size: 1.0625rem;
  font-weight: 600;
  letter-spacing: -0.01em;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ddash-panel-caption {
  margin: 0.125rem 0 0;
  color: var(--ddash-muted, #878a99);
  font-size: 0.75rem;
}

/* A column so a panel that stretches to its neighbour can push its footer to
   the bottom edge rather than leaving the gap under it. */
.ddash-panel-body {
  display: flex;
  flex-direction: column;
  flex-grow: 1;
  padding: 0.75rem 1.375rem 1.375rem;
}

.ddash-panel-body-flush {
  padding: 0.5rem 0 0.75rem;
}
</style>
