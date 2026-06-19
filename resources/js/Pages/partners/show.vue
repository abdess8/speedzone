<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

const { t, locale } = useI18n();

const props = defineProps({
  partner: { type: Object, required: true },
  apiLogs: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  can: { type: Object, default: () => ({}) },
});

const page = usePage();
const testingConnection = ref(false);
const syncing = ref(false);
const expandedLogId = ref(null);

const formatDate = (value) => {
  if (!value) return t("common.empty_value");
  return new Date(value).toLocaleString(locale.value === "en" ? "en-GB" : "fr-FR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
};

const delegatedCities = computed(() => props.partner?.cities ?? []);
const delegatedSectors = computed(() => props.partner?.sectors ?? []);
const statusMappings = computed(() => props.partner?.status_mappings ?? []);
const fieldMappings = computed(() => props.partner?.field_mappings ?? []);
const assignedAdmins = computed(() => props.partner?.users ?? []);
const logRows = computed(() => props.apiLogs?.data ?? []);
const logMeta = computed(() => props.apiLogs?.meta ?? {});
const ordersUrl = computed(() => route("partner-orders.index", { partner_id: props.partner.id }));

const toggleLog = (id) => {
  expandedLogId.value = expandedLogId.value === id ? null : id;
};

const goToLogPage = (url) => {
  if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

const flashToast = () => {
  const flash = page.props?.flash ?? {};
  if (flash.success) {
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: flash.success, showConfirmButton: false, timer: 3000 });
  }
  if (flash.error) {
    Swal.fire({ toast: true, position: "top-end", icon: "error", title: flash.error, showConfirmButton: false, timer: 4000 });
  }
};

watch(() => page.props?.flash, flashToast, { deep: true, immediate: true });

const confirmDelete = () => {
  Swal.fire({
    title: t("partners.delete_confirm_title"),
    text: t("partners.delete_confirm_text", { name: props.partner.name }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: t("common.confirm_delete"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (result.isConfirmed) router.delete(route("partners.destroy", props.partner.id));
  });
};

const testConnection = async () => {
  testingConnection.value = true;
  try {
    const { data } = await axios.post(route("partners.test-connection", props.partner.id), {
      api_base_url: props.partner.api_base_url,
      auth_type: props.partner.auth_type,
      client_id: props.partner.client_id,
      endpoint_statuses: props.partner.endpoint_statuses,
      endpoint_login: props.partner.endpoint_login,
      api_key_header: props.partner.api_key_header,
      login_username_field: props.partner.login_username_field,
      login_password_field: props.partner.login_password_field,
      login_token_field: props.partner.login_token_field,
      endpoint_deliveries: props.partner.endpoint_deliveries,
    });

    const loginInfo = data.login?.cached === false
      ? t("partners.connection.login_obtained")
      : data.login?.cached
        ? t("partners.connection.login_cached")
        : "";

    Swal.fire({
      icon: "success",
      title: t("partners.connection.success"),
      text: [data.message, loginInfo].filter(Boolean).join(" "),
      timer: 3500,
      showConfirmButton: false,
    });

    router.reload({ only: ["partner", "apiLogs"] });
  } catch (error) {
    const message = error.response?.data?.message ?? t("partners.connection.failed");
    Swal.fire({ icon: "error", title: t("partners.connection.failed"), text: message });
  } finally {
    testingConnection.value = false;
  }
};

const syncPartner = async () => {
  syncing.value = true;
  try {
    const { data } = await axios.post(route("partners.sync", props.partner.id));
    Swal.fire({
      icon: "success",
      title: t("partners.sync.done"),
      text: data.message,
      timer: 4000,
      showConfirmButton: false,
    });
    router.reload({ only: ["partner", "apiLogs"] });
  } catch (error) {
    const message = error.response?.data?.message ?? t("partners.sync.failed");
    Swal.fire({ icon: "error", title: t("partners.sync.failed"), text: message });
  } finally {
    syncing.value = false;
  }
};
</script>

<template>
  <Layout>
    <PageHeader :title="partner.name" :pageTitle="$t('partners.title')" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <img v-if="partner.logo_url" :src="partner.logo_url" alt="" width="32" height="32" class="rounded" />
        <span class="badge fs-13" :class="partner.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
          {{ partner.is_active ? $t('common.active') : $t('common.inactive') }}
        </span>
        <span v-if="partner.auth_type_label" class="badge bg-light text-body border fs-13">{{ partner.auth_type_label }}</span>
        <span class="text-muted fs-13">{{ partner.orders_count ?? 0 }} {{ $t('partners.table.orders').toLowerCase() }}</span>
        <div class="ms-auto hstack gap-2">
          <Link :href="route('partners.index')" class="btn btn-sm btn-light">
            <i class="ri-arrow-left-line align-bottom me-1"></i> {{ $t('common.back') }}
          </Link>
          <button v-if="can.test_connection" type="button" class="btn btn-sm btn-soft-info" :disabled="testingConnection" @click="testConnection">
            <span v-if="testingConnection" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="ri-link-m align-bottom me-1"></i>
            {{ testingConnection ? $t('partners.connection.testing') : $t('partners.connection.test_button') }}
          </button>
          <button v-if="can.sync" type="button" class="btn btn-sm btn-primary" :disabled="syncing" @click="syncPartner">
            <span v-if="syncing" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="ri-refresh-line align-bottom me-1"></i>
            {{ syncing ? $t('partners.sync.running') : $t('partners.sync.button') }}
          </button>
          <Link v-if="can.view_orders" :href="ordersUrl" class="btn btn-sm btn-soft-primary">
            <i class="ri-shopping-basket-2-line align-bottom me-1"></i> {{ $t('partners.show.view_orders') }}
          </Link>
          <Link v-if="can.update" :href="route('partners.edit', partner.id)" class="btn btn-sm btn-soft-warning">
            <i class="ri-pencil-line align-bottom me-1"></i> {{ $t('common.edit') }}
          </Link>
          <button v-if="can.delete" class="btn btn-sm btn-soft-danger" @click="confirmDelete">
            <i class="ri-delete-bin-line align-bottom me-1"></i> {{ $t('common.delete') }}
          </button>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="4">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('partners.form.info') }}</h5></BCardHeader>
          <BCardBody>
            <div class="mb-3">
              <div class="text-muted fs-13">{{ $t('partners.table.name') }}</div>
              <div class="fw-semibold">{{ partner.name }}</div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">{{ $t('partners.form.reception_city') }}</div>
              <div class="fw-semibold">{{ partner.reception_city || $t('common.empty_value') }}</div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">{{ $t('partners.show.token_status') }}</div>
              <div class="fw-semibold">
                <span v-if="partner.has_access_token" class="badge bg-success-subtle text-success">
                  {{ $t('partners.show.token_active') }}
                </span>
                <span v-else class="text-muted">{{ $t('partners.show.token_none') }}</span>
                <div v-if="partner.token_expires_at" class="text-muted fs-12 mt-1">
                  {{ $t('partners.show.token_expires') }}: {{ formatDate(partner.token_expires_at) }}
                </div>
              </div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">{{ $t('partners.show.last_synced') }}</div>
              <div class="fw-semibold">{{ formatDate(partner.last_synced_at) }}</div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="8">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('partners.form.api_section') }}</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('partners.form.auth_type') }}</div>
                <div class="fw-semibold">{{ partner.auth_type_label || partner.auth_type }}</div>
              </BCol>
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('partners.form.api_base_url') }}</div>
                <div class="fw-semibold text-truncate">{{ partner.api_base_url || $t('common.empty_value') }}</div>
              </BCol>
              <BCol md="6" v-if="partner.endpoint_login">
                <div class="text-muted fs-13">{{ $t('partners.form.endpoint_login') }}</div>
                <div class="fw-semibold text-truncate"><code>{{ partner.endpoint_login }}</code></div>
              </BCol>
              <BCol md="3" v-if="partner.login_username_field">
                <div class="text-muted fs-13">{{ $t('partners.form.login_username_field') }}</div>
                <div class="fw-semibold"><code>{{ partner.login_username_field }}</code></div>
              </BCol>
              <BCol md="3" v-if="partner.login_token_field">
                <div class="text-muted fs-13">{{ $t('partners.form.login_token_field') }}</div>
                <div class="fw-semibold"><code>{{ partner.login_token_field }}</code></div>
              </BCol>
              <BCol md="3">
                <div class="text-muted fs-13">{{ $t('partners.form.endpoint_statuses') }}</div>
                <div class="fw-semibold"><code>{{ partner.endpoint_statuses || '/all-status-deliveries' }}</code></div>
              </BCol>
              <BCol md="3">
                <div class="text-muted fs-13">{{ $t('partners.form.endpoint_deliveries') }}</div>
                <div class="fw-semibold"><code>{{ partner.endpoint_deliveries || '/deliveries' }}</code></div>
              </BCol>
              <BCol md="3" v-if="partner.delivery_lookup_param">
                <div class="text-muted fs-13">{{ $t('partners.form.delivery_lookup_param') }}</div>
                <div class="fw-semibold"><code>{{ partner.delivery_lookup_param }}</code></div>
              </BCol>
              <BCol md="3" v-if="partner.endpoint_update">
                <div class="text-muted fs-13">{{ $t('partners.form.endpoint_update') }}</div>
                <div class="fw-semibold"><code>{{ partner.endpoint_update }}</code></div>
              </BCol>
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('partners.form.sync_status') }}</div>
                <div class="fw-semibold">
                  <span class="badge fs-13" :class="partner.sync_status ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                    {{ partner.sync_status ? $t('common.active') : $t('common.inactive') }}
                  </span>
                  <div class="text-muted fs-12 mt-1">{{ $t('partners.form.sync_status_hint') }}</div>
                </div>
              </BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('partners.show.delegated_cities') }} / {{ $t('partners.show.delegated_sectors') }}</h5></BCardHeader>
          <BCardBody>
            <div v-if="delegatedCities.length" class="d-flex flex-wrap gap-2 mb-3">
              <span v-for="city in delegatedCities" :key="city.id" class="badge bg-info-subtle text-info fs-13">
                <i class="ri-map-pin-line align-bottom me-1"></i>{{ city.name }}
              </span>
            </div>
            <div v-if="delegatedSectors.length" class="d-flex flex-wrap gap-2">
              <span v-for="sector in delegatedSectors" :key="sector.id" class="badge bg-primary-subtle text-primary fs-13">
                {{ sector.city_name ? `${sector.city_name} / ` : '' }}{{ sector.name }}
              </span>
            </div>
            <div v-if="!delegatedCities.length && !delegatedSectors.length" class="text-muted">{{ $t('partners.show.no_cities') }}</div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('partners.form.status_mapping_section') }}</h5></BCardHeader>
          <BCardBody>
            <div v-if="statusMappings.length" class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('partners.form.speedzone_status') }}</th>
                    <th>{{ $t('partners.form.partner_status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="mapping in statusMappings" :key="mapping.id ?? `${mapping.speedzone_status}-${mapping.partner_status}`">
                    <td><span class="badge bg-light text-body border">{{ mapping.speedzone_status_label || mapping.speedzone_status }}</span></td>
                    <td><code>{{ mapping.partner_status }}</code></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-muted">{{ $t('partners.show.no_mappings') }}</div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('partners.form.field_mapping_section') }}</h5></BCardHeader>
          <BCardBody>
            <div v-if="fieldMappings.length" class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('partners.form.speedzone_field') }}</th>
                    <th>{{ $t('partners.form.partner_field') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="mapping in fieldMappings" :key="mapping.id ?? `${mapping.speedzone_field}-${mapping.partner_field}`">
                    <td><span class="badge bg-light text-body border">{{ mapping.speedzone_field_label || mapping.speedzone_field }}</span></td>
                    <td><code>{{ mapping.partner_field }}</code></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-muted">{{ $t('partners.show.no_field_mappings') }}</div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('partners.assignments.assigned_admins') }}</h5></BCardHeader>
          <BCardBody>
            <div v-if="assignedAdmins.length" class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('partners.show.admin_name') }}</th>
                    <th>{{ $t('partners.show.admin_email') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="admin in assignedAdmins" :key="admin.id">
                    <td class="fw-semibold">{{ admin.name }}</td>
                    <td class="text-muted">{{ admin.email || $t('common.empty_value') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="text-muted">{{ $t('partners.show.no_assigned_admins') }}</div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <!-- API logs monitoring -->
    <BCard no-body class="mt-3">
      <BCardHeader class="d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">
          <i class="ri-pulse-line align-bottom me-1"></i>{{ $t('partners.logs.title') }}
        </h5>
        <span class="text-muted fs-13">{{ $t('partners.logs.subtitle') }}</span>
      </BCardHeader>
      <BCardBody>
        <div class="table-responsive table-card">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th>{{ $t('partners.logs.when') }}</th>
                <th>{{ $t('partners.logs.action') }}</th>
                <th>{{ $t('partners.logs.method') }}</th>
                <th>{{ $t('partners.logs.endpoint') }}</th>
                <th class="text-center">{{ $t('partners.logs.status') }}</th>
                <th class="text-end">{{ $t('partners.logs.duration') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <template v-for="log in logRows" :key="log.id">
                <tr>
                  <td class="text-nowrap fs-13">{{ formatDate(log.created_at) }}</td>
                  <td>
                    <span class="badge" :class="log.action === 'AUTH' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info'">
                      {{ log.action }}
                    </span>
                  </td>
                  <td><code>{{ log.method }}</code></td>
                  <td class="text-truncate" style="max-width: 280px" :title="log.endpoint">{{ log.endpoint }}</td>
                  <td class="text-center">
                    <span
                      class="badge"
                      :class="log.is_success ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
                    >
                      {{ log.status_code ?? '—' }}
                    </span>
                  </td>
                  <td class="text-end text-muted fs-13">{{ log.duration_ms != null ? `${log.duration_ms} ms` : '—' }}</td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-light" @click="toggleLog(log.id)">
                      <i :class="expandedLogId === log.id ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"></i>
                    </button>
                  </td>
                </tr>
                <tr v-if="expandedLogId === log.id">
                  <td colspan="7" class="bg-light">
                    <div v-if="log.error_message" class="text-danger mb-2"><strong>{{ $t('partners.logs.error') }}:</strong> {{ log.error_message }}</div>
                    <BRow class="g-3">
                      <BCol md="6">
                        <div class="text-muted fs-12 mb-1">{{ $t('partners.logs.request') }}</div>
                        <pre class="bg-white border rounded p-2 fs-12 mb-0" style="max-height: 200px; overflow: auto">{{ JSON.stringify(log.request_payload, null, 2) || '—' }}</pre>
                      </BCol>
                      <BCol md="6">
                        <div class="text-muted fs-12 mb-1">{{ $t('partners.logs.response') }}</div>
                        <pre class="bg-white border rounded p-2 fs-12 mb-0" style="max-height: 200px; overflow: auto">{{ JSON.stringify(log.response_payload, null, 2) || '—' }}</pre>
                      </BCol>
                    </BRow>
                  </td>
                </tr>
              </template>
              <tr v-if="logRows.length === 0">
                <td colspan="7" class="text-center text-muted py-4">{{ $t('partners.logs.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="logMeta.links" class="d-flex justify-content-end mt-3">
          <ul class="pagination pagination-sm mb-0">
            <li
              v-for="(link, i) in logMeta.links"
              :key="i"
              class="page-item"
              :class="{ active: link.active, disabled: !link.url }"
            >
              <button class="page-link" @click="goToLogPage(link.url)" v-html="link.label"></button>
            </li>
          </ul>
        </div>
      </BCardBody>
    </BCard>
  </Layout>
</template>
