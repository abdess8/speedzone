<script setup>
import { computed, ref, watch } from 'vue';
import Multiselect from '@vueform/multiselect';
import '@vueform/multiselect/themes/default.css';
import { Ckeditor as CKEditor } from '@ckeditor/ckeditor5-vue';
import {
  BlockQuote,
  Bold,
  ClassicEditor,
  Essentials,
  FontColor,
  FontSize,
  Heading,
  Italic,
  Link,
  List,
  Paragraph,
  Underline,
} from 'ckeditor5';
import 'ckeditor5/ckeditor5.css';
import { useI18n } from 'vue-i18n';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  form: { type: Object, required: true },
  types: { type: Array, default: () => [] },
  formats: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  selectedUsers: { type: Array, default: () => [] },
});

const { t } = useI18n();

/** Marker meaning "no restriction on this dimension". Mirrors Alert::EVERYONE. */
const EVERYONE = 'all';

const editor = ClassicEditor;

/**
 * The editor is loaded with exactly the plugins App\Support\AlertHtml keeps.
 *
 * The stock classic build carries no colour or size plugin, so those buttons
 * would quietly disappear from the toolbar; and offering anything the sanitiser
 * strips would let an administrator compose a message that loses parts of
 * itself the moment it is saved.
 */
const editorConfig = {
  plugins: [
    Essentials,
    Paragraph,
    Heading,
    Bold,
    Italic,
    Underline,
    FontColor,
    FontSize,
    List,
    Link,
    BlockQuote,
  ],
  toolbar: [
    'heading',
    '|',
    'bold',
    'italic',
    'underline',
    'fontColor',
    'fontSize',
    '|',
    'bulletedList',
    'numberedList',
    '|',
    'link',
    'blockQuote',
    '|',
    'undo',
    'redo',
  ],
  heading: {
    options: [
      { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
      { model: 'heading2', view: 'h2', title: 'Heading', class: 'ck-heading_heading2' },
      { model: 'heading3', view: 'h3', title: 'Subheading', class: 'ck-heading_heading3' },
    ],
  },
  fontSize: {
    options: [12, 14, 'default', 18, 22],
    supportAllValues: false,
  },
  link: {
    // An announcement goes out to people who did not ask for it, so anything
    // it points at opens away from the application.
    addTargetToExternalLinks: true,
  },
};

const isBanner = computed(() => props.form.display_format === 'banner');

// A modal has to be closable, so the toggle is meaningless there and the stored
// value is realigned to avoid saving a contradiction.
watch(isBanner, (banner) => {
  if (!banner) {
    props.form.is_dismissible = true;
  }
});

/**
 * A dimension is either open to everyone or a list of specific values, so the
 * "all" box and the individual boxes clear each other rather than stacking.
 */
const toggleEveryone = (field, on) => {
  props.form[field] = on ? [EVERYONE] : [];
};

const isEveryone = (field) => props.form[field].includes(EVERYONE);

const toggleValue = (field, value) => {
  const current = props.form[field].filter((entry) => entry !== EVERYONE);
  const index = current.indexOf(value);

  if (index === -1) {
    current.push(value);
  } else {
    current.splice(index, 1);
  }

  props.form[field] = current;
};

const isSelected = (field, value) => props.form[field].includes(value);

/** Options already known to the picker, so saved recipients render by name. */
const userOptions = ref([...props.selectedUsers]);

const searchUsers = async (query) => {
  const response = await fetch(`${route('alerts.users')}?q=${encodeURIComponent(query ?? '')}`, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });

  if (!response.ok) {
    return userOptions.value;
  }

  const { data } = await response.json();

  // Keep the already-selected people in the list; dropping them would blank out
  // their labels as soon as the reader types.
  const merged = [...props.selectedUsers, ...data];
  userOptions.value = merged.filter(
    (option, index) => merged.findIndex((other) => other.value === option.value) === index,
  );

  return userOptions.value;
};

const labelsFor = (field, options) =>
  props.form[field]
    .map((value) => options.find((option) => String(option.value) === String(value))?.label)
    .filter(Boolean);

/**
 * Plain-language read-out of who this reaches. The targeting rules are easy to
 * get wrong from the checkboxes alone, so the form states the outcome.
 */
const audienceSummary = computed(() => {
  const named = props.form.target_user_ids.length;
  const hasRoles = props.form.target_roles.length > 0;
  const hasCities = props.form.target_cities.length > 0;

  if (!hasRoles || !hasCities) {
    return named ? t('alerts.audience.only_users', { count: named }) : t('alerts.audience.nobody');
  }

  const roles = isEveryone('target_roles')
    ? t('alerts.audience.all_roles')
    : labelsFor('target_roles', props.roles).join(', ');

  const cities = isEveryone('target_cities')
    ? t('alerts.audience.all_cities')
    : labelsFor('target_cities', props.cities).join(', ');

  const broadcast =
    isEveryone('target_roles') && isEveryone('target_cities')
      ? t('alerts.audience.everyone')
      : t('alerts.audience.roles_in_cities', { roles, cities });

  return named ? `${broadcast} ${t('alerts.audience.plus_users', { count: named })}` : broadcast;
});
</script>

<template>
  <BRow>
    <BCol xl="9" class="mx-auto">
      <BCard no-body class="mb-3">
        <BCardHeader>
          <h5 class="card-title mb-1">{{ $t('alerts.form.appearance') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('alerts.form.appearance_hint') }}</p>
        </BCardHeader>
        <BCardBody>
          <label class="form-label">{{ $t('alerts.table.type') }}</label>
          <div class="d-flex flex-wrap gap-2 mb-4">
            <button
              v-for="type in types"
              :key="type.value"
              type="button"
              class="btn alert-type-choice"
              :class="
                form.type === type.value ? `btn-${type.color}` : `btn-outline-${type.color}`
              "
              @click="form.type = type.value"
            >
              <i :class="type.icon" class="align-bottom me-1"></i>
              {{ type.label }}
            </button>
          </div>
          <InputError :message="form.errors.type" />

          <label class="form-label">{{ $t('alerts.table.format') }}</label>
          <BRow class="g-3">
            <BCol md="6" v-for="format in formats" :key="format.value">
              <button
                type="button"
                class="w-100 text-start p-3 border rounded alert-format-choice"
                :class="
                  form.display_format === format.value
                    ? 'border-primary bg-primary-subtle'
                    : 'border-light'
                "
                @click="form.display_format = format.value"
              >
                <span class="d-flex align-items-center gap-2 fw-semibold mb-1">
                  <i :class="format.icon"></i>
                  {{ format.label }}
                </span>
                <span class="text-muted fs-13">{{ format.description }}</span>
              </button>
            </BCol>
          </BRow>
          <InputError :message="form.errors.display_format" />

          <div v-if="isBanner" class="form-check form-switch fs-15 mt-3">
            <input
              id="alertDismissible"
              v-model="form.is_dismissible"
              class="form-check-input"
              type="checkbox"
              role="switch"
            />
            <label class="form-check-label" for="alertDismissible">
              {{ $t('alerts.form.dismissible') }}
            </label>
            <div class="form-text">{{ $t('alerts.form.dismissible_hint') }}</div>
          </div>
          <p v-else class="text-muted fs-13 mt-3 mb-0">
            <i class="ri-information-line align-bottom me-1"></i>
            {{ $t('alerts.form.dismissible_modal_note') }}
          </p>
        </BCardBody>
      </BCard>

      <BCard no-body class="mb-3">
        <BCardHeader>
          <h5 class="card-title mb-1">{{ $t('alerts.form.audience') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('alerts.form.audience_hint') }}</p>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-4">
            <BCol md="6">
              <label class="form-label">{{ $t('alerts.form.roles') }}</label>
              <div class="form-check mb-2">
                <input
                  id="allRoles"
                  class="form-check-input"
                  type="checkbox"
                  :checked="isEveryone('target_roles')"
                  @change="toggleEveryone('target_roles', $event.target.checked)"
                />
                <label class="form-check-label fw-semibold" for="allRoles">
                  {{ $t('alerts.form.all_roles') }}
                </label>
              </div>
              <div
                v-for="role in roles"
                :key="role.value"
                class="form-check"
                :class="{ 'opacity-50': isEveryone('target_roles') }"
              >
                <input
                  :id="`role-${role.value}`"
                  class="form-check-input"
                  type="checkbox"
                  :disabled="isEveryone('target_roles')"
                  :checked="isSelected('target_roles', role.value)"
                  @change="toggleValue('target_roles', role.value)"
                />
                <label class="form-check-label" :for="`role-${role.value}`">{{ role.label }}</label>
              </div>
              <InputError :message="form.errors.target_roles" />
            </BCol>

            <BCol md="6">
              <label class="form-label">{{ $t('alerts.form.cities') }}</label>
              <div class="form-check mb-2">
                <input
                  id="allCities"
                  class="form-check-input"
                  type="checkbox"
                  :checked="isEveryone('target_cities')"
                  @change="toggleEveryone('target_cities', $event.target.checked)"
                />
                <label class="form-check-label fw-semibold" for="allCities">
                  {{ $t('alerts.form.all_cities') }}
                </label>
              </div>
              <Multiselect
                v-if="!isEveryone('target_cities')"
                v-model="form.target_cities"
                mode="tags"
                :options="cities"
                :searchable="true"
                :close-on-select="false"
                :placeholder="$t('alerts.form.cities')"
              />
              <div class="form-text">{{ $t('alerts.form.cities_hint') }}</div>
              <InputError :message="form.errors.target_cities" />
            </BCol>

            <BCol md="12">
              <label class="form-label">{{ $t('alerts.form.users') }}</label>
              <Multiselect
                v-model="form.target_user_ids"
                mode="tags"
                :options="searchUsers"
                :filter-results="false"
                :min-chars="0"
                :resolve-on-load="true"
                :delay="250"
                :searchable="true"
                :close-on-select="false"
                :placeholder="$t('alerts.form.users_placeholder')"
              />
              <div class="form-text">{{ $t('alerts.form.users_hint') }}</div>
              <InputError :message="form.errors.target_user_ids" />
            </BCol>

            <BCol md="12">
              <div class="alert alert-info d-flex gap-2 mb-0 py-2 px-3">
                <i class="ri-group-line mt-1"></i>
                <span>
                  <span class="text-muted">{{ $t('alerts.audience.summary_label') }}</span>
                  <strong class="ms-1">{{ audienceSummary }}</strong>
                </span>
              </div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <BCard no-body class="mb-3">
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('alerts.form.content') }}</h5>
        </BCardHeader>
        <BCardBody>
          <div class="mb-3">
            <label class="form-label">
              {{ $t('alerts.form.title_field') }} <span class="text-danger">*</span>
            </label>
            <input
              v-model="form.title"
              type="text"
              class="form-control"
              :class="{ 'is-invalid': form.errors.title }"
            />
            <InputError :message="form.errors.title" />
          </div>

          <label class="form-label">
            {{ $t('alerts.form.message') }} <span class="text-danger">*</span>
          </label>
          <CKEditor v-model="form.message" :editor="editor" :config="editorConfig" />
          <div class="form-text">{{ $t('alerts.form.message_hint') }}</div>
          <InputError :message="form.errors.message" />
        </BCardBody>
      </BCard>

      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-1">{{ $t('alerts.form.schedule') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('alerts.form.schedule_hint') }}</p>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3 align-items-start">
            <BCol md="6">
              <label class="form-label">
                {{ $t('alerts.form.end_date') }} <span class="text-danger">*</span>
              </label>
              <input
                v-model="form.end_date"
                type="datetime-local"
                class="form-control"
                :class="{ 'is-invalid': form.errors.end_date }"
              />
              <InputError :message="form.errors.end_date" />
            </BCol>
            <BCol md="6">
              <div class="form-check form-switch fs-15 mt-md-4 pt-md-2">
                <input
                  id="alertActive"
                  v-model="form.is_active"
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                />
                <label class="form-check-label" for="alertActive">
                  {{ $t('alerts.form.active') }}
                </label>
              </div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>

<style lang="scss" scoped>
.alert-format-choice {
  background: transparent;
  transition: border-color 0.15s ease, background-color 0.15s ease;

  &:hover {
    border-color: var(--vz-primary) !important;
  }
}

.alert-type-choice {
  min-width: 8.5rem;
}
</style>
