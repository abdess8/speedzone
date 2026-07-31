<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * The backlog that is actually waiting on someone, one swipeable card at a time.
 *
 * The desktop dashboard reports these counts as four tiles among twenty-three,
 * where nothing distinguishes a number you read from a number you must act on.
 * Here they are the only thing on screen at that scroll position, each one a
 * link straight into the filtered list, and a bucket at zero is dropped rather
 * than shown as a reassuring "0".
 */
defineProps({
  /**
   * @type {import('vue').PropType<Array<{
   *   key: string, label: string, count: number, tone: string,
   *   icon: string, href: string
   * }>>}
   */
  items: { type: Array, default: () => [] },
  openLabel: { type: String, required: true },
});

const activeIndex = ref(0);

/**
 * Which card is centred, derived from scroll position rather than tracked on
 * tap: the strip is swiped far more often than the dots are looked at.
 */
function syncActiveIndex(event) {
  const track = event.currentTarget;
  const card = track.firstElementChild;

  if (!card) {
    return;
  }

  const stride = card.offsetWidth + parseFloat(getComputedStyle(track).columnGap || '0');
  activeIndex.value = stride > 0 ? Math.round(track.scrollLeft / stride) : 0;
}
</script>

<template>
  <div>
    <div class="mdash-tasks" @scroll.passive="syncActiveIndex">
      <Link v-for="item in items" :key="item.key" :href="item.href" class="mdash-task">
        <span class="mdash-task-icon" :class="`bg-${item.tone}-subtle text-${item.tone}`">
          <i :class="item.icon"></i>
        </span>
        <span class="mdash-task-text">
          <span class="mdash-task-label">{{ item.label }}</span>
          <span class="mdash-task-count" :class="`text-${item.tone}`">{{ item.count }}</span>
        </span>
        <span class="mdash-task-cta" :aria-label="openLabel">
          <i class="ri-arrow-right-s-line"></i>
        </span>
      </Link>
    </div>

    <div v-if="items.length > 1" class="mdash-dots" aria-hidden="true">
      <span
        v-for="(item, index) in items"
        :key="item.key"
        class="mdash-dot"
        :class="{ 'mdash-dot-active': index === activeIndex }"
      ></span>
    </div>
  </div>
</template>

<style scoped>
.mdash-tasks {
  display: flex;
  overflow-x: auto;
  gap: 0.75rem;
  margin: 0 calc(var(--mdash-gutter) * -1);
  padding: 0 var(--mdash-gutter);
  scroll-padding-left: var(--mdash-gutter);
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
}

.mdash-tasks::-webkit-scrollbar {
  display: none;
}

.mdash-task {
  display: flex;
  align-items: center;
  /* Just short of the full width, so the next card's edge shows and the strip
     announces itself as swipeable without a hint label. */
  flex: 0 0 92%;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  border-radius: var(--mdash-radius);
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--mdash-shadow);
  color: inherit;
  scroll-snap-align: start;
  text-decoration: none;
}

.mdash-task-icon {
  display: inline-flex;
  width: 2.5rem;
  height: 2.5rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.75rem;
  font-size: 1.25rem;
}

.mdash-task-text {
  display: flex;
  min-width: 0;
  flex-direction: column;
  flex-grow: 1;
}

.mdash-task-label {
  overflow: hidden;
  color: var(--mdash-muted);
  font-size: 0.75rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mdash-task-count {
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.25;
}

.mdash-task-cta {
  display: inline-flex;
  width: 1.75rem;
  height: 1.75rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background-color: var(--vz-light, #f3f6f9);
  color: var(--mdash-muted);
  font-size: 1.125rem;
}

.mdash-dots {
  display: flex;
  justify-content: center;
  gap: 0.3125rem;
  margin-top: 0.625rem;
}

.mdash-dot {
  width: 0.3125rem;
  height: 0.3125rem;
  border-radius: 999px;
  background-color: var(--vz-border-color, #e9ebec);
  transition: width 0.2s ease, background-color 0.2s ease;
}

.mdash-dot-active {
  width: 1rem;
  background-color: var(--vz-primary, #0d4a9d);
}

@media (prefers-reduced-motion: reduce) {
  .mdash-dot {
    transition: none;
  }
}
</style>
