<script setup>
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import axios from "axios";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import InputError from "@/Components/InputError.vue";

const { t } = useI18n();

const props = defineProps({
  form: { type: Object, required: true },
  cities: { type: Array, default: () => [] },
  sectors: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});

const money = (value) =>
  new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

const isCashPayment = computed(() => props.form.payment_method === "CASH");

const totalAmount = computed(() => {
  const collectible = isCashPayment.value ? Number(props.form.order_amount || 0) : 0;
  return collectible + Number(props.form.delivery_price || 0);
});

watch(
  () => props.form.payment_method,
  (method) => {
    if (method === "CASH") {
      props.form.order_value = "";
    } else {
      props.form.order_amount = "";
    }
  }
);

const sectorList = ref([...props.sectors]);
const loadingSectors = ref(false);

const cityOptions = computed(() =>
  props.cities.map((c) => ({ value: c.id, label: c.region ? `${c.name} — ${c.region}` : c.name }))
);

const sectorOptions = computed(() =>
  sectorList.value.map((s) => ({ value: s.id, label: `${s.name} — ${money(s.delivery_price)} MAD` }))
);

const selectedSector = computed(() =>
  sectorList.value.find((s) => s.id === Number(props.form.sector_id)) ?? null
);

const sectorPlaceholder = computed(() => {
  if (!props.form.city_id) return t("orders.form.select_city_first");
  if (loadingSectors.value) return t("orders.form.loading_sectors");
  return t("orders.form.search_sector");
});

const fetchSectors = async (cityId) => {
  if (!cityId) {
    sectorList.value = [];
    return;
  }
  loadingSectors.value = true;
  try {
    const { data } = await axios.get(route("cities.sectors", cityId));
    sectorList.value = data.data ?? [];
  } finally {
    loadingSectors.value = false;
  }
};

watch(
  () => props.form.city_id,
  async (cityId) => {
    await fetchSectors(cityId);
    props.form.sector_id = null;
    props.form.delivery_price = "";
  }
);

watch(
  () => props.form.sector_id,
  (sectorId) => {
    const sector = sectorList.value.find((s) => s.id === Number(sectorId));
    if (sector) {
      props.form.delivery_price = sector.delivery_price;
    }
  }
);
</script>

<template>
  <BRow>
    <BCol xl="8">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('orders.form.customer_info') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="6">
              <label class="form-label">{{ $t('orders.form.first_name') }} <span class="text-danger">*</span></label>
              <input type="text" class="form-control" v-model="form.customer_first_name" :class="{ 'is-invalid': form.errors.customer_first_name }" />
              <InputError :message="form.errors.customer_first_name" />
            </BCol>
            <BCol md="6">
              <label class="form-label">{{ $t('orders.form.last_name') }} <span class="text-danger">*</span></label>
              <input type="text" class="form-control" v-model="form.customer_last_name" :class="{ 'is-invalid': form.errors.customer_last_name }" />
              <InputError :message="form.errors.customer_last_name" />
            </BCol>
            <BCol md="6">
              <label class="form-label">{{ $t('orders.form.phone') }} <span class="text-danger">*</span></label>
              <input type="text" class="form-control" v-model="form.customer_phone" :class="{ 'is-invalid': form.errors.customer_phone }" />
              <InputError :message="form.errors.customer_phone" />
            </BCol>
            <BCol md="6">
              <label class="form-label">{{ $t('orders.form.delivery_city') }} <span class="text-danger">*</span></label>
              <Multiselect
                v-model="form.city_id"
                :options="cityOptions"
                :searchable="true"
                :close-on-select="true"
                :placeholder="$t('orders.form.search_city')"
                :class="{ 'is-invalid': form.errors.city_id }"
              />
              <InputError :message="form.errors.city_id" />
            </BCol>
            <BCol md="6">
              <label class="form-label">{{ $t('orders.form.delivery_sector') }} <span class="text-danger">*</span></label>
              <Multiselect
                v-model="form.sector_id"
                :options="sectorOptions"
                :searchable="true"
                :close-on-select="true"
                :disabled="!form.city_id || loadingSectors"
                :placeholder="sectorPlaceholder"
                :class="{ 'is-invalid': form.errors.sector_id }"
              />
              <InputError :message="form.errors.sector_id" />
              <div v-if="form.city_id && !loadingSectors && sectorOptions.length === 0" class="text-warning fs-13 mt-1">
                {{ $t('orders.form.no_sectors') }}
              </div>
            </BCol>
            <BCol md="6" v-if="selectedSector">
              <label class="form-label">{{ $t('orders.form.sector_delivery_price') }}</label>
              <div class="form-control bg-light fw-semibold text-primary">{{ money(selectedSector.delivery_price) }} MAD</div>
            </BCol>
            <BCol md="12">
              <label class="form-label">{{ $t('orders.form.address') }} <span class="text-danger">*</span></label>
              <textarea class="form-control" rows="2" v-model="form.customer_address" :class="{ 'is-invalid': form.errors.customer_address }"></textarea>
              <InputError :message="form.errors.customer_address" />
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('orders.form.package_info') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="12">
              <label class="form-label">{{ $t('orders.form.notes') }}</label>
              <textarea class="form-control" rows="2" :placeholder="$t('orders.form.notes_placeholder')" v-model="form.notes" :class="{ 'is-invalid': form.errors.notes }"></textarea>
              <InputError :message="form.errors.notes" />
            </BCol>
            <BCol md="6">
              <div class="form-check card-radio h-100">
                <input class="form-check-input" type="checkbox" id="isFragile" v-model="form.is_fragile" />
                <label class="form-check-label w-100 text-center py-3" for="isFragile">
                  <span class="fs-24 d-block mb-2">📦</span>
                  <span class="fs-14 fw-medium d-block">{{ $t('orders.form.fragile_package') }}</span>
                  <small class="text-muted d-block mt-1">{{ $t('orders.form.fragile_hint') }}</small>
                </label>
              </div>
            </BCol>
            <BCol md="6">
              <div class="form-check card-radio h-100">
                <input class="form-check-input" type="checkbox" id="canBeOpened" v-model="form.can_be_opened" />
                <label class="form-check-label w-100 text-center py-3" for="canBeOpened">
                  <span class="fs-24 d-block mb-2">🔓</span>
                  <span class="fs-14 fw-medium d-block">{{ $t('orders.form.can_be_opened') }}</span>
                  <small class="text-muted d-block mt-1">{{ $t('orders.form.can_be_opened_hint') }}</small>
                </label>
              </div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>

    <BCol xl="4">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('orders.form.payment_amounts') }}</h5>
        </BCardHeader>
        <BCardBody>
          <div class="mb-3">
            <label class="form-label d-block">{{ $t('orders.form.payment_method') }} <span class="text-danger">*</span></label>
            <BRow class="g-2">
              <BCol v-for="method in paymentMethods" :key="method.value" cols="6">
                <div class="form-check card-radio h-100">
                  <input class="form-check-input" type="radio" :id="`pm-${method.value}`" :value="method.value" v-model="form.payment_method" />
                  <label class="form-check-label w-100" :for="`pm-${method.value}`">
                    <span class="fs-20 d-block mb-1">
                      <span class="me-1">{{ method.emoji }}</span>
                      <i :class="`${method.icon} align-bottom text-${method.color}`"></i>
                    </span>
                    <span class="fs-14 fw-medium">{{ method.label }}</span>
                  </label>
                </div>
              </BCol>
            </BRow>
            <InputError :message="form.errors.payment_method" />
          </div>

          <div v-if="isCashPayment" class="mb-3">
            <label class="form-label">{{ $t('orders.form.order_amount') }} <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="number" step="0.01" min="0" class="form-control" v-model="form.order_amount" :class="{ 'is-invalid': form.errors.order_amount }" />
              <span class="input-group-text">MAD</span>
            </div>
            <InputError :message="form.errors.order_amount" />
          </div>

          <div v-else class="mb-3">
            <label class="form-label">
              {{ $t('orders.form.order_value') }}
              <small class="text-muted">{{ $t('orders.form.order_value_optional') }}</small>
            </label>
            <div class="input-group">
              <input type="number" step="0.01" min="0" class="form-control" v-model="form.order_value" :class="{ 'is-invalid': form.errors.order_value }" :placeholder="$t('orders.form.order_value_placeholder')" />
              <span class="input-group-text">MAD</span>
            </div>
            <InputError :message="form.errors.order_value" />
          </div>

          <div class="mb-3">
            <label class="form-label">
              {{ $t('orders.form.delivery_price') }}
              <small class="text-muted">{{ $t('orders.form.delivery_price_hint') }}</small>
            </label>
            <div class="input-group">
              <input type="number" step="0.01" min="0" class="form-control" v-model="form.delivery_price" :class="{ 'is-invalid': form.errors.delivery_price }" />
              <span class="input-group-text">MAD</span>
            </div>
            <InputError :message="form.errors.delivery_price" />
          </div>

          <div class="d-flex justify-content-between align-items-center border-top pt-3">
            <span class="fs-15 fw-medium">{{ $t('orders.form.total_amount') }}</span>
            <span class="fs-18 fw-bold text-primary">{{ money(totalAmount) }} MAD</span>
          </div>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
