<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import TransitionBadge from '../bulk-status/Partials/TransitionBadge.vue';

/**
 * The `statut actuel → nouveau statut` matrix, one tab per entity.
 *
 * Rows are the transitions the workflow actually permits, columns the platform
 * roles. A cell is saved on its own, immediately: an administrator narrowing one
 * role's reach should not have to press Save on a grid where a colleague may
 * have changed another cell in the meantime.
 */
const { t } = useI18n();

const props = defineProps({
  entities: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  grants: { type: Object, default: () => ({}) },
});

const activeEntity = ref(props.entities[0]?.value ?? null);
const search = ref('');
/** Cells with a request in flight, so a double click cannot queue two writes. */
const saving = reactive({});

const local = reactive({ ...props.grants });

const entity = computed(() => props.entities.find((row) => row.value === activeEntity.value) ?? null);

const transitions = computed(() => {
  const term = search.value.trim().toLowerCase();
  const rows = entity.value?.transitions ?? [];

  if (!term) {
    return rows;
  }

  return rows.filter((row) => `${row.from.label} ${row.to.label}`.toLowerCase().includes(term));
});

const isGranted = (permission, roleId) => (local[permission] ?? []).includes(roleId);

const toggle = (permission, roleId) => {
  const key = `${permission}:${roleId}`;

  if (saving[key]) {
    return;
  }

  const granted = !isGranted(permission, roleId);
  saving[key] = true;

  // Optimistic: the grid has to feel like a checkbox, and the server answer is
  // reconciled below either way.
  local[permission] = granted
    ? [...(local[permission] ?? []), roleId]
    : (local[permission] ?? []).filter((id) => id !== roleId);

  router.put(
    route('status-transition-permissions.update'),
    { permission, role_id: roleId, granted },
    {
      preserveScroll: true,
      preserveState: true,
      onError: () => {
        local[permission] = granted
          ? (local[permission] ?? []).filter((id) => id !== roleId)
          : [...(local[permission] ?? []), roleId];

        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'error',
          title: t('bulk_status.permissions.admin_only'),
          timer: 3000,
          showConfirmButton: false,
        });
      },
      onFinish: () => {
        saving[key] = false;
      },
    }
  );
};
</script>

<template>
  <Layout>
    <PageHeader
      :title="$t('bulk_status.permissions.title')"
      :pageTitle="$t('bulk_status.permissions.page_title')"
    />

    <BCard no-body>
      <BCardBody>
        <p class="text-muted mb-2">{{ $t('bulk_status.permissions.subtitle') }}</p>
        <div class="alert alert-info d-flex gap-2 py-2 mb-0">
          <i class="ri-information-line fs-16"></i>
          <div class="small">
            <div>{{ $t('bulk_status.permissions.help') }}</div>
            <div class="text-muted">{{ $t('bulk_status.permissions.note') }}</div>
          </div>
        </div>
      </BCardBody>
    </BCard>

    <BCard no-body>
      <BCardBody class="border-bottom d-flex flex-wrap align-items-center gap-3">
        <ul class="nav nav-pills">
          <li v-for="row in entities" :key="row.value" class="nav-item">
            <button
              type="button"
              class="nav-link"
              :class="{ active: activeEntity === row.value }"
              @click="activeEntity = row.value"
            >
              {{ row.label }}
              <span class="badge bg-light text-body ms-1">{{ row.transitions.length }}</span>
            </button>
          </li>
        </ul>

        <div class="input-group ms-auto search-box">
          <span class="input-group-text"><i class="ri-search-line"></i></span>
          <input
            v-model="search"
            type="search"
            class="form-control"
            :placeholder="$t('bulk_status.permissions.search')"
          />
        </div>
      </BCardBody>

      <BCardBody>
        <p v-if="transitions.length === 0" class="text-muted text-center py-5 mb-0">
          {{ $t('bulk_status.permissions.empty') }}
        </p>

        <div v-else class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ $t('bulk_status.permissions.transition') }}</th>
                <th v-for="role in roles" :key="role.id" class="text-center">{{ role.name }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in transitions" :key="row.name">
                <td>
                  <TransitionBadge :from="row.from" :to="row.to" />
                  <div class="text-muted fs-11 font-monospace">{{ row.name }}</div>
                </td>
                <td v-for="role in roles" :key="role.id" class="text-center">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :checked="isGranted(row.name, role.id)"
                    :disabled="saving[`${row.name}:${role.id}`]"
                    :aria-label="`${row.from.label} → ${row.to.label} · ${role.name}`"
                    @change="toggle(row.name, role.id)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BCardBody>
    </BCard>
  </Layout>
</template>

<style scoped>
.search-box {
  max-width: 20rem;
}
</style>
