<script setup>
import { ref, watch } from 'vue';
import { useStore } from '@/composables/useStore';

const { availableStores, mustChooseStore, switchStore } = useStore();

const open = ref(mustChooseStore.value);
const submitting = ref(false);

// Re-opens after a fresh login in the same tab, when the server says the choice
// has not been made yet for this session.
watch(mustChooseStore, (value) => {
  open.value = value;
});

const choose = (storeId) => {
  submitting.value = true;

  switchStore(storeId, {
    onFinish: () => {
      submitting.value = false;
      open.value = false;
    },
  });
};

const initials = (name) =>
  (name ?? '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase();
</script>

<template>
  <!-- Deliberately not dismissible: the rest of the interface is meaningless
       until we know which shop the user is working in. -->
  <BModal
    v-model="open"
    :title="$t('stores.picker.title')"
    centered
    hide-footer
    no-close-on-backdrop
    no-close-on-esc
    hide-header-close
  >
    <p class="text-muted mb-3">{{ $t('stores.picker.subtitle') }}</p>

    <div class="d-grid gap-2">
      <button
        v-for="store in availableStores"
        :key="store.id"
        type="button"
        class="btn btn-outline-light text-start d-flex align-items-center gap-3 p-3"
        :disabled="submitting"
        @click="choose(store.id)"
      >
        <img
          v-if="store.logo_url"
          :src="store.logo_url"
          :alt="store.name"
          class="store-picker-logo rounded"
        />
        <span v-else class="store-picker-initials rounded">{{ initials(store.name) }}</span>

        <span class="flex-grow-1">
          <span class="d-block fw-medium text-body">{{ store.name }}</span>
          <span v-if="store.category" class="d-block fs-12 text-muted">{{ store.category }}</span>
        </span>

        <i class="mdi mdi-chevron-right fs-20 text-muted"></i>
      </button>
    </div>
  </BModal>
</template>

<style scoped>
.store-picker-logo {
  height: 44px;
  width: 44px;
  object-fit: contain;
  background-color: var(--vz-light);
}

.store-picker-initials {
  height: 44px;
  width: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  color: var(--vz-primary);
  background-color: rgba(var(--vz-primary-rgb), 0.12);
}
</style>
