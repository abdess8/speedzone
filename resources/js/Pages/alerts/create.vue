<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import AlertForm from './Partials/AlertForm.vue';
import { defaultEnd, toInstant } from './Partials/alertSchedule';

defineProps({
  types: { type: Array, default: () => [] },
  formats: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
});

const form = useForm({
  title: '',
  message: '',
  type: 'info',
  display_format: 'banner',
  is_dismissible: true,
  target_roles: ['all'],
  target_cities: ['all'],
  target_user_ids: [],
  end_date: defaultEnd(),
  is_active: true,
});

const submit = () =>
  form
    .transform((data) => ({ ...data, end_date: toInstant(data.end_date) }))
    .post(route('alerts.store'));
</script>

<template>
  <Layout>
    <PageHeader :title="$t('alerts.create_title')" :pageTitle="$t('alerts.title')" />

    <form @submit.prevent="submit">
      <AlertForm
        :form="form"
        :types="types"
        :formats="formats"
        :roles="roles"
        :cities="cities"
      />

      <BRow>
        <BCol xl="9" class="mx-auto">
          <div class="hstack gap-2 justify-content-end my-4">
            <Link :href="route('alerts.index')" class="btn btn-light">
              {{ $t('common.cancel') }}
            </Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-send-plane-line align-bottom me-1"></i>
              {{ $t('alerts.create_button') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
