<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import RoleForm from "./Partials/RoleForm.vue";

defineProps({
  permissionGroups: { type: Array, default: () => [] },
});

const form = useForm({
  name: "",
  permission_ids: [],
});

const submit = () => {
  form.post(route("roles.store"));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('roles.create_title')" :pageTitle="$t('roles.page_title')" />
    <form @submit.prevent="submit">
      <RoleForm :form="form" :permission-groups="permissionGroups" />

      <div class="hstack gap-2 justify-content-end mb-4">
        <Link :href="route('roles.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
        <BButton type="submit" variant="success" :disabled="form.processing">
          <i class="ri-save-line align-bottom me-1"></i> {{ $t('roles.create') }}
        </BButton>
      </div>
    </form>
  </Layout>
</template>
