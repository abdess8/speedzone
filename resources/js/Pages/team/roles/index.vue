<script setup>
import { Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';

defineProps({
  roles: { type: Array, default: () => [] },
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('team.roles.title')" :pageTitle="$t('team.title')" />

    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <div>
              <h5 class="card-title mb-1">{{ $t('team.roles.title') }}</h5>
              <p class="text-muted mb-0 fs-13">{{ $t('team.roles.subtitle') }}</p>
            </div>
            <div class="hstack gap-2">
              <Link
                :href="route('team.index')"
                class="btn btn-light btn-sm text-nowrap"
                :title="$t('team.roles.back')"
                :aria-label="$t('team.roles.back')"
              >
                <i class="ri-arrow-left-line align-bottom"></i>
                <span class="d-none d-sm-inline ms-1">{{ $t('team.roles.back') }}</span>
              </Link>
              <Link
                :href="route('team.roles.create')"
                class="btn btn-success btn-sm text-nowrap"
                :title="$t('team.roles.add')"
                :aria-label="$t('team.roles.add')"
              >
                <i class="ri-add-line align-bottom"></i>
                <span class="d-none d-sm-inline ms-1">{{ $t('team.roles.add') }}</span>
              </Link>
            </div>
          </BCardHeader>

          <BCardBody>
            <BRow v-if="roles.length" class="g-3">
              <BCol v-for="role in roles" :key="role.id" md="6" xl="4">
                <div class="card border h-100 mb-0">
                  <div class="card-body">
                    <h6 class="mb-2">{{ role.label }}</h6>
                    <ul class="list-unstyled text-muted fs-13 mb-3">
                      <li class="mb-1">
                        <i class="ri-shield-check-line align-bottom me-1"></i>
                        {{ $t('team.roles.permissions_count', { count: role.permissions_count }) }}
                      </li>
                      <li>
                        <i class="ri-team-line align-bottom me-1"></i>
                        {{ $t('team.roles.members_count', { count: role.members_count }) }}
                      </li>
                    </ul>
                    <Link :href="route('team.roles.edit', role.id)" class="btn btn-sm btn-light">
                      <i class="ri-settings-3-line align-bottom me-1"></i> {{ $t('common.edit') }}
                    </Link>
                  </div>
                </div>
              </BCol>
            </BRow>

            <div v-else class="text-center py-5">
              <i class="ri-shield-user-line fs-1 text-muted"></i>
              <p class="text-muted mt-2 mb-1">{{ $t('team.roles.empty') }}</p>
              <p class="text-muted fs-13">{{ $t('team.roles.empty_hint') }}</p>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
