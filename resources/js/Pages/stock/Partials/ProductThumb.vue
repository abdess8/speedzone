<script setup>
import { computed } from 'vue';

/**
 * Product avatar: the photo when there is one, its initials otherwise.
 *
 * A catalog screen is scanned vertically, and a column of identical grey
 * placeholders is worse than no column at all — so a reference shipped without
 * a photo gets a tile whose colour is derived from its own name, which makes it
 * recognisable from one visit to the next.
 */
const props = defineProps({
  name: { type: String, default: '' },
  photoUrl: { type: String, default: null },
  initials: { type: String, default: '' },
  size: { type: Number, default: 40 },
  rounded: { type: Boolean, default: true },
});

/** Bootstrap contextual palette, indexed by a stable hash of the name. */
const PALETTE = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'];

const tone = computed(() => {
  const label = props.name || props.initials || '';
  let hash = 0;

  for (let index = 0; index < label.length; index += 1) {
    hash = (hash * 31 + label.charCodeAt(index)) % 9973;
  }

  return PALETTE[hash % PALETTE.length];
});

const letters = computed(() => {
  if (props.initials) {
    return props.initials;
  }

  const words = props.name.trim().split(/\s+/).filter(Boolean).slice(0, 2);

  return words.map((word) => word.charAt(0).toUpperCase()).join('') || '#';
});

const style = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  fontSize: `${Math.max(10, Math.round(props.size * 0.36))}px`,
}));
</script>

<template>
  <img
    v-if="photoUrl"
    :src="photoUrl"
    :alt="name"
    class="product-thumb"
    :class="rounded ? 'rounded' : ''"
    :style="style"
  />
  <span
    v-else
    class="product-thumb product-thumb--initials"
    :class="[rounded ? 'rounded' : '', `bg-${tone}-subtle`, `text-${tone}`]"
    :style="style"
    :title="name"
    aria-hidden="true"
  >
    {{ letters }}
  </span>
</template>

<style scoped>
.product-thumb {
  flex: 0 0 auto;
  object-fit: cover;
  background-color: var(--vz-light);
}

.product-thumb--initials {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  letter-spacing: 0.02em;
}
</style>
