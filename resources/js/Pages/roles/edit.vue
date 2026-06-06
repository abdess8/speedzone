<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import RoleForm from "./Partials/RoleForm.vue";

const props = defineProps({
  role: { type: Object, required: true },
  permissionGroups: { type: Array, default: () => [] },
});

const form = useForm({
  name: props.role.name,
  permission_ids: [...props.role.permission_ids],
});

const submit = () => {
  form.put(route("roles.update", props.role.id));
};
</script>

<template>
  <Layout>
    <PageHeader title="Edit Role" pageTitle="Role Management" />
    <form @submit.prevent="submit">
      <RoleForm :form="form" :permission-groups="permissionGroups" />

      <div class="hstack gap-2 justify-content-end mb-4">
        <Link :href="route('roles.index')" class="btn btn-light">Cancel</Link>
        <BButton type="submit" variant="success" :disabled="form.processing">
          <i class="ri-save-line align-bottom me-1"></i> Update Role
        </BButton>
      </div>
    </form>
  </Layout>
</template>
