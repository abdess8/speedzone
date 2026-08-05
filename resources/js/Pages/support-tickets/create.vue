<script setup>
import { ref, computed, watch } from "vue";
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
  categories: { type: Array, default: () => [] },
  objectTypes: { type: Array, default: () => [] },
  prefill: { type: Object, default: () => ({}) },
});

const form = useForm({
  category: "",
  object_type: props.prefill.object_type ?? "",
  object_id: props.prefill.object_id ?? "",
  subject: "",
  message: "",
  attachment: null,
});

const objectOptions = ref([]);
const loadingObjects = ref(false);

const categoryOptions = computed(() =>
  props.categories.map((c) => ({ value: c.value, label: c.label }))
);

const objectTypeOptions = computed(() =>
  props.objectTypes.map((o) => ({ value: o.value, label: o.label }))
);

const loadObjects = async () => {
  if (!form.object_type) {
    objectOptions.value = [];
    form.object_id = "";
    return;
  }

  loadingObjects.value = true;
  try {
    const { data } = await axios.get(route("support-tickets.related"), {
      params: { object_type: form.object_type },
    });
    objectOptions.value = data.data ?? [];
    if (form.object_id && !objectOptions.value.some((o) => o.value === form.object_id)) {
      form.object_id = "";
    }
  } catch {
    objectOptions.value = [];
  } finally {
    loadingObjects.value = false;
  }
};

watch(() => form.object_type, () => {
  loadObjects();
});

const onAttachmentChange = (event) => {
  form.attachment = event.target.files[0] || null;
};

const submit = () => {
  form.post(route("support-tickets.store"), {
    forceFormData: true,
  });
};

if (form.object_type) {
  loadObjects();
}
</script>

<template>
  <Layout>
    <PageHeader :title="$t('support_tickets.create_form.title')" :pageTitle="$t('support_tickets.create_form.page_title')" />

    <BRow class="justify-content-center">
      <BCol lg="8">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('support_tickets.create_form.title') }}</h5>
          </BCardHeader>
          <BCardBody>
            <form @submit.prevent="submit">
              <!-- Step 1: Category -->
              <div class="mb-4">
                <label class="form-label fw-semibold">
                  <span class="badge bg-primary-subtle text-primary me-2">1</span>
                  {{ $t('support_tickets.create_form.step_category') }}
                </label>
                <Multiselect
                  v-model="form.category"
                  :options="categoryOptions"
                  :searchable="true"
                  :close-on-select="true"
                  :placeholder="$t('support_tickets.create_form.category_placeholder')"
                  :class="{ 'is-invalid': form.errors.category }"
                />
                <InputError :message="form.errors.category" />
              </div>

              <!-- Step 2: Related Object -->
              <div class="mb-4">
                <label class="form-label fw-semibold">
                  <span class="badge bg-primary-subtle text-primary me-2">2</span>
                  {{ $t('support_tickets.create_form.step_object') }}
                </label>
                <BRow class="g-2">
                  <BCol md="6">
                    <Multiselect
                      v-model="form.object_type"
                      :options="objectTypeOptions"
                      :searchable="true"
                      :close-on-select="true"
                      :placeholder="$t('support_tickets.create_form.object_type_placeholder')"
                      :class="{ 'is-invalid': form.errors.object_type }"
                    />
                    <InputError :message="form.errors.object_type" />
                  </BCol>
                  <BCol md="6">
                    <Multiselect
                      v-model="form.object_id"
                      :options="objectOptions"
                      :searchable="true"
                      :close-on-select="true"
                      :disabled="!form.object_type || loadingObjects"
                      :placeholder="loadingObjects ? $t('support_tickets.create_form.loading_objects') : $t('support_tickets.create_form.object_id_placeholder')"
                      :class="{ 'is-invalid': form.errors.object_id }"
                    />
                    <InputError :message="form.errors.object_id" />
                    <div v-if="form.object_type && !loadingObjects && objectOptions.length === 0" class="form-text text-warning">
                      {{ $t('support_tickets.create_form.no_objects') }}
                    </div>
                    <div v-else class="form-text">{{ $t('support_tickets.create_form.object_hint') }}</div>
                  </BCol>
                </BRow>
              </div>

              <!-- Step 3: Subject & Message -->
              <div class="mb-4">
                <label class="form-label fw-semibold">
                  <span class="badge bg-primary-subtle text-primary me-2">3</span>
                  {{ $t('support_tickets.create_form.step_message') }}
                </label>
                <div class="mb-3">
                  <label class="form-label">{{ $t('support_tickets.create_form.subject') }} <span class="text-danger">*</span></label>
                  <input
                    v-model="form.subject"
                    type="text"
                    class="form-control"
                    :placeholder="$t('support_tickets.create_form.subject_placeholder')"
                    :class="{ 'is-invalid': form.errors.subject }"
                  />
                  <InputError :message="form.errors.subject" />
                </div>
                <div>
                  <label class="form-label">{{ $t('support_tickets.create_form.message') }} <span class="text-danger">*</span></label>
                  <textarea
                    v-model="form.message"
                    class="form-control"
                    rows="5"
                    :placeholder="$t('support_tickets.create_form.message_placeholder')"
                    :class="{ 'is-invalid': form.errors.message }"
                  ></textarea>
                  <InputError :message="form.errors.message" />
                </div>
              </div>

              <!-- Step 4: Attachment -->
              <div class="mb-4">
                <label class="form-label fw-semibold">
                  <span class="badge bg-secondary-subtle text-secondary me-2">4</span>
                  {{ $t('support_tickets.create_form.step_attachment') }}
                </label>
                <input
                  type="file"
                  class="form-control"
                  accept=".pdf,image/*,.doc,.docx,.xls,.xlsx,.csv,.txt"
                  @change="onAttachmentChange"
                  :class="{ 'is-invalid': form.errors.attachment }"
                />
                <div class="form-text">{{ $t('support_tickets.create_form.attachment_hint') }}</div>
                <InputError :message="form.errors.attachment" />
              </div>

              <div class="hstack gap-2 justify-content-end">
                <Link :href="route('support-tickets.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
                <BButton variant="success" type="submit" :disabled="form.processing">
                  <i class="ri-send-plane-line align-bottom me-1"></i>
                  {{ $t('support_tickets.create_form.submit') }}
                </BButton>
              </div>
            </form>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
