<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
  type: {
    type: String,
    required: true,
    validator: (v) => ["order", "pickup", "transfer"].includes(v),
  },
  entity: { type: Object, required: true },
  showStatus: { type: Boolean, default: true },
  size: { type: String, default: "md" },
});

const config = computed(() => {
  const map = {
    order: {
      icon: "ri-box-3-line",
      route: "orders.show",
      labelKey: null,
      prefix: "",
    },
    pickup: {
      icon: "ri-hand-heart-line",
      route: "pickup-requests.show",
      labelKey: "orders.lookups.pickup",
      prefix: "PU",
    },
    transfer: {
      icon: "ri-truck-line",
      route: "transfers.show",
      labelKey: "orders.lookups.transfer",
      prefix: "TRF",
    },
  };
  return map[props.type];
});

const href = computed(() => route(config.value.route, props.entity.id));

const displayRef = computed(
  () => props.entity.reference || props.entity.tracking_number || `#${props.entity.id}`
);

const paddingClass = computed(() => (props.size === "sm" ? "px-2 py-1" : "px-3 py-2"));
</script>

<template>
  <Link
    :href="href"
    class="entity-link d-inline-flex align-items-center gap-2 rounded border text-decoration-none transition-all"
    :class="paddingClass"
  >
    <span
      class="entity-link__icon d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary flex-shrink-0"
      :class="size === 'sm' ? 'avatar-xs' : 'avatar-sm'"
    >
      <i :class="config.icon"></i>
    </span>
    <span class="d-flex flex-column">
      <span class="fw-semibold text-body entity-link__label">
        <template v-if="config.labelKey">{{ $t(config.labelKey) }}:</template>
        {{ displayRef }}
      </span>
      <span
        v-if="showStatus && entity.status_label"
        class="badge align-self-start mt-1"
        :class="`bg-${entity.status_color}-subtle text-${entity.status_color}`"
      >
        {{ entity.status_label }}
      </span>
    </span>
    <i class="ri-arrow-right-s-line text-muted ms-1 entity-link__arrow"></i>
  </Link>
</template>

<style scoped>
.entity-link {
  background: var(--vz-card-bg, #fff);
  border-color: var(--vz-border-color) !important;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.entity-link:hover {
  border-color: var(--vz-primary) !important;
  box-shadow: 0 2px 8px rgba(var(--vz-primary-rgb, 64, 81, 137), 0.12);
  transform: translateY(-1px);
}

.entity-link:hover .entity-link__label {
  color: var(--vz-primary) !important;
}

.entity-link__arrow {
  opacity: 0;
  transition: opacity 0.15s ease;
}

.entity-link:hover .entity-link__arrow {
  opacity: 1;
}
</style>
