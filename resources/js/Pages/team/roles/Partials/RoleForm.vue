<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
  form: { type: Object, required: true },
  permissionGroups: { type: Array, default: () => [] },
});

const { t, te } = useI18n();

const allNames = computed(() =>
  props.permissionGroups.flatMap((group) => group.permissions.map((permission) => permission.name))
);

/**
 * Permission names are technical (orders.read.own); fall back to the raw name
 * when a translation is missing so a newly added permission is still usable.
 */
const resourceLabel = (resource) =>
  te(`team.resources.${resource}`) ? t(`team.resources.${resource}`) : resource;

const permissionLabel = (permission) => {
  const action = te(`team.actions_labels.${permission.action}`)
    ? t(`team.actions_labels.${permission.action}`)
    : permission.action;

  if (!permission.scope) {
    return action;
  }

  const scope = te(`team.scopes.${permission.scope}`)
    ? t(`team.scopes.${permission.scope}`)
    : permission.scope;

  return `${action} — ${scope}`;
};

const toggle = (name) => {
  const index = props.form.permissions.indexOf(name);

  if (index === -1) {
    props.form.permissions.push(name);
  } else {
    props.form.permissions.splice(index, 1);
  }
};

const selectAll = () => {
  props.form.permissions = [...allNames.value];
};

const clearAll = () => {
  props.form.permissions = [];
};
</script>

<template>
  <BRow>
    <BCol xl="8" class="mx-auto">
      <BCard no-body>
        <BCardBody>
          <label class="form-label" for="label">{{ $t('team.roles.fields.label') }}</label>
          <input
            id="label"
            v-model="form.label"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': form.errors.label }"
            :placeholder="$t('team.roles.hints.label')"
          />
          <div class="invalid-feedback">{{ form.errors.label }}</div>
        </BCardBody>
      </BCard>

      <BCard no-body>
        <BCardHeader class="d-flex align-items-center justify-content-between">
          <h5 class="card-title mb-0">{{ $t('team.roles.fields.permissions') }}</h5>
          <div class="hstack gap-2">
            <BButton type="button" variant="light" size="sm" @click="selectAll">
              {{ $t('team.roles.select_all') }}
            </BButton>
            <BButton type="button" variant="light" size="sm" @click="clearAll">
              {{ $t('team.roles.clear_all') }}
            </BButton>
          </div>
        </BCardHeader>
        <BCardBody>
          <p class="text-muted fs-13">{{ $t('team.roles.hints.permissions') }}</p>

          <div v-if="form.errors.permissions" class="text-danger fs-13 mb-2">
            {{ form.errors.permissions }}
          </div>

          <div v-for="group in permissionGroups" :key="group.resource" class="mb-4">
            <h6 class="text-uppercase fs-12 text-muted mb-2">
              {{ resourceLabel(group.resource) }}
            </h6>
            <BRow class="g-2">
              <BCol v-for="permission in group.permissions" :key="permission.id" md="6">
                <div class="form-check">
                  <input
                    :id="`permission-${permission.id}`"
                    class="form-check-input"
                    type="checkbox"
                    :checked="form.permissions.includes(permission.name)"
                    @change="toggle(permission.name)"
                  />
                  <label class="form-check-label" :for="`permission-${permission.id}`">
                    {{ permissionLabel(permission) }}
                  </label>
                </div>
              </BCol>
            </BRow>
          </div>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
