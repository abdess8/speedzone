<script setup>
/**
 * Mobile list row for any entity — orders, pickups, transfers, returns, users.
 *
 * The desktop tables trade a dozen columns against a small font, which does not
 * survive a phone: they become a horizontal scroll with the primary action off
 * screen. Every list therefore switches to these cards below `lg`, and they all
 * share one shape so a driver moving between modules recognises the layout.
 *
 * Content is passed as data rather than markup (`rows`) so a list page describes
 * *what* to show and never re-implements the card's spacing or truncation.
 */
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  statusLabel: { type: String, default: '' },
  /** Bootstrap contextual colour, e.g. `success`; drives the subtle badge. */
  statusColor: { type: String, default: 'secondary' },
  /**
   * Detail lines rendered as a two-column grid.
   *
   * @type {{label: string, value: string|number|null, emphasis?: boolean}[]}
   */
  rows: { type: Array, default: () => [] },
  /** Offer a checkbox so bulk actions also work on mobile. */
  selectable: { type: Boolean, default: false },
  selected: { type: Boolean, default: false },
  /** When false the card is inert — used where a role may not open the detail. */
  clickable: { type: Boolean, default: true },
});

const emit = defineEmits(['open', 'toggle-select']);
</script>

<template>
  <div
    class="card entity-card mb-2"
    :class="{ 'entity-card-selected': selected, 'entity-card-clickable': clickable }"
    :role="clickable ? 'button' : undefined"
    :aria-label="clickable ? title : undefined"
    @click="clickable && emit('open')"
  >
    <div class="card-body p-3">
      <div class="d-flex align-items-start gap-2">
        <input
          v-if="selectable"
          class="form-check-input mt-1 flex-shrink-0"
          type="checkbox"
          :checked="selected"
          :aria-label="title"
          @click.stop
          @change="emit('toggle-select')"
        />

        <slot name="avatar"></slot>

        <div class="min-w-0 flex-grow-1">
          <div class="fw-semibold fs-14 text-truncate">
            <slot name="title">{{ title }}</slot>
          </div>
          <div v-if="subtitle || $slots.subtitle" class="text-muted fs-12 mt-1 text-truncate">
            <slot name="subtitle">{{ subtitle }}</slot>
          </div>
        </div>

        <span
          v-if="statusLabel"
          class="badge flex-shrink-0"
          :class="`bg-${statusColor}-subtle text-${statusColor}`"
        >
          {{ statusLabel }}
        </span>
      </div>

      <dl v-if="rows.length" class="entity-card-grid mb-0 mt-3">
        <div v-for="row in rows" :key="row.label" class="min-w-0">
          <dt class="text-muted fs-11 text-uppercase fw-normal">{{ row.label }}</dt>
          <dd class="mb-0 fs-13 text-truncate" :class="{ 'fw-semibold': row.emphasis }">
            {{ row.value ?? '—' }}
          </dd>
        </div>
      </dl>

      <div v-if="$slots.badges" class="mt-2 d-flex flex-wrap gap-1">
        <slot name="badges"></slot>
      </div>

      <!-- Actions are stopped from bubbling so tapping a button never also
           opens the detail sheet behind it. -->
      <div v-if="$slots.actions" class="mt-3 d-flex gap-2" @click.stop>
        <slot name="actions"></slot>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Elevation is deliberately left to the global `.card` rule: a scoped shadow
   here outranks it on specificity and would quietly opt this card out of the
   app-wide setting. */
.entity-card {
  border-radius: 0.85rem;
}

.entity-card-clickable {
  cursor: pointer;
}

.entity-card-selected {
  border: 1px solid var(--vz-primary, #0d4a9d);
}

.entity-card-grid {
  display: grid;
  /* Two columns on a phone, three once there is room, so a 4-line card stays
     two rows tall instead of pushing the actions below the fold. */
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.5rem 0.75rem;
}

@media (min-width: 576px) {
  .entity-card-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.min-w-0 {
  min-width: 0;
}
</style>
