<script setup>
import { ref, computed, onMounted } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import BottomSheet from "@/Components/BottomSheet.vue";
import InputError from "@/Components/InputError.vue";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  preview: { type: Object, default: () => ({ summary: {}, lines: [] }) },
  driverId: { type: Number, default: null },
  drivers: { type: Array, default: () => [] },
  billing: { type: Object, default: () => ({}) },
  transactionTypes: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const selectedDriver = ref(props.driverId);
/** Line whose mobile detail sheet is open. */
const selectedLine = ref(null);

const driverOptions = computed(() =>
  props.drivers.map((d) => ({ value: d.id, label: `${d.name} (${d.email})` }))
);

const isAdmin = computed(() => (props.drivers ?? []).length > 0);

const lines = computed(() => props.preview?.lines ?? []);
const summary = computed(() => props.preview?.summary ?? {});

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : null);

const changeDriver = (value) => {
  router.get(route("driver-invoices.pending"), value ? { driver_id: value } : {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const activeFilterCount = computed(() => (selectedDriver.value ? 1 : 0));

const applyFilters = () => changeDriver(selectedDriver.value);

const resetFilters = () => {
  selectedDriver.value = null;
  changeDriver(null);
};

// A manual entry has no order behind it, so it identifies itself by its type.
const lineTitle = (line) => line.tracking_number ?? line.transaction_type_label;

const lineSubtitle = (line) => line.customer_full_name ?? line.note ?? "—";

const cardRows = (line) => [
  { label: t("driver_invoices.columns.sector"), value: line.sector },
  { label: t("driver_invoices.columns.amount"), value: money(line.amount), emphasis: true },
];

const sheetRows = (line) => [
  ...cardRows(line),
  { label: t("driver_invoices.columns.note"), value: line.note },
];

/* --- Manual ledger entries (bonus / penalty / adjustment) --- */

/**
 * The page falls back to the viewer's own ledger when no driver is filtered, so
 * only offer the form once a real driver from the list is on screen.
 */
const targetDriver = computed(
  () => (props.drivers ?? []).find((d) => d.id === props.driverId) ?? null
);

const canAdjust = computed(() => Boolean(props.can?.adjust && targetDriver.value));

const showTransactionSheet = ref(false);

const transactionForm = useForm({
  driver_id: null,
  transaction_type: "BONUS",
  amount: "",
  note: "",
});

const openTransactionSheet = () => {
  transactionForm.reset();
  transactionForm.clearErrors();
  transactionForm.driver_id = targetDriver.value?.id ?? null;
  showTransactionSheet.value = true;
};

const submitTransaction = () => {
  transactionForm.post(route("driver-transactions.store"), {
    preserveScroll: true,
    onSuccess: () => {
      showTransactionSheet.value = false;
      transactionForm.reset();
    },
  });
};

/** A delivery payment mirrors a delivered order and is never removable here. */
const isManualLine = (line) => line.transaction_type !== "DELIVERY_PAYMENT";

const deleteTransaction = (line) => {
  selectedLine.value = null;

  Swal.fire({
    title: t("driver_invoices.confirms.delete_transaction_title"),
    text: t("driver_invoices.confirms.delete_transaction_text"),
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: t("driver_invoices.confirms.confirm"),
    cancelButtonText: t("common.cancel"),
    confirmButtonColor: "#f06548",
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route("driver-transactions.destroy", line.id), { preserveScroll: true });
    }
  });
};

onMounted(() => {
  const flash = usePage().props?.flash ?? {};
  if (flash.success) {
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: flash.success, showConfirmButton: false, timer: 3000, timerProgressBar: true });
  }
  if (flash.error) {
    Swal.fire({ toast: true, position: "top-end", icon: "error", title: flash.error, showConfirmButton: false, timer: 4000, timerProgressBar: true });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('driver_invoices.pending.title')" :pageTitle="$t('driver_invoices.pending.page_title')" />

    <BRow>
      <BCol lg="8">
        <BCard no-body>
          <FilterPanel
            v-if="isAdmin"
            :active-count="activeFilterCount"
            @apply="applyFilters"
            @reset="resetFilters"
          >
            <template #title>
              <div class="d-flex align-items-center gap-2">
                <h5 class="card-title mb-0">{{ $t('driver_invoices.pending.orders_title') }}</h5>
                <span class="badge bg-primary-subtle text-primary">{{ summary.transactions_count ?? 0 }}</span>
              </div>
            </template>

            <template v-if="canAdjust" #actions>
              <button type="button" class="btn btn-soft-success text-nowrap" @click="openTransactionSheet">
                <i class="ri-add-line align-bottom me-1"></i>
                <span class="d-none d-sm-inline">{{ $t('driver_invoices.transactions.add') }}</span>
              </button>
            </template>

            <BCol md="6">
              <label class="form-label">{{ $t('driver_invoices.filters.driver') }}</label>
              <Multiselect
                v-model="selectedDriver"
                :options="driverOptions"
                :searchable="true"
                :close-on-select="true"
                :placeholder="$t('driver_invoices.create.driver_placeholder')"
              />
            </BCol>
          </FilterPanel>

          <BCardHeader v-else class="d-flex align-items-center">
            <h5 class="card-title mb-0 flex-grow-1">{{ $t('driver_invoices.pending.orders_title') }}</h5>
            <span class="badge bg-primary-subtle text-primary">{{ summary.transactions_count ?? 0 }}</span>
          </BCardHeader>

          <BCardBody>
            <BRow v-if="(summary.transactions_count ?? 0) > 0" class="g-3 mb-3">
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('driver_invoices.summary.deliveries') }}</p>
                <h5 class="mb-0">{{ summary.deliveries_count }}</h5>
              </BCol>
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('driver_invoices.summary.bonus_total') }}</p>
                <h5 class="mb-0 text-primary">{{ money(summary.bonus_total) }}</h5>
              </BCol>
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('driver_invoices.summary.penalty_total') }}</p>
                <h5 class="mb-0 text-danger">- {{ money(summary.penalty_total) }}</h5>
              </BCol>
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('driver_invoices.summary.total') }}</p>
                <h4 class="mb-0 text-success">{{ money(summary.total_amount) }}</h4>
              </BCol>
            </BRow>

            <div class="d-lg-none">
              <EntityCard
                v-for="line in lines"
                :key="line.id"
                :title="lineTitle(line)"
                :subtitle="lineSubtitle(line)"
                :status-label="line.transaction_type_label"
                status-color="info"
                :rows="cardRows(line)"
                @open="selectedLine = line"
              >
                <template v-if="canAdjust && isManualLine(line)" #actions>
                  <button class="btn btn-sm btn-soft-danger flex-fill" @click="deleteTransaction(line)">
                    <i class="ri-delete-bin-line align-bottom me-1"></i> {{ $t('driver_invoices.transactions.delete') }}
                  </button>
                </template>
              </EntityCard>
              <p v-if="lines.length === 0" class="text-center text-muted py-4 mb-0">
                {{ $t('driver_invoices.pending.empty') }}
              </p>
            </div>

            <div class="table-responsive table-card d-none d-lg-block">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('driver_invoices.columns.order') }}</th>
                    <th>{{ $t('driver_invoices.columns.customer') }}</th>
                    <th>{{ $t('driver_invoices.columns.sector') }}</th>
                    <th>{{ $t('driver_invoices.columns.type') }}</th>
                    <th class="text-end">{{ $t('driver_invoices.columns.amount') }}</th>
                    <th v-if="canAdjust" class="text-end">{{ $t('common.actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in lines" :key="line.id">
                    <td class="fw-semibold">{{ line.tracking_number ?? "—" }}</td>
                    <td>
                      <div>{{ line.customer_full_name ?? "—" }}</div>
                      <div v-if="line.note" class="text-muted fs-12">{{ line.note }}</div>
                    </td>
                    <td>{{ line.sector ?? "—" }}</td>
                    <td><span class="badge bg-info-subtle text-info">{{ line.transaction_type_label }}</span></td>
                    <td class="text-end fw-semibold" :class="line.amount < 0 ? 'text-danger' : ''">{{ money(line.amount) }}</td>
                    <td v-if="canAdjust" class="text-end">
                      <BButton
                        v-if="isManualLine(line)"
                        variant="ghost-danger"
                        size="sm"
                        :title="$t('driver_invoices.transactions.delete')"
                        @click="deleteTransaction(line)"
                      >
                        <i class="ri-delete-bin-line align-bottom"></i>
                      </BButton>
                    </td>
                  </tr>
                  <tr v-if="lines.length === 0">
                    <td :colspan="canAdjust ? 6 : 5" class="text-center text-muted py-4">{{ $t('driver_invoices.pending.empty') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="4">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('driver_invoices.pending.next_billing') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="text-center mb-3">
              <div class="avatar-sm mx-auto mb-2">
                <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-22">
                  <i class="ri-calendar-event-line"></i>
                </span>
              </div>
              <h4 class="mb-0">{{ formatDate(billing.next_billing_date) ?? $t('driver_invoices.pending.not_scheduled') }}</h4>
              <p class="text-muted mb-0">{{ $t('driver_invoices.pending.next_date') }}</p>
            </div>
            <hr />
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('driver_invoices.pending.frequency') }}</span>
              <span class="fw-medium">{{ billing.billing_frequency_label ?? "—" }}</span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="text-muted">{{ $t('driver_invoices.table.status') }}</span>
              <span v-if="billing.billing_enabled" class="badge bg-success-subtle text-success">
                {{ $t('driver_invoices.pending.auto_enabled') }}
              </span>
              <span v-else class="badge bg-secondary-subtle text-secondary">
                {{ $t('driver_invoices.pending.auto_disabled') }}
              </span>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <EntityDetailSheet
      :show="selectedLine !== null"
      :title="selectedLine ? lineTitle(selectedLine) : ''"
      :subtitle="selectedLine ? lineSubtitle(selectedLine) : ''"
      :status-label="selectedLine?.transaction_type_label ?? ''"
      status-color="info"
      :rows="selectedLine ? sheetRows(selectedLine) : []"
      @close="selectedLine = null"
    >
      <template v-if="canAdjust && selectedLine && isManualLine(selectedLine)" #actions>
        <button class="btn btn-danger flex-fill sheet-action" @click="deleteTransaction(selectedLine)">
          <i class="ri-delete-bin-line align-bottom me-1"></i> {{ $t('driver_invoices.transactions.delete') }}
        </button>
      </template>
    </EntityDetailSheet>

    <BottomSheet
      :show="showTransactionSheet"
      :title="$t('driver_invoices.transactions.add')"
      :subtitle="targetDriver?.name ?? ''"
      @close="showTransactionSheet = false"
    >
      <form @submit.prevent="submitTransaction">
        <div class="mb-3">
          <label class="form-label">{{ $t('driver_invoices.columns.type') }} <span class="text-danger">*</span></label>
          <select
            v-model="transactionForm.transaction_type"
            class="form-select"
            :class="{ 'is-invalid': transactionForm.errors.transaction_type }"
          >
            <option v-for="type in transactionTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
          <InputError :message="transactionForm.errors.transaction_type" />
        </div>

        <div class="mb-3">
          <label class="form-label">{{ $t('driver_invoices.columns.amount') }} <span class="text-danger">*</span></label>
          <input
            v-model="transactionForm.amount"
            type="number"
            step="0.01"
            min="0.01"
            class="form-control"
            :class="{ 'is-invalid': transactionForm.errors.amount }"
          />
          <div class="form-text">{{ $t('driver_invoices.transactions.amount_hint') }}</div>
          <InputError :message="transactionForm.errors.amount" />
        </div>

        <div class="mb-3">
          <label class="form-label">{{ $t('driver_invoices.columns.note') }}</label>
          <input
            v-model="transactionForm.note"
            type="text"
            maxlength="255"
            class="form-control"
            :placeholder="$t('driver_invoices.transactions.note_placeholder')"
            :class="{ 'is-invalid': transactionForm.errors.note }"
          />
          <InputError :message="transactionForm.errors.note" />
        </div>

        <InputError :message="transactionForm.errors.driver_id" />

        <div class="hstack gap-2 justify-content-end">
          <BButton variant="light" type="button" @click="showTransactionSheet = false">{{ $t('common.cancel') }}</BButton>
          <BButton variant="success" type="submit" :disabled="transactionForm.processing">
            <i class="ri-check-line align-bottom me-1"></i> {{ $t('driver_invoices.transactions.submit') }}
          </BButton>
        </div>
      </form>
    </BottomSheet>
  </Layout>
</template>
