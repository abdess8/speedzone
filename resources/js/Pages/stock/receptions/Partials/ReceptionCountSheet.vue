<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import InputError from '@/Components/InputError.vue';
import ProductThumb from '../../Partials/ProductThumb.vue';

/**
 * What the depot signs for.
 *
 * Only the figures typed here are credited to the catalog, so the agent has to
 * answer every line before the document can be closed.
 *
 * The column he is compared against is the collector's count, not the vendor's
 * declaration: the collector is the last person to have seen the goods, and
 * measuring the depot against a figure already superseded at the shop door would
 * flag a shortage the shop already owned up to.
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
    quantity_sent: item.baseline_quantity ?? item.quantity_sent,
    quantity_received: item.quantity_received ?? item.baseline_quantity ?? item.quantity_sent,
    quantity_rejected: item.quantity_rejected ?? 0,
    note: item.note ?? '',
  }))
);

/** Whether a collector counted the goods, which changes what the table compares to. */
const wasCollected = computed(() => props.reception.totals.collected !== null);

const receivedAt = ref(props.reception.received_at ?? new Date().toISOString().slice(0, 10));
const notes = ref(props.reception.reception_notes ?? '');
const errors = ref({});
const processing = ref(false);

const totalReceived = computed(() =>
  lines.reduce((total, line) => total + (Number(line.quantity_received) || 0), 0)
);

const totalRejected = computed(() =>
  lines.reduce((total, line) => total + (Number(line.quantity_rejected) || 0), 0)
);

const totalSent = computed(() => lines.reduce((total, line) => total + line.quantity_sent, 0));

/** Signed gap between what was declared and what the agent accounted for. */
const discrepancy = (line) =>
  (Number(line.quantity_received) || 0) + (Number(line.quantity_rejected) || 0) - line.quantity_sent;

const matchAll = () => {
  lines.forEach((line) => {
    line.quantity_received = line.quantity_sent;
    line.quantity_rejected = 0;
  });
};

const lineError = (position, field) => errors.value[`items.${position}.${field}`] ?? '';

const submit = async () => {
  const confirmed = await Swal.fire({
    title: t('stock.receptions.reception_form.confirm_title'),
    text: t('stock.receptions.reception_form.confirm_text', { units: totalReceived.value }),
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: t('stock.receptions.reception_form.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-success', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (!confirmed.isConfirmed) {
    return;
  }

  router.put(
    route('stock-receptions.validate', props.reception.id),
    {
      received_at: receivedAt.value,
      reception_notes: notes.value,
      items: lines.map((line) => ({
        id: line.id,
        quantity_received: Number(line.quantity_received) || 0,
        quantity_rejected: Number(line.quantity_rejected) || 0,
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
  <BCard no-body class="border border-success border-opacity-25">
    <BCardHeader class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="card-title mb-1">{{ $t('stock.receptions.reception_form.title') }}</h5>
        <p class="text-muted fs-13 mb-0">{{ $t('stock.receptions.reception_form.hint') }}</p>
      </div>
      <BButton
        variant="soft-secondary"
        size="sm"
        :title="$t('stock.receptions.reception_form.match_all_hint')"
        @click="matchAll"
      >
        <i class="ri-check-double-line align-bottom me-1"></i>
        {{ $t('stock.receptions.reception_form.match_all') }}
      </BButton>
    </BCardHeader>

    <BCardBody>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ $t('stock.receptions.columns.product') }}</th>
              <th class="text-end" style="width: 100px">
                {{ wasCollected ? $t('stock.receptions.columns.collected') : $t('stock.receptions.columns.sent') }}
              </th>
              <th class="text-center" style="width: 130px">
                {{ $t('stock.receptions.reception_form.quantity_received') }}
              </th>
              <th class="text-center" style="width: 130px">
                {{ $t('stock.receptions.reception_form.quantity_rejected') }}
              </th>
              <th class="text-center" style="width: 90px">{{ $t('stock.receptions.columns.discrepancy') }}</th>
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
                  v-model="line.quantity_received"
                  type="number"
                  min="0"
                  step="1"
                  inputmode="numeric"
                  class="form-control form-control-sm text-center"
                  :class="{ 'is-invalid': lineError(position, 'quantity_received') }"
                />
                <InputError :message="lineError(position, 'quantity_received')" />
              </td>
              <td>
                <input
                  v-model="line.quantity_rejected"
                  type="number"
                  min="0"
                  step="1"
                  inputmode="numeric"
                  class="form-control form-control-sm text-center"
                  :class="{ 'is-invalid': lineError(position, 'quantity_rejected') }"
                />
                <InputError :message="lineError(position, 'quantity_rejected')" />
              </td>
              <td class="text-center">
                <span
                  class="badge"
                  :class="
                    discrepancy(line) === 0
                      ? 'bg-success-subtle text-success'
                      : 'bg-danger-subtle text-danger'
                  "
                >
                  {{ discrepancy(line) > 0 ? `+${discrepancy(line)}` : discrepancy(line) }}
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
              <th class="text-end">{{ totalSent }}</th>
              <th class="text-center text-success">{{ totalReceived }}</th>
              <th class="text-center text-danger">{{ totalRejected }}</th>
              <th colspan="2"></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <BRow class="mt-3">
        <BCol md="4">
          <label class="form-label" for="reception-received-at">
            {{ $t('stock.receptions.reception_form.received_at') }}
          </label>
          <input
            id="reception-received-at"
            v-model="receivedAt"
            type="date"
            class="form-control"
            :class="{ 'is-invalid': errors.received_at }"
          />
          <InputError :message="errors.received_at" />
        </BCol>
        <BCol md="8">
          <label class="form-label" for="reception-post-notes">
            {{ $t('stock.receptions.reception_form.reception_notes') }}
          </label>
          <textarea
            id="reception-post-notes"
            v-model="notes"
            class="form-control"
            rows="2"
            :class="{ 'is-invalid': errors.reception_notes }"
            :placeholder="$t('stock.receptions.reception_form.reception_notes_placeholder')"
          ></textarea>
          <InputError :message="errors.reception_notes" />
        </BCol>
      </BRow>

      <InputError :message="errors.items" class="mt-2" />
      <InputError :message="errors.status" class="mt-2" />

      <div class="text-end mt-3">
        <BButton variant="success" :disabled="processing" @click="submit">
          <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="ri-inbox-archive-line align-bottom me-1"></i>
          {{ $t('stock.receptions.reception_form.confirm') }}
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
