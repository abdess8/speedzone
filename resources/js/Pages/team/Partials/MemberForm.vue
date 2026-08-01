<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  form: { type: Object, required: true },
  stores: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  isEdit: { type: Boolean, default: false },
});

const toggle = (list, id) => {
  const index = list.indexOf(id);

  if (index === -1) {
    list.push(id);
  } else {
    list.splice(index, 1);
  }
};
</script>

<template>
  <BRow>
    <BCol xl="8" class="mx-auto">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('team.sections.identity') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="6">
              <label class="form-label" for="first_name">{{ $t('team.fields.first_name') }}</label>
              <input
                id="first_name"
                v-model="form.first_name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': form.errors.first_name }"
              />
              <div class="invalid-feedback">{{ form.errors.first_name }}</div>
            </BCol>

            <BCol md="6">
              <label class="form-label" for="last_name">{{ $t('team.fields.last_name') }}</label>
              <input
                id="last_name"
                v-model="form.last_name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': form.errors.last_name }"
              />
              <div class="invalid-feedback">{{ form.errors.last_name }}</div>
            </BCol>

            <BCol md="6">
              <label class="form-label" for="email">{{ $t('team.fields.email') }}</label>
              <input
                id="email"
                v-model="form.email"
                type="email"
                class="form-control"
                :class="{ 'is-invalid': form.errors.email }"
              />
              <div class="invalid-feedback">{{ form.errors.email }}</div>
            </BCol>

            <BCol md="6">
              <label class="form-label" for="phone_number">{{ $t('team.fields.phone_number') }}</label>
              <input
                id="phone_number"
                v-model="form.phone_number"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': form.errors.phone_number }"
              />
              <div class="invalid-feedback">{{ form.errors.phone_number }}</div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('team.sections.access') }}</h5>
        </BCardHeader>
        <BCardBody>
          <div class="mb-4">
            <label class="form-label">{{ $t('team.fields.stores') }}</label>
            <p class="text-muted fs-13">{{ $t('team.hints.stores') }}</p>

            <div v-if="stores.length" class="d-flex flex-column gap-2">
              <div v-for="store in stores" :key="store.id" class="form-check">
                <input
                  :id="`store-${store.id}`"
                  class="form-check-input"
                  type="checkbox"
                  :checked="form.store_ids.includes(store.id)"
                  @change="toggle(form.store_ids, store.id)"
                />
                <label class="form-check-label" :for="`store-${store.id}`">
                  {{ store.name }}
                  <span v-if="store.is_default" class="badge bg-primary-subtle text-primary ms-1">
                    {{ $t('stores.badges.default') }}
                  </span>
                </label>
              </div>
            </div>
            <p v-else class="text-danger fs-13 mb-0">{{ $t('team.errors.no_store') }}</p>

            <div v-if="form.errors.store_ids" class="text-danger fs-13 mt-1">
              {{ form.errors.store_ids }}
            </div>
          </div>

          <div>
            <label class="form-label">{{ $t('team.fields.roles') }}</label>
            <p class="text-muted fs-13">{{ $t('team.hints.roles') }}</p>

            <div v-if="roles.length" class="d-flex flex-column gap-2">
              <div v-for="role in roles" :key="role.id" class="form-check">
                <input
                  :id="`role-${role.id}`"
                  class="form-check-input"
                  type="checkbox"
                  :checked="form.role_ids.includes(role.id)"
                  @change="toggle(form.role_ids, role.id)"
                />
                <label class="form-check-label" :for="`role-${role.id}`">
                  {{ role.label }}
                  <span class="text-muted fs-12 ms-1">
                    {{ $t('team.roles.permissions_count', { count: role.permissions_count }) }}
                  </span>
                </label>
              </div>
            </div>
            <p v-else class="text-danger fs-13 mb-1">{{ $t('team.errors.no_role') }}</p>

            <Link :href="route('team.roles.create')" class="btn btn-link btn-sm ps-0">
              <i class="ri-add-line align-bottom me-1"></i> {{ $t('team.roles.add') }}
            </Link>

            <div v-if="form.errors.role_ids" class="text-danger fs-13 mt-1">
              {{ form.errors.role_ids }}
            </div>
          </div>
        </BCardBody>
      </BCard>

      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('team.sections.security') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="6">
              <label class="form-label" for="password">{{ $t('team.fields.password') }}</label>
              <input
                id="password"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                class="form-control"
                :class="{ 'is-invalid': form.errors.password }"
              />
              <div class="invalid-feedback">{{ form.errors.password }}</div>
              <p class="text-muted fs-13 mt-1 mb-0">
                {{ isEdit ? $t('team.hints.password_edit') : $t('team.hints.password') }}
              </p>
            </BCol>

            <BCol md="6">
              <label class="form-label" for="password_confirmation">
                {{ $t('team.fields.password_confirmation') }}
              </label>
              <input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                class="form-control"
              />
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
