<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import AdminSellerCard from '@/Components/AdminSellerCard.vue';
import RoleForm from './Partials/RoleForm.vue';

const props = defineProps({
  permissionGroups: { type: Array, default: () => [] },
  admin: { type: Boolean, default: false },
  sellers: { type: Array, default: () => [] },
  seller: { type: Object, default: null },
});

const form = useForm({
  seller_id: props.seller?.id ?? '',
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
      <AdminSellerCard
        v-if="admin"
        v-model="form.seller_id"
        :sellers="sellers"
        :error="form.errors.seller_id"
        :label="$t('team.roles.admin.seller_field')"
        :placeholder="$t('team.roles.admin.seller_placeholder')"
        :help="$t('team.roles.admin.seller_help')"
      />

      <RoleForm :form="form" :permission-groups="permissionGroups" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('team.roles.index')" class="btn btn-light">
              {{ $t('common.cancel') }}
            </Link>
            <BButton type="submit" variant="success" :disabled="form.processing || (admin && !form.seller_id)">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.create') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
