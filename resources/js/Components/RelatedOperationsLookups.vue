<script setup>
import EntityLink from "@/Components/EntityLink.vue";

defineProps({
  pickupRequest: { type: Object, default: null },
  activeTransfer: { type: Object, default: null },
  activeReturn: { type: Object, default: null },
});
</script>

<template>
  <div class="d-flex flex-wrap gap-3">
    <EntityLink
      v-if="pickupRequest"
      type="pickup"
      :entity="pickupRequest"
    />
    <EntityLink
      v-if="activeTransfer"
      type="transfer"
      :entity="activeTransfer"
    />
    <EntityLink
      v-if="activeReturn"
      type="return"
      :entity="activeReturn"
    />
    <div v-if="!pickupRequest && !activeTransfer && !activeReturn" class="text-muted fs-13">
      {{ $t("orders.lookups.empty") }}
    </div>
  </div>
</template>
