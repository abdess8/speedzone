<script setup>
import { computed, ref, watch } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";
import InputError from "@/Components/InputError.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  form: { type: Object, required: true },
  cities: { type: Array, default: () => [] },
  orderStatuses: { type: Array, default: () => [] },
  orderFields: { type: Array, default: () => [] },
  updateFields: { type: Array, default: () => [] },
  authTypes: { type: Array, default: () => [] },
  partnerId: { type: Number, default: null },
  isEdit: { type: Boolean, default: false },
  canTestConnection: { type: Boolean, default: true },
  currentLogoUrl: { type: String, default: null },
});

const testingConnection = ref(false);
const logoPreview = ref(null);

const displayedLogo = computed(() => logoPreview.value || props.currentLogoUrl || null);

const onLogoChange = (event) => {
  const file = event.target.files[0];
  props.form.logo = file || null;

  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      logoPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    logoPreview.value = null;
  }
};

const isLoginToken = computed(() => props.form.auth_type === "LOGIN_TOKEN");
const isApiKey = computed(() => props.form.auth_type === "API_KEY");
const isBearer = computed(() => props.form.auth_type === "BEARER");
const showClientId = computed(() => !isBearer.value);

const credentialLabels = computed(() => {
  switch (props.form.auth_type) {
    case "BASIC":
      return { id: t("partners.form.username"), secret: t("partners.form.password") };
    case "BEARER":
      return { secret: t("partners.form.bearer_token") };
    case "API_KEY":
      return { id: t("partners.form.api_key_header"), secret: t("partners.form.api_key") };
    case "LOGIN_TOKEN":
      return {
        id: props.form.login_username_field || "public_key",
        secret: props.form.login_password_field || "secret_key",
        idHint: t("partners.form.login_field_hint"),
        secretHint: t("partners.form.login_field_hint"),
      };
    default:
      return { id: t("partners.form.client_id"), secret: t("partners.form.client_secret") };
  }
});

const loginPayloadPreview = computed(() => {
  if (!isLoginToken.value) return null;

  const key1 = props.form.login_username_field || "public_key";
  const key2 = props.form.login_password_field || "secret_key";

  return JSON.stringify(
    {
      [key1]: props.form.client_id || "…",
      [key2]: props.form.client_secret ? "***" : "…",
    },
    null,
    2
  );
});

const selectedAuthDescription = computed(() =>
  props.authTypes.find((type) => type.value === props.form.auth_type)?.description ?? ""
);

const addStatusMapping = () => {
  if (!Array.isArray(props.form.status_mappings)) {
    props.form.status_mappings = [];
  }
  props.form.status_mappings.push({ speedzone_status: "", partner_status: "" });
};

const removeStatusMapping = (index) => {
  props.form.status_mappings.splice(index, 1);
};

const addFieldMapping = () => {
  if (!Array.isArray(props.form.field_mappings)) {
    props.form.field_mappings = [];
  }
  props.form.field_mappings.push({ speedzone_field: "", partner_field: "" });
};

const removeFieldMapping = (index) => {
  props.form.field_mappings.splice(index, 1);
};

const addUpdateFieldMapping = () => {
  if (!Array.isArray(props.form.update_field_mappings)) {
    props.form.update_field_mappings = [];
  }
  props.form.update_field_mappings.push({ speedzone_field: "", partner_field: "" });
};

const removeUpdateFieldMapping = (index) => {
  props.form.update_field_mappings.splice(index, 1);
};

const updatePayloadPreview = computed(() => {
  const mappings = props.form.update_field_mappings ?? [];
  if (!mappings.length) {
    return JSON.stringify(
      {
        id: "DXXXXXXX",
        status: "DELIVERED",
        message: "string",
        proof_image: "string",
        deliver_by: "2024-09-20",
        isDeliveredPartial: 1,
      },
      null,
      2
    );
  }

  const preview = {};
  mappings.forEach((mapping) => {
    if (!mapping.partner_field) return;
    preview[mapping.partner_field] = mapping.speedzone_field
      ? `<${mapping.speedzone_field}>`
      : "…";
  });

  return JSON.stringify(preview, null, 2);
});

watch(
  () => [...(props.form.city_ids ?? [])],
  (cityIds) => {
    const allowedSectorIds = props.cities
      .filter((city) => cityIds.includes(city.id))
      .flatMap((city) => (city.sectors ?? []).map((sector) => sector.id));

    props.form.sector_ids = (props.form.sector_ids ?? []).filter((id) => allowedSectorIds.includes(id));
  }
);

const testConnection = async () => {
  if (!props.form.api_base_url) {
    Swal.fire({ icon: "warning", title: t("partners.connection.missing_url") });
    return;
  }

  testingConnection.value = true;

  const payload = {
    api_base_url: props.form.api_base_url,
    auth_type: props.form.auth_type,
    client_id: props.form.client_id,
    client_secret: props.form.client_secret || undefined,
    endpoint_statuses: props.form.endpoint_statuses,
    endpoint_login: props.form.endpoint_login,
    api_key_header: props.form.api_key_header,
    login_username_field: props.form.login_username_field,
    login_password_field: props.form.login_password_field,
    login_token_field: props.form.login_token_field,
    endpoint_deliveries: props.form.endpoint_deliveries,
  };

  const url = props.partnerId
    ? route("partners.test-connection", props.partnerId)
    : route("partners.test-connection.draft");

  try {
    const { data } = await axios.post(url, payload);
    const loginInfo = data.login;
    let detail = data.message;
    if (loginInfo?.token_preview) {
      detail = loginInfo.cached
        ? t("partners.connection.login_cached")
        : t("partners.connection.login_obtained");
      detail += ` (${loginInfo.token_preview})`;
    }
    Swal.fire({
      icon: "success",
      title: t("partners.connection.success"),
      text: detail,
      timer: 4000,
      showConfirmButton: false,
    });
  } catch (error) {
    const message = error.response?.data?.message ?? t("partners.connection.failed");
    Swal.fire({ icon: "error", title: t("partners.connection.failed"), text: message });
  } finally {
    testingConnection.value = false;
  }
};
</script>

<template>
  <BRow>
    <BCol xl="8" class="mx-auto">
      <!-- General information -->
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('partners.form.info') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="8">
              <label class="form-label">{{ $t('partners.table.name') }} <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control"
                v-model="form.name"
                :class="{ 'is-invalid': form.errors.name }"
                :placeholder="$t('partners.form.name_placeholder')"
              />
              <InputError :message="form.errors.name" />
            </BCol>
            <BCol md="4">
              <label class="form-label">{{ $t('partners.table.ice_number') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.ice_number"
                :class="{ 'is-invalid': form.errors.ice_number }"
                :placeholder="$t('partners.form.ice_placeholder')"
              />
              <InputError :message="form.errors.ice_number" />
            </BCol>
            <BCol md="8">
              <label class="form-label">{{ $t('partners.form.logo') }}</label>
              <div v-if="displayedLogo" class="mb-2">
                <img
                  :src="displayedLogo"
                  alt=""
                  class="rounded border"
                  width="64"
                  height="64"
                  style="object-fit: contain;"
                />
              </div>
              <input
                type="file"
                class="form-control"
                accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
                @change="onLogoChange"
                :class="{ 'is-invalid': form.errors.logo }"
              />
              <small class="text-muted">{{ $t('partners.form.logo_hint') }}</small>
              <InputError :message="form.errors.logo" />
            </BCol>
            <BCol md="4">
              <label class="form-label d-block">{{ $t('common.status') }}</label>
              <div class="form-check form-switch fs-15 mt-2">
                <input class="form-check-input" type="checkbox" role="switch" id="partnerActive" v-model="form.is_active" />
                <label class="form-check-label" for="partnerActive">
                  {{ form.is_active ? $t('common.active') : $t('common.inactive') }}
                </label>
              </div>
            </BCol>
            <BCol md="6">
              <label class="form-label">{{ $t('partners.form.reception_city') }}</label>
              <select
                v-model="form.reception_city_id"
                class="form-select"
                :class="{ 'is-invalid': form.errors.reception_city_id }"
              >
                <option :value="null">{{ $t('common.none') }}</option>
                <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
              </select>
              <InputError :message="form.errors.reception_city_id" />
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <!-- Cities & sectors (loaded from DB) -->
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('partners.form.coverage_section') }}</h5>
        </BCardHeader>
        <BCardBody>
          <p class="text-muted fs-13 mb-3">{{ $t('partners.form.coverage_hint') }}</p>

          <div v-if="cities.length === 0" class="text-muted">{{ $t('partners.form.no_cities_available') }}</div>

          <div v-for="city in cities" :key="city.id" class="border rounded p-3 mb-3">
            <div class="form-check mb-2">
              <input
                class="form-check-input"
                type="checkbox"
                :id="`city-${city.id}`"
                :value="city.id"
                v-model="form.city_ids"
              />
              <label class="form-check-label fw-semibold" :for="`city-${city.id}`">
                <i class="ri-map-pin-line me-1"></i>{{ city.name }}
              </label>
            </div>

            <div v-if="form.city_ids.includes(city.id)" class="ms-4">
              <div class="text-muted fs-12 mb-2">{{ $t('partners.form.delegated_sectors') }}</div>
              <div v-if="(city.sectors ?? []).length === 0" class="text-muted fs-13">
                {{ $t('partners.form.no_sectors_in_city') }}
              </div>
              <div v-else class="row g-2">
                <BCol sm="6" md="4" v-for="sector in city.sectors" :key="sector.id">
                  <div class="form-check">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :id="`sector-${sector.id}`"
                      :value="sector.id"
                      v-model="form.sector_ids"
                    />
                    <label class="form-check-label" :for="`sector-${sector.id}`">{{ sector.name }}</label>
                  </div>
                </BCol>
              </div>
            </div>
          </div>

          <InputError :message="form.errors.city_ids" />
          <InputError :message="form.errors.sector_ids" />
        </BCardBody>
      </BCard>

      <!-- API integration -->
      <BCard no-body>
        <BCardHeader class="d-flex align-items-center justify-content-between">
          <h5 class="card-title mb-0">{{ $t('partners.form.api_section') }}</h5>
          <button
            v-if="canTestConnection"
            type="button"
            class="btn btn-sm btn-soft-info"
            :disabled="testingConnection"
            @click="testConnection"
          >
            <span v-if="testingConnection" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="ri-link-m align-bottom me-1"></i>
            {{ testingConnection ? $t('partners.connection.testing') : $t('partners.connection.test_button') }}
          </button>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="6">
              <label class="form-label">{{ $t('partners.form.auth_type') }}</label>
              <select v-model="form.auth_type" class="form-select" :class="{ 'is-invalid': form.errors.auth_type }">
                <option v-for="type in authTypes" :key="type.value" :value="type.value">
                  {{ type.label }}
                </option>
              </select>
              <small v-if="selectedAuthDescription" class="text-muted">{{ selectedAuthDescription }}</small>
              <InputError :message="form.errors.auth_type" />
            </BCol>
            <BCol md="6">
              <label class="form-label">{{ $t('partners.form.sync_frequency') }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <input
                  type="number"
                  min="1"
                  max="1440"
                  class="form-control"
                  v-model="form.sync_frequency_minutes"
                  :class="{ 'is-invalid': form.errors.sync_frequency_minutes }"
                />
                <span class="input-group-text">{{ $t('partners.form.minutes') }}</span>
              </div>
              <InputError :message="form.errors.sync_frequency_minutes" />
            </BCol>
            <BCol md="6" class="d-flex align-items-end">
              <div class="form-check form-switch mb-3">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="partnerSyncStatus"
                  v-model="form.sync_status"
                />
                <label class="form-check-label" for="partnerSyncStatus">
                  {{ $t('partners.form.sync_status') }}
                </label>
                <small class="d-block text-muted">{{ $t('partners.form.sync_status_hint') }}</small>
              </div>
            </BCol>
            <BCol md="8">
              <label class="form-label">{{ $t('partners.form.api_base_url') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.api_base_url"
                :class="{ 'is-invalid': form.errors.api_base_url }"
                placeholder="https://app.sendit.ma/api/v1"
              />
              <InputError :message="form.errors.api_base_url" />
            </BCol>

            <BCol md="12" v-if="isLoginToken">
              <label class="form-label">{{ $t('partners.form.endpoint_login') }} <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control"
                v-model="form.endpoint_login"
                :class="{ 'is-invalid': form.errors.endpoint_login }"
                placeholder="https://app.sendit.ma/api/v1/login"
              />
              <small class="text-muted">{{ $t('partners.form.endpoint_login_hint') }}</small>
              <InputError :message="form.errors.endpoint_login" />
            </BCol>

            <BCol md="6" v-if="showClientId">
              <label class="form-label">
                {{ credentialLabels.id }}
                <span v-if="isLoginToken" class="text-muted fw-normal">({{ $t('partners.form.login_json_field') }})</span>
              </label>
              <input
                type="text"
                class="form-control"
                v-model="form.client_id"
                :class="{ 'is-invalid': form.errors.client_id }"
                autocomplete="off"
                :placeholder="isLoginToken ? '7eeadef76a523695701059e09514ab71' : ''"
              />
              <InputError :message="form.errors.client_id" />
            </BCol>
            <BCol :md="showClientId ? 6 : 12">
              <label class="form-label">
                {{ credentialLabels.secret }}
                <span v-if="isLoginToken" class="text-muted fw-normal">({{ $t('partners.form.login_json_field') }})</span>
              </label>
              <input
                type="password"
                class="form-control"
                v-model="form.client_secret"
                :class="{ 'is-invalid': form.errors.client_secret }"
                autocomplete="new-password"
                :placeholder="isEdit ? $t('partners.form.secret_keep') : ''"
              />
              <InputError :message="form.errors.client_secret" />
              <small class="text-muted">{{ $t('partners.form.secret_hint') }}</small>
            </BCol>

            <template v-if="isLoginToken">
              <BCol md="4">
                <label class="form-label">{{ $t('partners.form.login_username_field') }}</label>
                <input type="text" class="form-control" v-model="form.login_username_field" placeholder="public_key" />
                <small class="text-muted">{{ $t('partners.form.login_username_field_hint') }}</small>
              </BCol>
              <BCol md="4">
                <label class="form-label">{{ $t('partners.form.login_password_field') }}</label>
                <input type="text" class="form-control" v-model="form.login_password_field" placeholder="secret_key" />
                <small class="text-muted">{{ $t('partners.form.login_password_field_hint') }}</small>
              </BCol>
              <BCol md="4">
                <label class="form-label">{{ $t('partners.form.login_token_field') }}</label>
                <input type="text" class="form-control" v-model="form.login_token_field" placeholder="data.token" />
                <small class="text-muted">{{ $t('partners.form.login_token_field_hint') }}</small>
              </BCol>
              <BCol cols="12">
                <div class="bg-light border rounded p-3">
                  <div class="text-muted fs-12 mb-1">{{ $t('partners.form.login_payload_preview') }}</div>
                  <pre class="mb-0 fs-12 text-body">{{ loginPayloadPreview }}</pre>
                </div>
              </BCol>
            </template>

            <BCol cols="12">
              <hr class="my-1" />
              <div class="text-muted fs-13 mb-2">{{ $t('partners.form.endpoints_section') }}</div>
            </BCol>
            <BCol md="3">
              <label class="form-label">{{ $t('partners.form.endpoint_statuses') }}</label>
              <input type="text" class="form-control" v-model="form.endpoint_statuses" placeholder="/all-status-deliveries" />
              <InputError :message="form.errors.endpoint_statuses" />
            </BCol>
            <BCol md="3">
              <label class="form-label">{{ $t('partners.form.endpoint_deliveries') }}</label>
              <input type="text" class="form-control" v-model="form.endpoint_deliveries" placeholder="/deliveries" />
              <InputError :message="form.errors.endpoint_deliveries" />
            </BCol>
            <BCol md="3">
              <label class="form-label">{{ $t('partners.form.delivery_lookup_param') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.delivery_lookup_param"
                :placeholder="$t('partners.form.delivery_lookup_param_placeholder')"
              />
              <small class="text-muted">{{ $t('partners.form.delivery_lookup_param_hint') }}</small>
              <InputError :message="form.errors.delivery_lookup_param" />
            </BCol>
            <BCol md="3">
              <label class="form-label">{{ $t('partners.form.endpoint_update') }}</label>
              <input type="text" class="form-control" v-model="form.endpoint_update" placeholder="/update-deliveries" />
              <InputError :message="form.errors.endpoint_update" />
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <!-- Status mapping -->
      <BCard no-body>
        <BCardHeader class="d-flex align-items-center justify-content-between">
          <h5 class="card-title mb-0">{{ $t('partners.form.status_mapping_section') }}</h5>
          <button type="button" class="btn btn-sm btn-soft-success" @click="addStatusMapping">
            <i class="ri-add-line align-bottom me-1"></i>{{ $t('partners.form.add_mapping') }}
          </button>
        </BCardHeader>
        <BCardBody>
          <p class="text-muted fs-13">{{ $t('partners.form.status_mapping_hint') }}</p>

          <div v-if="!(form.status_mappings ?? []).length" class="text-muted text-center py-3">
            {{ $t('partners.form.no_mappings') }}
          </div>

          <div
            v-for="(mapping, index) in form.status_mappings"
            :key="index"
            class="row g-2 align-items-end mb-2"
          >
            <BCol md="5">
              <label class="form-label">{{ $t('partners.form.speedzone_status') }}</label>
              <select v-model="mapping.speedzone_status" class="form-select">
                <option value="">{{ $t('partners.form.select_status') }}</option>
                <option v-for="status in orderStatuses" :key="status.value" :value="status.value">
                  {{ status.label }}
                </option>
              </select>
            </BCol>
            <BCol md="5">
              <label class="form-label">{{ $t('partners.form.partner_status') }}</label>
              <input
                type="text"
                class="form-control text-uppercase"
                v-model="mapping.partner_status"
                :placeholder="$t('partners.form.partner_status_placeholder')"
              />
            </BCol>
            <BCol md="2">
              <button type="button" class="btn btn-soft-danger w-100" @click="removeStatusMapping(index)">
                <i class="ri-delete-bin-line"></i>
              </button>
            </BCol>
          </div>

          <InputError :message="form.errors['status_mappings']" />
        </BCardBody>
      </BCard>

      <!-- Field mapping -->
      <BCard no-body>
        <BCardHeader class="d-flex align-items-center justify-content-between">
          <h5 class="card-title mb-0">{{ $t('partners.form.field_mapping_section') }}</h5>
          <button type="button" class="btn btn-sm btn-soft-success" @click="addFieldMapping">
            <i class="ri-add-line align-bottom me-1"></i>{{ $t('partners.form.add_field_mapping') }}
          </button>
        </BCardHeader>
        <BCardBody>
          <p class="text-muted fs-13">{{ $t('partners.form.field_mapping_hint') }}</p>

          <div v-if="!(form.field_mappings ?? []).length" class="text-muted text-center py-3">
            {{ $t('partners.form.no_field_mappings') }}
          </div>

          <div
            v-for="(mapping, index) in form.field_mappings"
            :key="index"
            class="row g-2 align-items-end mb-2"
          >
            <BCol md="5">
              <label class="form-label">{{ $t('partners.form.speedzone_field') }}</label>
              <select v-model="mapping.speedzone_field" class="form-select">
                <option value="">{{ $t('partners.form.select_field') }}</option>
                <option v-for="field in orderFields" :key="field.value" :value="field.value">
                  {{ field.label }}
                </option>
              </select>
            </BCol>
            <BCol md="5">
              <label class="form-label">{{ $t('partners.form.partner_field') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="mapping.partner_field"
                :placeholder="$t('partners.form.partner_field_placeholder')"
              />
            </BCol>
            <BCol md="2">
              <button type="button" class="btn btn-soft-danger w-100" @click="removeFieldMapping(index)">
                <i class="ri-delete-bin-line"></i>
              </button>
            </BCol>
          </div>

          <InputError :message="form.errors['field_mappings']" />
        </BCardBody>
      </BCard>

      <!-- Outbound update payload mapping -->
      <BCard no-body>
        <BCardHeader class="d-flex align-items-center justify-content-between">
          <h5 class="card-title mb-0">{{ $t('partners.form.update_field_mapping_section') }}</h5>
          <button type="button" class="btn btn-sm btn-soft-success" @click="addUpdateFieldMapping">
            <i class="ri-add-line align-bottom me-1"></i>{{ $t('partners.form.add_update_field_mapping') }}
          </button>
        </BCardHeader>
        <BCardBody>
          <p class="text-muted fs-13">{{ $t('partners.form.update_field_mapping_hint') }}</p>

          <div class="bg-light border rounded p-3 mb-3">
            <div class="text-muted fs-12 mb-1">{{ $t('partners.form.update_payload_preview') }}</div>
            <pre class="mb-0 fs-12 text-body">{{ updatePayloadPreview }}</pre>
          </div>

          <div v-if="!(form.update_field_mappings ?? []).length" class="text-muted text-center py-3">
            {{ $t('partners.form.no_update_field_mappings') }}
          </div>

          <div
            v-for="(mapping, index) in form.update_field_mappings"
            :key="index"
            class="row g-2 align-items-end mb-2"
          >
            <BCol md="5">
              <label class="form-label">{{ $t('partners.form.speedzone_field') }}</label>
              <select v-model="mapping.speedzone_field" class="form-select">
                <option value="">{{ $t('partners.form.select_field') }}</option>
                <option v-for="field in updateFields" :key="field.value" :value="field.value">
                  {{ field.label }}
                </option>
              </select>
            </BCol>
            <BCol md="5">
              <label class="form-label">{{ $t('partners.form.partner_api_field') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="mapping.partner_field"
                :placeholder="$t('partners.form.update_field_placeholder')"
              />
            </BCol>
            <BCol md="2">
              <button type="button" class="btn btn-soft-danger w-100" @click="removeUpdateFieldMapping(index)">
                <i class="ri-delete-bin-line"></i>
              </button>
            </BCol>
          </div>

          <InputError :message="form.errors['update_field_mappings']" />
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
