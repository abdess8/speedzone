<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import RoleForm from './Partials/RoleForm.vue';

defineProps({
  permissionGroups: { type: Array, default: () => [] },
});

const form = useForm({
  label: '',
  permissions: [],
});

const submit = () => {
  form.post(route('team.roles.store'));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('team.roles.create_title')" :pageTitle="$t('team.roles.title')" />

    <form @submit.prevent="submit">
      <RoleForm :form="form" :permission-groups="permissionGroups" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('team.roles.index')" class="btn btn-light">
              {{ $t('common.cancel') }}
            </Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.create') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
