<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import MemberForm from './Partials/MemberForm.vue';

const props = defineProps({
  stores: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
});

const form = useForm({
  first_name: '',
  last_name: '',
  email: '',
  phone_number: '',
  password: '',
  password_confirmation: '',
  // Pre-tick the default store: the common case is a member working on the
  // vendor's main shop.
  store_ids: props.stores.filter((store) => store.is_default).map((store) => store.id),
  role_ids: [],
});

const submit = () => {
  form.post(route('team.store'));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('team.create_title')" :pageTitle="$t('team.title')" />

    <form @submit.prevent="submit">
      <MemberForm :form="form" :stores="stores" :roles="roles" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('team.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.create') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
