<script setup>
import { computed } from "vue";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
  form: { type: Object, required: true },
  permissionGroups: { type: Array, default: () => [] },
});

const totalPermissions = computed(() =>
  props.permissionGroups.reduce((acc, group) => acc + group.permissions.length, 0)
);

const selectedCount = computed(() => props.form.permission_ids.length);

const allSelected = computed(
  () => totalPermissions.value > 0 && selectedCount.value === totalPermissions.value
);

const isChecked = (id) => props.form.permission_ids.includes(id);

const toggle = (id) => {
  const list = props.form.permission_ids;
  const index = list.indexOf(id);
  if (index === -1) {
    list.push(id);
  } else {
    list.splice(index, 1);
  }
};

const groupIds = (group) => group.permissions.map((p) => p.id);

const groupSelectedCount = (group) =>
  group.permissions.filter((p) => isChecked(p.id)).length;

const isGroupAllSelected = (group) =>
  group.permissions.length > 0 && groupSelectedCount(group) === group.permissions.length;

const toggleGroup = (group) => {
  const ids = groupIds(group);
  if (isGroupAllSelected(group)) {
    props.form.permission_ids = props.form.permission_ids.filter(
      (id) => !ids.includes(id)
    );
  } else {
    const merged = new Set([...props.form.permission_ids, ...ids]);
    props.form.permission_ids = [...merged];
  }
};

const toggleAll = () => {
  if (allSelected.value) {
    props.form.permission_ids = [];
  } else {
    props.form.permission_ids = props.permissionGroups.flatMap((g) => groupIds(g));
  }
};

const badgeClass = (permission) => {
  if (permission.type === "workflow_transition") return "bg-info-subtle text-info";
  if (permission.type === "admin") return "bg-danger-subtle text-danger";
  if (permission.scope === "all") return "bg-primary-subtle text-primary";
  if (permission.scope === "own") return "bg-warning-subtle text-warning";
  return "bg-success-subtle text-success";
};
</script>

<template>
  <BRow>
    <BCol lg="12">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('roles.form.details') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="6">
              <label class="form-label">
                {{ $t('roles.form.role_name') }} <span class="text-danger">*</span>
              </label>
              <input
                type="text"
                class="form-control"
                :placeholder="$t('roles.form.role_name_placeholder')"
                v-model="form.name"
                :class="{ 'is-invalid': form.errors.name }"
              />
              <InputError :message="form.errors.name" />
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <BCard no-body>
        <BCardHeader class="border-bottom-dashed">
          <BRow class="g-3 align-items-center">
            <BCol sm>
              <h5 class="card-title mb-1">{{ $t('roles.form.permissions') }}</h5>
              <p class="text-muted mb-0">
                {{ $t('roles.form.permissions_selected', { selected: selectedCount, total: totalPermissions }) }}
              </p>
            </BCol>
            <BCol sm="auto">
              <div class="form-check form-switch fs-15">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="toggleAllPermissions"
                  :checked="allSelected"
                  @change="toggleAll"
                />
                <label class="form-check-label" for="toggleAllPermissions">
                  {{ $t('roles.form.select_all') }}
                </label>
              </div>
            </BCol>
          </BRow>
          <InputError :message="form.errors.permission_ids" class="d-block" />
        </BCardHeader>

        <BCardBody>
          <div v-if="permissionGroups.length === 0" class="text-center text-muted py-4">
            {{ $t('roles.form.no_permissions') }}
          </div>

          <BRow class="g-3">
            <BCol md="6" xl="4" v-for="group in permissionGroups" :key="group.resource">
              <div class="border rounded h-100 p-3">
                <div class="d-flex align-items-center mb-3">
                  <div class="form-check flex-grow-1">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :id="`group-${group.resource}`"
                      :checked="isGroupAllSelected(group)"
                      @change="toggleGroup(group)"
                    />
                    <label
                      class="form-check-label fw-semibold text-uppercase fs-12 text-muted"
                      :for="`group-${group.resource}`"
                    >
                      {{ group.label }}
                    </label>
                  </div>
                  <span class="badge bg-light text-body">
                    {{ groupSelectedCount(group) }}/{{ group.permissions.length }}
                  </span>
                </div>

                <div
                  v-for="permission in group.permissions"
                  :key="permission.id"
                  class="form-check mb-2"
                >
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :id="`perm-${permission.id}`"
                    :checked="isChecked(permission.id)"
                    @change="toggle(permission.id)"
                  />
                  <label
                    class="form-check-label d-flex align-items-center gap-2"
                    :for="`perm-${permission.id}`"
                  >
                    <span>{{ permission.label }}</span>
                    <span
                      v-if="permission.scope"
                      class="badge"
                      :class="badgeClass(permission)"
                    >
                      {{ permission.scope }}
                    </span>
                  </label>
                  <div class="text-muted fs-11">
                    <code>{{ permission.name }}</code>
                  </div>
                </div>
              </div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
