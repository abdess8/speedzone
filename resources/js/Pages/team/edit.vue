<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import MemberForm from './Partials/MemberForm.vue';

const props = defineProps({
  member: { type: Object, required: true },
  stores: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const form = useForm({
  first_name: props.member.first_name,
  last_name: props.member.last_name,
  email: props.member.email,
  phone_number: props.member.phone_number ?? '',
  password: '',
  password_confirmation: '',
  store_ids: [...props.member.store_ids],
  role_ids: [...props.member.role_ids],
});

const submit = () => {
  form.put(route('team.update', props.member.id));
};

const suspend = () => {
  router.put(route('team.suspend', props.member.id));
};

const reactivate = () => {
  router.put(route('team.reactivate', props.member.id));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('team.edit_title')" :pageTitle="$t('team.title')" />

    <form @submit.prevent="submit">
      <MemberForm :form="form" :stores="stores" :roles="roles" is-edit />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <BButton
              v-if="can.suspend && member.status === 'SUSPENDED'"
              type="button"
              variant="soft-success"
              class="me-auto"
              @click="reactivate"
            >
              {{ $t('team.actions.reactivate') }}
            </BButton>
            <BButton
              v-else-if="can.suspend"
              type="button"
              variant="soft-danger"
              class="me-auto"
              @click="suspend"
            >
              {{ $t('team.actions.suspend') }}
            </BButton>

            <Link :href="route('team.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.save') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
