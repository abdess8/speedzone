<script setup>
import { computed, ref, watch } from 'vue';
import BottomSheet from '@/Components/BottomSheet.vue';
import TransitionBadge from './TransitionBadge.vue';

/**
 * Last stop before the write.
 *
 * The breakdown is per source status rather than a single total, because "12
 * commandes → Livré" hides the one thing worth checking: that eight of them
 * were out on the round and four had only reached the city.
 */
const props = defineProps({
  show: { type: Boolean, default: false },
  items: { type: Array, default: () => [] },
  target: { type: Object, default: null },
  entityLabel: { type: String, default: '' },
  processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const comment = ref('');

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      comment.value = '';
    }
  }
);

const breakdown = computed(() => {
  const groups = new Map();

  props.items.forEach((item) => {
    const key = item.from_status.value;
    const existing = groups.get(key);

    existing
      ? (existing.count += 1)
      : groups.set(key, { count: 1, from: item.from_status, to: item.to_status });
  });

  return [...groups.values()].sort((a, b) => b.count - a.count);
});
</script>

<template>
  <BottomSheet
    :show="show"
    :title="$t('bulk_status.confirm.title')"
    size="lg"
    :dismissible="!processing"
    @close="$emit('close')"
  >
    <p class="mb-2">
      {{ $t('bulk_status.confirm.intro') }}
      <strong>{{ $t('bulk_status.confirm.items', { count: items.length }) }}</strong>
      <span class="text-muted"> · {{ entityLabel }}</span>
    </p>

    <ul class="list-group list-group-flush mb-3">
      <li
        v-for="group in breakdown"
        :key="group.from.value"
        class="list-group-item d-flex align-items-center justify-content-between gap-2 px-0 flex-wrap"
      >
        <TransitionBadge :from="group.from" :to="group.to" />
        <span class="badge bg-light text-body">× {{ group.count }}</span>
      </li>
    </ul>

    <label class="form-label" for="bulk-status-comment">{{ $t('bulk_status.confirm.comment') }}</label>
    <textarea
      id="bulk-status-comment"
      v-model="comment"
      class="form-control"
      rows="2"
      maxlength="1000"
      :disabled="processing"
    ></textarea>
    <div class="form-text">{{ $t('bulk_status.confirm.comment_help') }}</div>

    <div class="alert alert-warning d-flex gap-2 mt-3 mb-0 py-2">
      <i class="ri-error-warning-line fs-16"></i>
      <div class="small">
        <div>{{ $t('bulk_status.confirm.irreversible') }}</div>
        <div class="text-muted">{{ $t('bulk_status.confirm.consequences') }}</div>
      </div>
    </div>

    <template #footer>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-light flex-fill" :disabled="processing" @click="$emit('close')">
          {{ $t('common.cancel') }}
        </button>
        <button
          type="button"
          class="btn btn-primary flex-fill"
          :disabled="processing || items.length === 0"
          @click="$emit('confirm', comment)"
        >
          <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
          {{ $t('bulk_status.confirm.submit') }}
        </button>
      </div>
    </template>
  </BottomSheet>
</template>
