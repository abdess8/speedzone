<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import { guideI18nKey, hasGuide } from '@/guides/index.js';
import { roleLabel as sharedRoleLabel } from '@/utils/roleLabel';

/**
 * Which roles are invited to which interactive guide.
 *
 * A grid rather than a section of the role form: the state worth seeing is a
 * column ("who gets the bulk import guide"), and a guide assigned to nobody
 * behaves differently from one assigned to a single role — a distinction that
 * is invisible when you edit one role at a time.
 */

const props = defineProps({
  roles: { type: Array, default: () => [] },
  /** [{ key, icon, category, minutes, permissions, role_ids }] */
  guides: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({ update: false }) },
});

const { t } = useI18n();

const form = useForm({
  assignments: Object.fromEntries(props.guides.map((guide) => [guide.key, [...guide.role_ids]])),
});

const rows = computed(() =>
  props.guides.map((guide) => {
    const i18nKey = guideI18nKey(guide.key);

    return {
      ...guide,
      title: t(`guides.catalog.${i18nKey}.title`),
      summary: t(`guides.catalog.${i18nKey}.summary`),
      // A guide with no client definition cannot be played; saying so beats
      // letting an administrator assign roles to something that never runs.
      playable: hasGuide(guide.key),
    };
  })
);

function roleLabel(role) {
  return sharedRoleLabel(role, t);
}

function isAssigned(guideKey, roleId) {
  return form.assignments[guideKey].includes(roleId);
}

function toggle(guideKey, roleId) {
  const list = form.assignments[guideKey];
  const index = list.indexOf(roleId);

  if (index === -1) {
    list.push(roleId);
  } else {
    list.splice(index, 1);
  }
}

/** A guide nobody is assigned to stays visible to everyone the permissions allow. */
function isUnrestricted(guideKey) {
  return form.assignments[guideKey].length === 0;
}

function toggleRoleColumn(roleId) {
  const everyRow = rows.value.every((row) => isAssigned(row.key, roleId));

  rows.value.forEach((row) => {
    const assigned = isAssigned(row.key, roleId);

    if (everyRow && assigned) {
      toggle(row.key, roleId);
    } else if (!everyRow && !assigned) {
      toggle(row.key, roleId);
    }
  });
}

function submit() {
  form.put(route('roles.guides.update'), { preserveScroll: true });
}
</script>

<template>
  <Layout>
    <PageHeader :title="$t('guides.access.title')" :pageTitle="$t('roles.page_title')" />

    <BCard no-body>
      <BCardHeader class="d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1">
          <h5 class="card-title mb-1">{{ $t('guides.access.title') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('guides.access.subtitle') }}</p>
        </div>
        <Link :href="route('roles.index')" class="btn btn-light btn-sm">
          <i class="ri-arrow-left-line align-bottom me-1"></i>
          {{ $t('guides.access.back_to_roles') }}
        </Link>
      </BCardHeader>

      <BCardBody class="p-0">
        <div class="table-responsive">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th style="min-width: 18rem">{{ $t('guides.access.guide_column') }}</th>
                <th
                  v-for="role in roles"
                  :key="role.id"
                  class="text-center"
                  role="button"
                  :title="$t('guides.access.toggle_column')"
                  @click="can.update && toggleRoleColumn(role.id)"
                >
                  {{ roleLabel(role) }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.key">
                <td>
                  <div class="d-flex align-items-start gap-2">
                    <i :class="row.icon" class="fs-18 text-primary mt-1"></i>
                    <div>
                      <span class="fw-medium">{{ row.title }}</span>
                      <span
                        v-if="isUnrestricted(row.key)"
                        class="badge bg-warning-subtle text-warning ms-2"
                      >
                        {{ $t('guides.access.unrestricted') }}
                      </span>
                      <span v-if="!row.playable" class="badge bg-light text-body border ms-2">
                        {{ $t('guides.access.not_playable') }}
                      </span>
                      <div class="text-muted fs-12">
                        {{ $t(`guides.categories.${row.category}`) }} ·
                        <code>{{ row.permissions.join(' | ') }}</code>
                      </div>
                    </div>
                  </div>
                </td>

                <td v-for="role in roles" :key="role.id" class="text-center">
                  <div class="form-check d-inline-block">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :id="`guide-${row.key}-${role.id}`"
                      :disabled="!can.update"
                      :checked="isAssigned(row.key, role.id)"
                      @change="toggle(row.key, role.id)"
                    />
                    <label class="visually-hidden" :for="`guide-${row.key}-${role.id}`">
                      {{ row.title }} — {{ roleLabel(role) }}
                    </label>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BCardBody>
    </BCard>

    <!-- Says out loud what an empty line means; otherwise unchecking every role
         looks like it should hide the guide, and it does the opposite. -->
    <div class="alert alert-info d-flex align-items-start gap-2">
      <i class="ri-information-line fs-18"></i>
      <div>
        <p class="mb-1">{{ $t('guides.access.unrestricted_help') }}</p>
        <p class="mb-0">{{ $t('guides.access.permission_help') }}</p>
      </div>
    </div>

    <div v-if="can.update" class="hstack gap-2 justify-content-end mb-4">
      <Link :href="route('roles.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
      <BButton variant="success" :disabled="form.processing" @click="submit">
        <i class="ri-save-line align-bottom me-1"></i>
        {{ $t('common.save') }}
      </BButton>
    </div>
  </Layout>
</template>
