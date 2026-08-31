<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import InputError from '@/Components/InputError.vue';
import ProductThumb from '../../Partials/ProductThumb.vue';

/**
 * What the collector signs for at the shop counter.
 *
 * Pre-filled with the vendor's declaration because agreeing is the common case
 * and re-typing thirty identical numbers on a phone, standing in a shop, is how
 * counts get faked. Every field stays editable: the whole point of the visit is
 * that the figures can be corrected in front of the person who wrote them.
 */

const props = defineProps({
  reception: { type: Object, required: true },
});

const { t } = useI18n();

const lines = reactive(
  props.reception.items.map((item) => ({
    id: item.id,
    name: item.name,
    sku: item.sku,
    photo_url: item.photo_url,
    initials: item.initials,
    quantity_sent: item.quantity_sent,
    quantity_collected: item.quantity_collected ?? item.quantity_sent,
    note: item.note ?? '',
  }))
);

const notes = ref(props.reception.collection_notes ?? '');
const errors = ref({});
const processing = ref(false);

const totalDeclared = computed(() => lines.reduce((total, line) => total + line.quantity_sent, 0));

const totalCollected = computed(() =>
  lines.reduce((total, line) => total + (Number(line.quantity_collected) || 0), 0)
);

/** Signed gap between what the vendor declared and what is being loaded. */
const gap = (line) => (Number(line.quantity_collected) || 0) - line.quantity_sent;

const matchAll = () => {
  lines.forEach((line) => {
    line.quantity_collected = line.quantity_sent;
  });
};

const lineError = (position, field) => errors.value[`items.${position}.${field}`] ?? '';

const submit = async () => {
  const confirmed = await Swal.fire({
    title: t('stock.receptions.collection_form.confirm_title'),
    text: t('stock.receptions.collection_form.confirm_text', { units: totalCollected.value }),
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: t('stock.receptions.collection_form.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-info', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (!confirmed.isConfirmed) {
    return;
  }

  router.put(
    route('stock-receptions.collect', props.reception.id),
    {
      collection_notes: notes.value,
      items: lines.map((line) => ({
        id: line.id,
        quantity_collected: Number(line.quantity_collected) || 0,
        note: line.note || null,
      })),
    },
    {
      onStart: () => {
        processing.value = true;
        errors.value = {};
      },
      onError: (bag) => {
        errors.value = bag;
      },
      onFinish: () => {
        processing.value = false;
      },
    }
  );
};
</script>

<template>
  <BCard no-body class="border border-info border-opacity-25">
    <BCardHeader class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="card-title mb-1">{{ $t('stock.receptions.collection_form.title') }}</h5>
        <p class="text-muted fs-13 mb-0">{{ $t('stock.receptions.collection_form.hint') }}</p>
      </div>
      <BButton
        variant="soft-secondary"
        size="sm"
        :title="$t('stock.receptions.collection_form.match_all_hint')"
        @click="matchAll"
      >
        <i class="ri-check-double-line align-bottom me-1"></i>
        {{ $t('stock.receptions.collection_form.match_all') }}
      </BButton>
    </BCardHeader>

    <BCardBody>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ $t('stock.receptions.columns.product') }}</th>
              <th class="text-end" style="width: 100px">{{ $t('stock.receptions.columns.sent') }}</th>
              <th class="text-center" style="width: 130px">
                {{ $t('stock.receptions.collection_form.quantity_collected') }}
              </th>
              <th class="text-center" style="width: 90px">
                {{ $t('stock.receptions.columns.collection_gap') }}
              </th>
              <th>{{ $t('stock.receptions.columns.note') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, position) in lines" :key="line.id">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <ProductThumb
                    :name="line.name"
                    :photo-url="line.photo_url"
                    :initials="line.initials"
                    :size="36"
                  />
                  <div class="min-w-0">
                    <span class="d-block fw-medium text-truncate">{{ line.name }}</span>
                    <span class="d-block text-muted fs-12">{{ line.sku }}</span>
                  </div>
                </div>
              </td>
              <td class="text-end text-muted">{{ line.quantity_sent }}</td>
              <td>
                <input
                  v-model="line.quantity_collected"
                  type="number"
                  min="0"
                  step="1"
                  inputmode="numeric"
                  class="form-control form-control-sm text-center"
                  :class="{ 'is-invalid': lineError(position, 'quantity_collected') }"
                />
                <InputError :message="lineError(position, 'quantity_collected')" />
              </td>
              <td class="text-center">
                <span
                  class="badge"
                  :class="gap(line) === 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
                >
                  {{ gap(line) > 0 ? `+${gap(line)}` : gap(line) }}
                </span>
              </td>
              <td>
                <input
                  v-model="line.note"
                  type="text"
                  class="form-control form-control-sm"
                  :placeholder="$t('stock.receptions.columns.note')"
                />
              </td>
            </tr>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <th>{{ $t('stock.receptions.sections.summary') }}</th>
              <th class="text-end">{{ totalDeclared }}</th>
              <th class="text-center text-info">{{ totalCollected }}</th>
              <th colspan="2"></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="mt-3">
        <label class="form-label" for="collection-notes">
          {{ $t('stock.receptions.collection_form.collection_notes') }}
        </label>
        <textarea
          id="collection-notes"
          v-model="notes"
          class="form-control"
          rows="2"
          :class="{ 'is-invalid': errors.collection_notes }"
          :placeholder="$t('stock.receptions.collection_form.collection_notes_placeholder')"
        ></textarea>
        <InputError :message="errors.collection_notes" />
      </div>

      <InputError :message="errors.items" class="mt-2" />
      <InputError :message="errors.status" class="mt-2" />

      <div class="text-end mt-3">
        <BButton variant="info" :disabled="processing" @click="submit">
          <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="ri-truck-line align-bottom me-1"></i>
          {{ $t('stock.receptions.collection_form.confirm') }}
        </BButton>
      </div>
    </BCardBody>
  </BCard>
</template>

<style scoped>
.min-w-0 {
  min-width: 0;
}
</style>
