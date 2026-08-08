<script setup>
import { computed, ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import DocumentPreview from "@/Components/DocumentPreview.vue";
import FlipCardPreview from "@/Components/FlipCardPreview.vue";
import { roleLabel as sharedRoleLabel } from "@/utils/roleLabel";

const { t, locale } = useI18n();

const props = defineProps({
  user: { type: Object, required: true },
  stores: { type: Array, default: () => [] },
  teamMembers: { type: Array, default: () => [] },
});

// A stored photo path is no proof the file is still on disk, so fall back to the
// initials rather than showing the browser's broken-image glyph.
const photoFailed = ref(false);

const initials = computed(() => {
  const first = (props.user.first_name || props.user.name || "?").charAt(0);
  const last = (props.user.last_name || "").charAt(0);
  return (first + last).toUpperCase();
});

const roleBadge = computed(() => {
  const map = {
    SuperAdmin: "bg-danger-subtle text-danger",
    Admin: "bg-primary-subtle text-primary",
    Vendeur: "bg-success-subtle text-success",
    Livreur: "bg-info-subtle text-info",
    Partenaire: "bg-warning-subtle text-warning",
  };
  return map[props.user.role?.name] || "bg-secondary-subtle text-secondary";
});

const roleLabel = (role) => sharedRoleLabel(role, t) || t("common.empty_value_short");

const isSeller = computed(() =>
  ["Seller", "Vendeur"].includes(props.user.role?.name) ||
  (props.user.roles ?? []).some((r) => ["Seller", "Vendeur"].includes(r))
);

const isDriver = computed(() =>
  ["Driver", "Livreur"].includes(props.user.role?.name) ||
  (props.user.roles ?? []).some((r) => ["Driver", "Livreur"].includes(r))
);

const showBilling = computed(() => isSeller.value);

const hasIdentityDocuments = computed(() =>
  Boolean(
    props.user.rib_attachment_url ||
      props.user.cin_front_attachment_url ||
      props.user.cin_back_attachment_url
  )
);

const attachedFiles = computed(() => props.user.attached_files_urls ?? []);

const driverCities = computed(() => {
  const sectors = props.user.sectors ?? [];
  const cities = new Map();

  sectors.forEach((sector) => {
    const city = sector.city;
    if (!city) return;

    if (!cities.has(city.id)) {
      cities.set(city.id, { id: city.id, name: city.name, sectors: [] });
    }

    cities.get(city.id).sectors.push(sector);
  });

  return Array.from(cities.values()).sort((a, b) => a.name.localeCompare(b.name));
});

const formatDate = (value) => {
  if (!value) return t("common.empty_value_short");
  return new Date(value).toLocaleString(locale.value === "en" ? "en-GB" : "fr-FR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const formatDateOnly = (value) => {
  if (!value) return t("common.empty_value_short");
  return new Date(value).toLocaleDateString(locale.value === "en" ? "en-GB" : "fr-FR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

const labelFrom = (group, value) => {
  if (!value) return t("common.empty_value_short");
  const key = `${group}.${value}`;
  const translated = t(key);
  return translated !== key ? translated : value;
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('users.show_title')" :pageTitle="$t('users.page_title')" />
    <BRow>
      <BCol lg="4">
        <BCard no-body>
          <BCardBody class="text-center">
            <img
              v-if="user.photo_url && !photoFailed"
              :src="user.photo_url"
              :alt="user.full_name"
              class="rounded-circle avatar-xl object-fit-cover mx-auto"
              @error="photoFailed = true"
            />
            <div
              v-else
              class="avatar-xl rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center mx-auto fs-22 fw-medium"
            >
              {{ initials }}
            </div>
            <h5 class="mt-3 mb-1">{{ user.full_name }}</h5>
            <p class="text-muted mb-2">{{ user.email }}</p>
            <span class="badge" :class="roleBadge" v-if="user.role">{{ roleLabel(user.role) }}</span>
          </BCardBody>
          <BCardBody class="border-top">
            <div class="d-flex gap-2">
              <Link :href="route('users.edit', user.id)" class="btn btn-warning w-100">
                <i class="ri-pencil-fill align-bottom me-1"></i> {{ $t('common.edit') }}
              </Link>
              <Link :href="route('users.index')" class="btn btn-light w-100">{{ $t('common.back') }}</Link>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="8">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('users.show.info') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="table-responsive">
              <table class="table table-borderless mb-0">
                <tbody>
                  <tr>
                    <th class="ps-0" scope="row" style="width: 35%">{{ $t('users.show.first_name') }}</th>
                    <td class="text-muted">{{ user.first_name || $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.last_name') }}</th>
                    <td class="text-muted">{{ user.last_name || $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.email') }}</th>
                    <td class="text-muted">{{ user.email }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.phone') }}</th>
                    <td class="text-muted">{{ user.phone_number || $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.city') }}</th>
                    <td class="text-muted">{{ user.city?.name ?? user.city_name ?? $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.address') }}</th>
                    <td class="text-muted">{{ user.address || $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr v-if="isSeller">
                    <th class="ps-0" scope="row">{{ $t('users.show.pickup_address_1') }}</th>
                    <td class="text-muted">{{ user.pickup_address_1 || $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr v-if="isSeller">
                    <th class="ps-0" scope="row">{{ $t('users.show.pickup_address_2') }}</th>
                    <td class="text-muted">{{ user.pickup_address_2 || $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.cin') }}</th>
                    <td class="text-muted">{{ user.cin || $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.ice') }}</th>
                    <td class="text-muted">{{ user.ice_number || $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.role') }}</th>
                    <td class="text-muted">{{ user.role ? roleLabel(user.role) : $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.created_at') }}</th>
                    <td class="text-muted">{{ formatDate(user.created_at) }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.updated_at') }}</th>
                    <td class="text-muted">{{ formatDate(user.updated_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>

        <BCard v-if="isDriver" no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('users.show.coverage_info') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div v-if="driverCities.length">
              <div v-for="city in driverCities" :key="city.id" class="mb-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <i class="ri-building-2-line text-primary"></i>
                  <Link :href="route('cities.show', city.id)" class="fw-semibold link-primary">
                    {{ city.name }}
                  </Link>
                </div>
                <div class="d-flex flex-wrap gap-1 ms-4">
                  <Link
                    v-for="sector in city.sectors"
                    :key="sector.id"
                    :href="route('sectors.show', sector.id)"
                    class="badge bg-light text-body border text-decoration-none"
                  >
                    {{ sector.name }}
                  </Link>
                </div>
              </div>
            </div>
            <p v-else class="text-muted mb-0">{{ $t('users.show.no_coverage') }}</p>
          </BCardBody>
        </BCard>

        <BCard v-if="isSeller" no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('users.show.stores') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div v-if="stores.length" class="table-responsive">
              <table class="table table-borderless align-middle mb-0">
                <tbody>
                  <tr v-for="store in stores" :key="store.id">
                    <td class="ps-0" style="width: 48px">
                      <img
                        v-if="store.logo_url"
                        :src="store.logo_url"
                        :alt="store.name"
                        class="user-store-logo rounded"
                      />
                      <span v-else class="user-store-placeholder rounded">
                        <i class="ri-store-2-line"></i>
                      </span>
                    </td>
                    <td>
                      <span class="fw-medium d-block">{{ store.name }}</span>
                      <span class="text-muted fs-12">
                        {{ store.category || $t('stores.no_category') }}
                      </span>
                    </td>
                    <td class="text-muted">
                      {{ $t('stores.orders_count', { count: store.orders_count ?? 0 }) }}
                    </td>
                    <td class="text-end pe-0">
                      <span v-if="store.is_default" class="badge bg-primary-subtle text-primary">
                        {{ $t('stores.badges.default') }}
                      </span>
                      <span v-if="!store.is_active" class="badge bg-danger-subtle text-danger ms-1">
                        {{ $t('common.inactive') }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-muted mb-0">{{ $t('stores.empty') }}</p>
          </BCardBody>
        </BCard>

        <BCard v-if="isSeller" no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('users.show.team') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div v-if="teamMembers.length" class="table-responsive">
              <table class="table table-borderless align-middle mb-0">
                <tbody>
                  <tr v-for="member in teamMembers" :key="member.id">
                    <td class="ps-0">
                      <span class="fw-medium d-block">{{ member.name }}</span>
                      <span class="text-muted fs-12">{{ member.email }}</span>
                    </td>
                    <td>
                      <span
                        v-for="role in member.roles"
                        :key="role"
                        class="badge bg-primary-subtle text-primary me-1"
                      >
                        {{ role }}
                      </span>
                    </td>
                    <td class="text-muted fs-13">
                      {{ member.stores.join(', ') }}
                    </td>
                    <td class="text-end pe-0">
                      <span class="badge" :class="member.status_class">
                        {{ $t(`user_statuses.${member.status}`) }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-muted mb-0">{{ $t('team.empty') }}</p>
          </BCardBody>
        </BCard>

        <BCard v-if="showBilling" no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('users.show.billing_info') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="table-responsive">
              <table class="table table-borderless mb-0">
                <tbody>
                  <tr>
                    <th class="ps-0" scope="row" style="width: 35%">{{ $t('users.show.billing_status') }}</th>
                    <td>
                      <span class="badge" :class="user.billing_enabled ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                        {{ user.billing_enabled ? $t('users.show.billing_enabled') : $t('users.show.billing_disabled') }}
                      </span>
                    </td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.billing_frequency') }}</th>
                    <td class="text-muted">{{ labelFrom('billing_frequencies', user.billing_frequency) }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.next_billing_date') }}</th>
                    <td class="text-muted">{{ formatDateOnly(user.next_billing_date) }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.payment_method') }}</th>
                    <td class="text-muted">{{ labelFrom('seller_payment_methods', user.payment_method) }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.bank_name') }}</th>
                    <td class="text-muted">{{ user.bank_name || $t('common.empty_value_short') }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">{{ $t('users.show.rib') }}</th>
                    <td class="text-muted">{{ user.rib || $t('common.empty_value_short') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>

        <!-- The identity papers sit here rather than under billing: a driver
             also has a CIN on file, and the billing card is a seller's. -->
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('users.show.attached_documents') }}</h5>
          </BCardHeader>
          <BCardBody>
            <BRow v-if="hasIdentityDocuments" class="g-3">
              <!-- The CIN is a single card with two sides, so it is previewed as
                   one card that turns. Two thumbnails side by side never made it
                   clear they were two halves of the same document. -->
              <BCol sm="6" md="4">
                <FlipCardPreview
                  :front-url="user.cin_front_attachment_url"
                  :back-url="user.cin_back_attachment_url"
                  :label="$t('users.show.cin')"
                />
              </BCol>
              <BCol sm="6" md="4" v-if="user.rib_attachment_url">
                <DocumentPreview
                  :url="user.rib_attachment_url"
                  :label="$t('users.show.rib_attachment')"
                />
              </BCol>
            </BRow>

            <hr v-if="hasIdentityDocuments && attachedFiles.length" class="my-4" />

            <BRow v-if="attachedFiles.length" class="g-3">
              <BCol sm="6" md="4" v-for="(file, i) in attachedFiles" :key="i">
                <DocumentPreview :url="file.url" :label="file.name" />
                <a
                  :href="file.url"
                  target="_blank"
                  download
                  class="btn btn-sm btn-soft-primary w-100 mt-2"
                >
                  <i class="ri-download-2-line align-bottom me-1"></i>
                  {{ $t('common.download') }}
                </a>
              </BCol>
            </BRow>

            <p v-if="!hasIdentityDocuments && !attachedFiles.length" class="text-muted mb-0">
              {{ $t('users.show.no_documents') }}
            </p>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
.user-store-logo {
  height: 40px;
  width: 40px;
  object-fit: contain;
  background-color: var(--vz-light);
}

.user-store-placeholder {
  height: 40px;
  width: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--vz-primary);
  background-color: rgba(var(--vz-primary-rgb), 0.12);
}
</style>
