<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  show: { type: Boolean, default: false },
  sectors: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  processing: { type: Boolean, default: false },
});

const emit = defineEmits(["close", "submit"]);

const open = computed({
  get: () => props.show,
  set: (value) => {
    if (!value) emit("close");
  },
});

const sectorId = ref("");
const driverId = ref("");
const reassign = ref(false);

const selectedSector = computed(() =>
  props.sectors.find((sector) => sector.id === Number(sectorId.value))
);

// The drivers who cover the sector come first: they are the intended answer,
// and the rest of the fleet is only there for the sectors nobody covers yet.
const sectorDrivers = computed(() => selectedSector.value?.drivers ?? []);

const otherDrivers = computed(() => {
  const covered = new Set(sectorDrivers.value.map((driver) => driver.id));

  return props.drivers.filter((driver) => !covered.has(driver.id));
});

const affectedCount = computed(() => {
  if (!selectedSector.value) return 0;

  return reassign.value ? selectedSector.value.total : selectedSector.value.unassigned;
});

const canSubmit = computed(
  () => !!sectorId.value && !!driverId.value && affectedCount.value > 0 && !props.processing
);

// A driver picked for the previous sector is rarely the right one for the next.
watch(sectorId, () => {
  driverId.value = sectorDrivers.value.length === 1 ? sectorDrivers.value[0].id : "";
});

watch(
  () => props.show,
  (value) => {
    if (!value) return;

    sectorId.value = "";
    driverId.value = "";
    reassign.value = false;
  }
);

const submit = () => {
  if (!canSubmit.value) return;

  emit("submit", {
    sector_id: Number(sectorId.value),
    driver_id: Number(driverId.value),
    reassign: reassign.value,
    count: affectedCount.value,
    sector: selectedSector.value?.name,
    driver:
      [...sectorDrivers.value, ...otherDrivers.value].find(
        (driver) => driver.id === Number(driverId.value)
      )?.name ?? "",
  });
};
</script>

<template>
  <BModal v-model="open" :title="$t('orders.dispatch.title')" centered hide-footer>
    <p class="text-muted fs-13">{{ $t('orders.dispatch.subtitle') }}</p>

    <p v-if="sectors.length === 0" class="text-muted mb-0">
      {{ $t('orders.dispatch.empty') }}
    </p>

    <template v-else>
      <div class="mb-3">
        <label class="form-label">{{ $t('orders.dispatch.sector') }}</label>
        <select v-model="sectorId" class="form-select">
          <option value="">{{ $t('orders.dispatch.select_sector') }}</option>
          <option v-for="sector in sectors" :key="sector.id" :value="sector.id">
            {{ sector.city ? `${sector.name} — ${sector.city}` : sector.name }}
            ({{ sector.unassigned }}/{{ sector.total }})
          </option>
        </select>
        <div v-if="selectedSector" class="form-text">
          {{
            $t('orders.dispatch.pending', {
              unassigned: selectedSector.unassigned,
              total: selectedSector.total,
            })
          }}
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ $t('orders.dispatch.driver') }}</label>
        <select v-model="driverId" class="form-select" :disabled="!sectorId">
          <option value="">{{ $t('orders.dispatch.select_driver') }}</option>
          <optgroup v-if="sectorDrivers.length" :label="$t('orders.dispatch.sector_drivers')">
            <option v-for="driver in sectorDrivers" :key="driver.id" :value="driver.id">
              {{ driver.name }}
            </option>
          </optgroup>
          <optgroup v-if="otherDrivers.length" :label="$t('orders.dispatch.other_drivers')">
            <option v-for="driver in otherDrivers" :key="driver.id" :value="driver.id">
              {{ driver.name }}
            </option>
          </optgroup>
        </select>
        <div v-if="sectorId && sectorDrivers.length === 0" class="form-text text-warning">
          {{ $t('orders.dispatch.no_sector_driver') }}
        </div>
      </div>

      <div class="form-check mb-3">
        <input id="dispatchReassign" v-model="reassign" class="form-check-input" type="checkbox" />
        <label class="form-check-label fs-13" for="dispatchReassign">
          {{ $t('orders.dispatch.reassign') }}
        </label>
      </div>

      <div class="hstack gap-2 justify-content-end">
        <button type="button" class="btn btn-light" @click="emit('close')">
          {{ $t('common.cancel') }}
        </button>
        <button type="button" class="btn btn-primary" :disabled="!canSubmit" @click="submit">
          <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="ri-e-bike-2-line align-bottom me-1"></i>
          {{ $t('orders.dispatch.action') }}
        </button>
      </div>
    </template>
  </BModal>
</template>
