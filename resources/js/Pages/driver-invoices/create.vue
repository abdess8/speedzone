<script setup>
import { ref, computed } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import axios from "axios";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import InputError from "@/Components/InputError.vue";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";

const { t } = useI18n();

const props = defineProps({
  drivers: { type: Array, default: () => [] },
});

const form = useForm({
  driver_id: "",
  period_start: "",
  period_end: "",
});

const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref("");

const driverOptions = computed(() =>
  props.drivers.map((d) => ({ value: d.id, label: `${d.name} (${d.email})` }))
);

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const hasTransactions = computed(() => (preview.value?.summary?.transactions_count ?? 0) > 0);

const loadPreview = async () => {
  if (!form.driver_id) {
    previewError.value = t("driver_invoices.create.select_driver_first");
    preview.value = null;
    return;
  }
  previewLoading.value = true;
  previewError.value = "";
  try {
    const { data } = await axios.post(route("driver-invoices.preview"), {
      driver_id: form.driver_id,
      period_start: form.period_start || null,
      period_end: form.period_end || null,
    });
    preview.value = data;
  } catch (e) {
    previewError.value = e?.response?.data?.message ?? "Error";
    preview.value = null;
  } finally {
    previewLoading.value = false;
  }
};

const submit = () => {
  form.post(route("driver-invoices.store"));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('driver_invoices.create.title')" :pageTitle="$t('driver_invoices.create.page_title')" />

    <BRow>
      <BCol lg="4">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('driver_invoices.create.title') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="mb-3">
              <label class="form-label">{{ $t('driver_invoices.create.select_driver') }} <span class="text-danger">*</span></label>
              <Multiselect
                v-model="form.driver_id"
                :options="driverOptions"
                :searchable="true"
                :close-on-select="true"
                :placeholder="$t('driver_invoices.create.driver_placeholder')"
                :class="{ 'is-invalid': form.errors.driver_id }"
                @change="preview = null"
              />
              <InputError :message="form.errors.driver_id" />
            </div>

            <label class="form-label">{{ $t('driver_invoices.create.period') }}</label>
            <p class="text-muted fs-12">{{ $t('driver_invoices.create.period_hint') }}</p>
            <BRow class="g-2 mb-3">
              <BCol cols="6">
                <label class="form-label fs-12">{{ $t('driver_invoices.create.period_start') }}</label>
                <input type="date" class="form-control" v-model="form.period_start" />
              </BCol>
              <BCol cols="6">
                <label class="form-label fs-12">{{ $t('driver_invoices.create.period_end') }}</label>
                <input type="date" class="form-control" v-model="form.period_end" :class="{ 'is-invalid': form.errors.period_end }" />
                <InputError :message="form.errors.period_end" />
              </BCol>
            </BRow>

            <div class="d-grid gap-2">
              <BButton variant="primary" :disabled="previewLoading || !form.driver_id" @click="loadPreview">
                <span v-if="previewLoading" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ri-eye-line align-bottom me-1"></i>
                {{ $t('driver_invoices.create.preview') }}
              </BButton>
              <Link :href="route('driver-invoices.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            </div>
            <div v-if="previewError" class="alert alert-warning mt-3 mb-0">{{ previewError }}</div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="8">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('driver_invoices.create.preview_title') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div v-if="!preview" class="text-center text-muted py-5">
              <i class="ri-file-list-3-line fs-48 d-block mb-2"></i>
              {{ $t('driver_invoices.create.select_driver_first') }}
            </div>

            <template v-else>
              <div v-if="!hasTransactions" class="alert alert-warning">{{ $t('driver_invoices.create.no_transactions') }}</div>

              <template v-else>
                <BRow class="g-3 mb-3">
                  <BCol md="3" cols="6">
                    <p class="text-muted mb-1">{{ $t('driver_invoices.summary.deliveries') }}</p>
                    <h5 class="mb-0">{{ preview.summary.deliveries_count }}</h5>
                  </BCol>
                  <BCol md="3" cols="6">
                    <p class="text-muted mb-1">{{ $t('driver_invoices.summary.bonus_total') }}</p>
                    <h5 class="mb-0 text-primary">{{ money(preview.summary.bonus_total) }}</h5>
                  </BCol>
                  <BCol md="3" cols="6">
                    <p class="text-muted mb-1">{{ $t('driver_invoices.summary.penalty_total') }}</p>
                    <h5 class="mb-0 text-danger">- {{ money(preview.summary.penalty_total) }}</h5>
                  </BCol>
                  <BCol md="3" cols="6">
                    <p class="text-muted mb-1">{{ $t('driver_invoices.summary.total') }}</p>
                    <h4 class="mb-0 text-success">{{ money(preview.summary.total_amount) }}</h4>
                  </BCol>
                </BRow>

                <div class="table-responsive table-card">
                  <table class="table align-middle table-nowrap mb-0">
                    <thead class="table-light text-muted">
                      <tr>
                        <th>{{ $t('driver_invoices.columns.order') }}</th>
                        <th>{{ $t('driver_invoices.columns.customer') }}</th>
                        <th>{{ $t('driver_invoices.columns.sector') }}</th>
                        <th>{{ $t('driver_invoices.columns.type') }}</th>
                        <th class="text-end">{{ $t('driver_invoices.columns.amount') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="line in preview.lines" :key="line.id">
                        <td class="fw-semibold">{{ line.tracking_number ?? "—" }}</td>
                        <td>{{ line.customer_full_name ?? "—" }}</td>
                        <td>{{ line.sector ?? "—" }}</td>
                        <td><span class="badge bg-info-subtle text-info">{{ line.transaction_type_label }}</span></td>
                        <td class="text-end fw-semibold" :class="line.amount < 0 ? 'text-danger' : ''">{{ money(line.amount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="hstack gap-2 justify-content-end mt-3">
                  <BButton variant="success" :disabled="form.processing" @click="submit">
                    <i class="ri-check-double-line align-bottom me-1"></i> {{ $t('driver_invoices.create.confirm_generate') }}
                  </BButton>
                </div>
              </template>
            </template>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
