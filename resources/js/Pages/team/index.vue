<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';

const { t } = useI18n();

const props = defineProps({
  members: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const confirming = ref(null);

const hasMembers = computed(() => props.members.length > 0);

const askSuspend = (member) => {
  confirming.value = member;
};

const suspend = () => {
  const member = confirming.value;
  confirming.value = null;
  router.put(route('team.suspend', member.id), {}, { preserveScroll: true });
};

const reactivate = (member) => {
  router.put(route('team.reactivate', member.id), {}, { preserveScroll: true });
};

const lastActivityLabel = (member) => {
  if (!member.last_activity) {
    return t('team.sessions.never');
  }

  return new Date(member.last_activity * 1000).toLocaleString();
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('team.title')" :pageTitle="$t('sidebar.my_shop')" />

    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <div>
              <h5 class="card-title mb-1">{{ $t('team.title') }}</h5>
              <p class="text-muted mb-0 fs-13">{{ $t('team.subtitle') }}</p>
            </div>
            <div class="hstack gap-2">
              <Link
                v-if="can.manage_roles"
                :href="route('team.roles.index')"
                class="btn btn-light btn-sm btn-icon"
                :title="$t('team.manage_roles')"
                :aria-label="$t('team.manage_roles')"
              >
                <i class="ri-shield-user-line align-bottom"></i>
              </Link>
              <Link
                v-if="can.create"
                :href="route('team.create')"
                class="btn btn-success btn-sm btn-icon"
                :title="$t('team.add')"
                :aria-label="$t('team.add')"
              >
                <i class="ri-user-add-line align-bottom"></i>
              </Link>
            </div>
          </BCardHeader>

          <BCardBody>
            <div v-if="hasMembers" class="table-responsive">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light">
                  <tr>
                    <th scope="col">{{ $t('team.fields.first_name') }}</th>
                    <th scope="col">{{ $t('team.fields.roles') }}</th>
                    <th scope="col">{{ $t('team.fields.stores') }}</th>
                    <th scope="col">{{ $t('team.fields.sessions') }}</th>
                    <th scope="col">{{ $t('team.fields.status') }}</th>
                    <th scope="col" class="text-end">{{ $t('common.actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="member in members" :key="member.id">
                    <td>
                      <h6 class="mb-0">{{ member.name }}</h6>
                      <small class="text-muted">{{ member.email }}</small>
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
                    <td>
                      <span
                        v-for="store in member.stores"
                        :key="store"
                        class="badge bg-light text-body me-1"
                      >
                        {{ store }}
                      </span>
                    </td>
                    <td>
                      <span v-if="member.active_sessions" class="text-success fw-medium">
                        {{ $t('team.sessions.count', { count: member.active_sessions }) }}
                      </span>
                      <span v-else class="text-muted">{{ $t('team.sessions.none') }}</span>
                      <div class="text-muted fs-12">{{ lastActivityLabel(member) }}</div>
                    </td>
                    <td>
                      <span class="badge" :class="member.status_class">
                        {{ $t(`user_statuses.${member.status}`) }}
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="hstack gap-1 justify-content-end">
                        <Link
                          :href="route('team.edit', member.id)"
                          class="btn btn-sm btn-light btn-icon"
                          :title="$t('common.edit')"
                          :aria-label="$t('common.edit')"
                        >
                          <i class="ri-pencil-line align-bottom"></i>
                        </Link>
                        <BButton
                          v-if="member.status === 'SUSPENDED'"
                          variant="soft-success"
                          size="sm"
                          class="btn-icon"
                          :title="$t('team.actions.reactivate')"
                          :aria-label="$t('team.actions.reactivate')"
                          @click="reactivate(member)"
                        >
                          <i class="ri-play-circle-line align-bottom"></i>
                        </BButton>
                        <BButton
                          v-else
                          variant="soft-danger"
                          size="sm"
                          class="btn-icon"
                          :title="$t('team.actions.suspend')"
                          :aria-label="$t('team.actions.suspend')"
                          @click="askSuspend(member)"
                        >
                          <i class="ri-pause-circle-line align-bottom"></i>
                        </BButton>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="text-center py-5">
              <i class="ri-team-line fs-1 text-muted"></i>
              <p class="text-muted mt-2 mb-1">{{ $t('team.empty') }}</p>
              <p class="text-muted fs-13">{{ $t('team.empty_hint') }}</p>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BModal
      :model-value="confirming !== null"
      :title="$t('team.suspend_confirm_title', { name: confirming?.name })"
      hide-footer
      @update:model-value="confirming = null"
    >
      <p class="text-muted">{{ $t('team.suspend_confirm_text') }}</p>
      <div class="hstack gap-2 justify-content-end">
        <BButton variant="light" @click="confirming = null">{{ $t('common.cancel') }}</BButton>
        <BButton variant="danger" @click="suspend">{{ $t('team.actions.suspend') }}</BButton>
      </div>
    </BModal>
  </Layout>
</template>
