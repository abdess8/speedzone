<script setup>
import { onMounted, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import ReturnTimeline from "./Partials/ReturnTimeline.vue";
import ReturnQrScanner from "./Partials/ReturnQrScanner.vue";
import UserAvatar from "@/Components/UserAvatar.vue";
import EntityLink from "@/Components/EntityLink.vue";
import PaymentMethodBadge from "@/Components/PaymentMethodBadge.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  orderReturn: { type: Object, required: true },
  allowedTransitions: { type: Array, default: () => [] },
  qrCode: { type: String, default: null },
  cities: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const showQrScanner = ref(false);
const editingCustomer = ref(false);

const customerForm = useForm({
  updated_customer_name: props.orderReturn.updated_customer_name ?? props.orderReturn.order?.customer?.full_name ?? "",
  updated_customer_phone: props.orderReturn.updated_customer_phone ?? props.orderReturn.order?.customer?.phone ?? "",
  updated_address: props.orderReturn.updated_address ?? props.orderReturn.order?.customer?.address ?? "",
  updated_city_id: props.orderReturn.updated_city_id ?? props.orderReturn.order?.city_id ?? "",
});

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t("common.empty_value"));
const empty = () => t("common.empty_value");

const receiveAtHub = () => {
  Swal.fire({
    title: t("returns.swal.receive_at_hub_confirm"),
    input: "textarea",
    inputLabel: t("returns.swal.optional_comment"),
    showCancelButton: true,
    confirmButtonText: t("returns.swal.confirm"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (!result.isConfirmed) return;
    router.post(route("returns.receive-at-hub", props.orderReturn.id), { comment: result.value }, { preserveScroll: true });
  });
};

const changeStatus = (status) => {
  Swal.fire({
    title: t("returns.swal.status_confirm", { label: status.label }),
    input: "textarea",
    inputLabel: t("returns.swal.optional_comment"),
    showCancelButton: true,
    confirmButtonText: t("returns.swal.confirm"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (!result.isConfirmed) return;
    router.post(
      route("returns.change-status", props.orderReturn.id),
      { status: status.value, comment: result.value },
      { preserveScroll: true }
    );
  });
};

const saveCustomerData = () => {
  customerForm.put(route("returns.update-customer-data", props.orderReturn.id), {
    preserveScroll: true,
    onSuccess: () => {
      editingCustomer.value = false;
      Swal.fire({ toast: true, position: "top-end", icon: "success", title: t("returns.swal.customer_saved"), timer: 2500, showConfirmButton: false });
    },
  });
};

onMounted(() => {
  const success = usePage().props?.flash?.success;
  if (success) {
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: success, showConfirmButton: false, timer: 3000 });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="orderReturn.reference" :pageTitle="$t('returns.page_title')" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge fs-13" :class="`bg-${orderReturn.status_color}-subtle text-${orderReturn.status_color}`">
          {{ orderReturn.status_label }}
        </span>
        <div class="vr"></div>

        <!-- Labels collapse below `sm`; `title` still names the icon-only button. -->
        <button
          v-if="can.update_status && orderReturn.status === 'CREATED'"
          class="btn btn-sm btn-primary"
          :title="$t('returns.show.receive_at_hub')"
          @click="receiveAtHub"
        >
          <i class="ri-store-3-line align-bottom"></i>
          <span class="d-none d-sm-inline ms-1">{{ $t('returns.show.receive_at_hub') }}</span>
        </button>

        <div class="dropdown" v-if="allowedTransitions.length">
          <button class="btn btn-sm btn-soft-primary dropdown-toggle" data-bs-toggle="dropdown" :title="$t('orders.actions.update_status')">
            <i class="ri-exchange-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('orders.actions.update_status') }}</span>
          </button>
          <ul class="dropdown-menu">
            <li v-for="trans in allowedTransitions" :key="trans.value">
              <button class="dropdown-item" @click="changeStatus(trans)">
                <i :class="`ri-circle-fill text-${trans.color} me-2 fs-10`"></i>{{ trans.label }}
              </button>
            </li>
          </ul>
        </div>

        <div class="ms-auto action-bar">
          <button v-if="can.scan" class="btn btn-sm btn-soft-primary" :title="$t('returns.qr_scan')" @click="showQrScanner = true">
            <i class="ri-qr-scan-2-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('returns.qr_scan') }}</span>
          </button>
          <Link :href="route('returns.index')" class="btn btn-sm btn-light" :title="$t('returns.show.back')">
            <i class="ri-arrow-left-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('returns.show.back') }}</span>
          </Link>
        </div>
      </BCardBody>
    </BCard>

    <BRow class="g-3">
      <BCol xl="4">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('returns.show.info') }}</h5></BCardHeader>
          <BCardBody>
            <dl class="row mb-0">
              <dt class="col-5 text-muted">{{ $t('returns.show.reference') }}</dt>
              <dd class="col-7 fw-medium">{{ orderReturn.reference }}</dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.status') }}</dt>
              <dd class="col-7">
                <span class="badge" :class="`bg-${orderReturn.status_color}-subtle text-${orderReturn.status_color}`">{{ orderReturn.status_label }}</span>
              </dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.reason') }}</dt>
              <dd class="col-7">{{ orderReturn.reason_label }}</dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.initiated_by') }}</dt>
              <dd class="col-7">{{ orderReturn.initiated_by_role_label }}</dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.created_by') }}</dt>
              <dd class="col-7">
                <UserAvatar v-if="orderReturn.creator" :user="orderReturn.creator" :size="24" clickable show-name show-role />
                <span v-else>{{ empty() }}</span>
              </dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.current_city') }}</dt>
              <dd class="col-7">{{ orderReturn.current_location_city?.name || empty() }}</dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.creation_date') }}</dt>
              <dd class="col-7">{{ formatDate(orderReturn.created_at) }}</dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.notes') }}</dt>
              <dd class="col-7">{{ orderReturn.return_notes || empty() }}</dd>
            </dl>

            <div v-if="qrCode" class="mt-3 text-center">
              <p class="text-muted small mb-2">{{ $t('returns.show.qr_hint') }}</p>
              <img :src="qrCode" :alt="$t('returns.show.qr_code')" class="img-fluid" style="max-width: 180px" />
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="4">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('returns.show.order_info') }}</h5></BCardHeader>
          <BCardBody v-if="orderReturn.order">
            <dl class="row mb-0">
              <dt class="col-5 text-muted">{{ $t('returns.show.tracking_number') }}</dt>
              <dd class="col-7"><EntityLink type="order" :entity="orderReturn.order" /></dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.payment_method') }}</dt>
              <dd class="col-7"><PaymentMethodBadge :method="orderReturn.order.payment_method" /></dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.seller') }}</dt>
              <dd class="col-7">
                <UserAvatar v-if="orderReturn.order.seller" :user="orderReturn.order.seller" :size="24" clickable show-name show-role />
              </dd>
              <dt class="col-5 text-muted">{{ $t('returns.show.city') }}</dt>
              <dd class="col-7">{{ orderReturn.order.city?.name || empty() }}</dd>
            </dl>
          </BCardBody>
        </BCard>

        <BCard no-body class="mt-3">
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">{{ $t('returns.show.customer_info') }}</h5>
            <button
              v-if="can.edit_customer_data && orderReturn.can_edit_customer_data && !editingCustomer"
              class="btn btn-sm btn-soft-primary"
              @click="editingCustomer = true"
            >
              {{ $t('returns.show.edit_customer') }}
            </button>
          </BCardHeader>
          <BCardBody>
            <div class="mb-3">
              <h6 class="text-muted fs-12 text-uppercase">{{ $t('returns.show.original_customer') }}</h6>
              <p class="mb-1">{{ orderReturn.order?.customer?.full_name }}</p>
              <p class="mb-1 text-muted">{{ orderReturn.order?.customer?.phone }}</p>
              <p class="mb-0 text-muted">{{ orderReturn.order?.customer?.address }}</p>
            </div>

            <hr />

            <div v-if="editingCustomer">
              <form @submit.prevent="saveCustomerData">
                <div class="mb-2">
                  <label class="form-label">{{ $t('returns.show.name') }}</label>
                  <input v-model="customerForm.updated_customer_name" type="text" class="form-control" />
                </div>
                <div class="mb-2">
                  <label class="form-label">{{ $t('returns.show.phone') }}</label>
                  <input v-model="customerForm.updated_customer_phone" type="text" class="form-control" />
                </div>
                <div class="mb-2">
                  <label class="form-label">{{ $t('returns.show.address') }}</label>
                  <textarea v-model="customerForm.updated_address" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ $t('returns.show.city') }}</label>
                  <select v-model="customerForm.updated_city_id" class="form-select">
                    <option value="">{{ $t('returns.show.select_city') }}</option>
                    <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
                  </select>
                </div>
                <div class="hstack gap-2">
                  <button type="submit" class="btn btn-primary btn-sm" :disabled="customerForm.processing">{{ $t('returns.show.save_customer') }}</button>
                  <button type="button" class="btn btn-light btn-sm" @click="editingCustomer = false">{{ $t('common.cancel') }}</button>
                </div>
              </form>
            </div>
            <div v-else>
              <h6 class="text-muted fs-12 text-uppercase">{{ $t('returns.show.effective_customer') }}</h6>
              <p class="mb-1">{{ orderReturn.effective_customer_name }}</p>
              <p class="mb-1 text-muted">{{ orderReturn.effective_customer_phone }}</p>
              <p class="mb-0 text-muted">{{ orderReturn.effective_address }}</p>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="4">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('returns.show.status_history') }}</h5></BCardHeader>
          <BCardBody>
            <ReturnTimeline :history="orderReturn.status_history ?? []" />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <ReturnQrScanner :show="showQrScanner" @close="showQrScanner = false" />
  </Layout>
</template>
