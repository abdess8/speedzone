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
  sellers: { type: Array, default: () => [] },
});

const form = useForm({
  seller_id: "",
  period_start: "",
  period_end: "",
});

const preview = ref(null);
const previewLoading = ref(false);
const previewError = ref("");

const sellerOptions = computed(() =>
  props.sellers.map((s) => ({ value: s.id, label: `${s.name} (${s.email})` }))
);

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : "—");

const hasOrders = computed(() => (preview.value?.summary?.total_orders_count ?? 0) > 0);

const loadPreview = async () => {
  if (!form.seller_id) {
    previewError.value = t("invoices.create.select_seller_first");
    preview.value = null;
    return;
  }
  previewLoading.value = true;
  previewError.value = "";
  try {
    const { data } = await axios.post(route("invoices.preview"), {
      seller_id: form.seller_id,
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
  form.post(route("invoices.store"));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('invoices.create.title')" :pageTitle="$t('invoices.create.page_title')" />

    <BRow>
      <BCol lg="4">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('invoices.create.title') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="mb-3">
              <label class="form-label">{{ $t('invoices.create.select_seller') }} <span class="text-danger">*</span></label>
              <Multiselect
                v-model="form.seller_id"
                :options="sellerOptions"
                :searchable="true"
                :close-on-select="true"
                :placeholder="$t('invoices.create.seller_placeholder')"
                :class="{ 'is-invalid': form.errors.seller_id }"
                @change="preview = null"
              />
              <InputError :message="form.errors.seller_id" />
            </div>

            <label class="form-label">{{ $t('invoices.create.period') }}</label>
            <p class="text-muted fs-12">{{ $t('invoices.create.period_hint') }}</p>
            <BRow class="g-2 mb-3">
              <BCol cols="6">
                <label class="form-label fs-12">{{ $t('invoices.create.period_start') }}</label>
                <input type="date" class="form-control" v-model="form.period_start" />
              </BCol>
              <BCol cols="6">
                <label class="form-label fs-12">{{ $t('invoices.create.period_end') }}</label>
                <input type="date" class="form-control" v-model="form.period_end" :class="{ 'is-invalid': form.errors.period_end }" />
                <InputError :message="form.errors.period_end" />
              </BCol>
            </BRow>

            <div class="d-grid gap-2">
              <BButton variant="primary" :disabled="previewLoading || !form.seller_id" @click="loadPreview">
                <span v-if="previewLoading" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ri-eye-line align-bottom me-1"></i>
                {{ $t('invoices.create.preview') }}
              </BButton>
              <Link :href="route('invoices.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            </div>
            <div v-if="previewError" class="alert alert-warning mt-3 mb-0">{{ previewError }}</div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="8">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('invoices.create.preview_title') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div v-if="!preview" class="text-center text-muted py-5">
              <i class="ri-file-list-3-line fs-48 d-block mb-2"></i>
              {{ $t('invoices.create.select_seller_first') }}
            </div>

            <template v-else>
              <div v-if="!hasOrders" class="alert alert-warning">{{ $t('invoices.create.no_orders') }}</div>

              <template v-else>
                <BRow class="g-3 mb-3">
                  <BCol md="2" cols="4">
                    <p class="text-muted mb-1">{{ $t('invoices.summary.total_orders') }}</p>
                    <h5 class="mb-0">{{ preview.summary.total_orders_count }}</h5>
                  </BCol>
                  <BCol md="2" cols="4">
                    <p class="text-muted mb-1">{{ $t('invoices.summary.delivered') }}</p>
                    <h5 class="mb-0">{{ money(preview.summary.delivered_amount) }}</h5>
                  </BCol>
                  <BCol md="3" cols="4">
                    <p class="text-muted mb-1">{{ $t('invoices.summary.delivery_fees') }}</p>
                    <h5 class="mb-0 text-danger">- {{ money(preview.summary.delivery_fees_total) }}</h5>
                  </BCol>
                  <BCol md="2" cols="6">
                    <p class="text-muted mb-1">{{ $t('invoices.summary.return_fees') }}</p>
                    <h5 class="mb-0 text-danger">- {{ money(preview.summary.return_fees_total) }}</h5>
                  </BCol>
                  <BCol md="3" cols="6">
                    <p class="text-muted mb-1">{{ $t('invoices.summary.net') }}</p>
                    <h4 class="mb-0 text-primary">{{ money(preview.summary.net_amount) }}</h4>
                  </BCol>
                </BRow>

                <div class="table-responsive table-card">
                  <table class="table align-middle table-nowrap mb-0">
                    <thead class="table-light text-muted">
                      <tr>
                        <th>{{ $t('invoices.columns.order') }}</th>
                        <th>{{ $t('invoices.columns.customer') }}</th>
                        <th>{{ $t('invoices.columns.city') }}</th>
                        <th>{{ $t('invoices.columns.status') }}</th>
                        <th>{{ $t('invoices.columns.completed_on') }}</th>
                        <th class="text-end">{{ $t('invoices.columns.order_amount') }}</th>
                        <th class="text-end">{{ $t('invoices.columns.delivery_fee') }}</th>
                        <th class="text-end">{{ $t('invoices.columns.return_fee') }}</th>
                        <th class="text-end">{{ $t('invoices.columns.final_amount') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="line in preview.lines" :key="line.id">
                        <td class="fw-semibold">{{ line.tracking_number }}</td>
                        <td>{{ line.customer_full_name ?? "—" }}</td>
                        <td>{{ line.city ?? "—" }}</td>
                        <td>
                          <span class="badge" :class="line.status === 'RETURNED' ? 'bg-dark-subtle text-dark' : 'bg-success-subtle text-success'">
                            {{ line.status }}
                          </span>
                        </td>
                        <td>{{ formatDate(line.completed_at) }}</td>
                        <td class="text-end">{{ money(line.order_amount) }}</td>
                        <td class="text-end">{{ money(line.delivery_fee) }}</td>
                        <td class="text-end">{{ money(line.return_fee) }}</td>
                        <td class="text-end fw-semibold" :class="line.final_amount < 0 ? 'text-danger' : ''">{{ money(line.final_amount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="hstack gap-2 justify-content-end mt-3">
                  <BButton variant="success" :disabled="form.processing" @click="submit">
                    <i class="ri-check-double-line align-bottom me-1"></i> {{ $t('invoices.create.confirm_generate') }}
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
