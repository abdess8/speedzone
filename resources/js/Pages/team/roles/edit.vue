<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import RoleForm from './Partials/RoleForm.vue';

const props = defineProps({
  role: { type: Object, required: true },
  permissionGroups: { type: Array, default: () => [] },
});

const confirmingDelete = ref(false);

const form = useForm({
  label: props.role.label,
  permissions: [...props.role.permissions],
});

const submit = () => {
  form.put(route('team.roles.update', props.role.id));
};

const destroy = () => {
  confirmingDelete.value = false;
  router.delete(route('team.roles.destroy', props.role.id));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('team.roles.edit_title')" :pageTitle="$t('team.roles.title')" />

    <form @submit.prevent="submit">
      <RoleForm :form="form" :permission-groups="permissionGroups" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <BButton
              v-if="!role.members_count"
              type="button"
              variant="soft-danger"
              class="me-auto"
              @click="confirmingDelete = true"
            >
              <i class="ri-delete-bin-line align-bottom me-1"></i> {{ $t('common.delete') }}
            </BButton>

            <Link :href="route('team.roles.index')" class="btn btn-light">
              {{ $t('common.cancel') }}
            </Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.save') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>

    <BModal
      v-model="confirmingDelete"
      :title="$t('team.roles.delete_confirm_title', { name: role.label })"
      hide-footer
    >
      <p class="text-muted">{{ $t('team.roles.delete_confirm_text') }}</p>
      <div class="hstack gap-2 justify-content-end">
        <BButton variant="light" @click="confirmingDelete = false">{{ $t('common.cancel') }}</BButton>
        <BButton variant="danger" @click="destroy">{{ $t('common.delete') }}</BButton>
      </div>
    </BModal>
  </Layout>
</template>
