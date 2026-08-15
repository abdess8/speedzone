<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import BottomSheet from '@/Components/BottomSheet.vue';
import { useBulkStatusAccess } from '@/composables/useBulkStatusAccess';

/**
 * Field shortcuts, stacked under the assistant's launcher on phones.
 *
 * A driver holding a parcel wants two taps, not a navigation: the sheet asks the
 * only two questions the full screen would have asked first — what, and into
 * which status — then hands over to it already answered. The scanner is offered
 * from here too, because on a round it is the way the selection is actually
 * made.
 *
 * Rendered on phones only: on a desktop the same entry lives in the orders and
 * returns toolbars, where there is room for it.
 */
const { t } = useI18n();
const page = usePage();
const { canBulkEdit } = useBulkStatusAccess();

const open = ref(false);
const loading = ref(false);
const entities = ref([]);
const entityType = ref(null);

const isAvailable = () => Boolean(page.props?.auth?.user) && canBulkEdit.value;

const load = async () => {
  if (entities.value.length > 0) {
    return;
  }

  loading.value = true;

  try {
    const { data } = await axios.get(route('bulk-status.options'));
    entities.value = data.entities;
    entityType.value = data.entities[0]?.value ?? null;
  } finally {
    loading.value = false;
  }
};

const show = async () => {
  open.value = true;
  await load();
};

const go = (toStatus = null, scan = false) => {
  open.value = false;

  router.get(route('bulk-status.index'), {
    entity_type: entityType.value,
    ...(toStatus ? { to_status: toStatus } : {}),
    ...(scan ? { scan: 1 } : {}),
  });
};

const currentEntity = () => entities.value.find((row) => row.value === entityType.value) ?? null;
</script>

<template>
  <div v-if="isAvailable()" class="qa-widget d-md-none">
    <button type="button" class="qa-launcher" :aria-label="$t('bulk_status.menu')" @click="show">
      <i class="ri-list-check-3"></i>
    </button>

    <BottomSheet
      :show="open"
      :title="$t('bulk_status.quick_action')"
      :subtitle="$t('bulk_status.subtitle')"
      @close="open = false"
    >
      <div v-if="loading" class="text-center text-muted py-4">
        <span class="spinner-border spinner-border-sm me-2"></span>
        {{ $t('bulk_status.selection.loading') }}
      </div>

      <template v-else>
        <label class="form-label">{{ $t('bulk_status.entity_step.title') }}</label>
        <div class="btn-group w-100 mb-3" role="group">
          <button
            v-for="row in entities"
            :key="row.value"
            type="button"
            class="btn"
            :class="entityType === row.value ? 'btn-primary' : 'btn-outline-primary'"
            @click="entityType = row.value"
          >
            <i :class="row.icon" class="me-1"></i>{{ row.label }}
          </button>
        </div>

        <label class="form-label">{{ $t('bulk_status.target_step.title') }}</label>
        <div class="vstack gap-2">
          <button
            v-for="option in currentEntity()?.targets ?? []"
            :key="option.value"
            type="button"
            class="btn btn-outline-light text-start p-3 d-flex align-items-center justify-content-between"
            @click="go(option.value)"
          >
            <span class="badge" :class="`bg-${option.color}-subtle text-${option.color}`">
              <i :class="option.icon" class="me-1"></i>{{ option.label }}
            </span>
            <i class="ri-arrow-right-s-line text-muted"></i>
          </button>

          <p v-if="(currentEntity()?.targets ?? []).length === 0" class="text-muted mb-0">
            {{ $t('bulk_status.target_step.empty') }}
          </p>
        </div>
      </template>

      <template #footer>
        <button type="button" class="btn btn-soft-primary w-100" @click="go(null, true)">
          <i class="ri-qr-scan-2-line align-bottom me-1"></i>{{ $t('bulk_status.scan.button') }}
        </button>
      </template>
    </BottomSheet>
  </div>
</template>

<style scoped>
/* Directly beneath the assistant's launcher, which owns the corner above it. */
.qa-widget {
  position: fixed;
  right: 1rem;
  bottom: calc(9.25rem + env(safe-area-inset-bottom, 0px));
  z-index: 1044;
}

.qa-launcher {
  display: flex;
  width: 2.75rem;
  height: 2.75rem;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 999px;
  background-color: var(--vz-secondary, #f15a24);
  box-shadow: 0 6px 18px rgba(241, 90, 36, 0.35);
  color: #fff;
  font-size: 1.25rem;
  transition: transform 0.18s ease;
}

.qa-launcher:hover {
  transform: translateY(-2px);
}

@media (prefers-reduced-motion: reduce) {
  .qa-launcher {
    transition: none;
  }
}
</style>
