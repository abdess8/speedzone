<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * The backlog, as tiles that go straight to the filtered list behind them.
 *
 * These are the only figures on the screen that are not there to be read but
 * to be cleared, so they get their own band and each one is a link rather than
 * a number. The parent drops empty buckets: a queue of zero is not work, and
 * showing it would bury the queues that are.
 */
defineProps({
  /**
   * @type {import('vue').PropType<Array<{
   *   key: string, label: string, count: number, tone: string,
   *   icon: string, href?: string
   * }>>}
   */
  items: { type: Array, default: () => [] },
  emptyLabel: { type: String, required: true },
  loading: { type: Boolean, default: false },
});
</script>

<template>
  <div v-if="loading" class="ddash-tasks">
    <div v-for="n in 4" :key="n" class="ddash-task ddash-task-skeleton" aria-hidden="true"></div>
  </div>

  <p v-else-if="!items.length" class="ddash-tasks-empty">
    <i class="ri-checkbox-circle-fill text-success"></i>
    {{ emptyLabel }}
  </p>

  <div v-else class="ddash-tasks">
    <component
      :is="item.href ? Link : 'div'"
      v-for="item in items"
      :key="item.key"
      :href="item.href"
      class="ddash-task"
    >
      <span class="ddash-task-icon" :class="`bg-${item.tone}-subtle text-${item.tone}`">
        <i :class="item.icon"></i>
      </span>

      <span class="ddash-task-main">
        <span class="ddash-task-count">{{ item.count }}</span>
        <span class="ddash-task-label">{{ item.label }}</span>
      </span>

      <i v-if="item.href" class="ri-arrow-right-up-line ddash-task-arrow"></i>
    </component>
  </div>
</template>

<style scoped>
.ddash-tasks {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
}

.ddash-task {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.9375rem 1.125rem;
  border-radius: var(--ddash-radius, 1.25rem);
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--ddash-shadow, 0 1px 2px rgba(13, 42, 77, 0.08));
  color: inherit;
  text-decoration: none;
}

.ddash-task-icon {
  display: inline-flex;
  width: 2.625rem;
  height: 2.625rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.875rem;
  font-size: 1.25rem;
}

.ddash-task-main {
  display: block;
  min-width: 0;
  flex-grow: 1;
}

.ddash-task-count {
  display: block;
  color: var(--vz-heading-color, #495057);
  font-size: 1.375rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
}

.ddash-task-label {
  display: block;
  overflow: hidden;
  color: var(--ddash-muted, #878a99);
  font-size: 0.75rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ddash-task-arrow {
  flex-shrink: 0;
  color: var(--ddash-muted, #878a99);
  font-size: 1rem;
}

.ddash-tasks-empty {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  padding: 1rem 1.25rem;
  border-radius: var(--ddash-radius, 1.25rem);
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--ddash-shadow, 0 1px 2px rgba(13, 42, 77, 0.08));
  color: var(--ddash-muted, #878a99);
  font-size: 0.8125rem;
}

.ddash-task-skeleton {
  height: 4.5rem;
  animation: ddash-pulse 1.4s ease-in-out infinite;
}

@keyframes ddash-pulse {
  50% {
    opacity: 0.5;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ddash-task-skeleton {
    animation: none;
  }
}
</style>
