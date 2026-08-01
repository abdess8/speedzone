<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import AlertForm from './Partials/AlertForm.vue';
import { toInstant, toLocalInput } from './Partials/alertSchedule';

const props = defineProps({
  alert: { type: Object, required: true },
  types: { type: Array, default: () => [] },
  formats: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  selectedUsers: { type: Array, default: () => [] },
});

const form = useForm({
  title: props.alert.title,
  message: props.alert.message,
  type: props.alert.type,
  display_format: props.alert.display_format,
  is_dismissible: props.alert.is_dismissible,
  target_roles: [...props.alert.target_roles],
  target_cities: [...props.alert.target_cities],
  target_user_ids: [...props.alert.target_user_ids],
  end_date: toLocalInput(props.alert.end_date),
  is_active: props.alert.is_active,
});

const submit = () =>
  form
    .transform((data) => ({ ...data, end_date: toInstant(data.end_date) }))
    .put(route('alerts.update', props.alert.id));
</script>

<template>
  <Layout>
    <PageHeader :title="$t('alerts.edit_title')" :pageTitle="$t('alerts.title')" />

    <form @submit.prevent="submit">
      <AlertForm
        :form="form"
        :types="types"
        :formats="formats"
        :roles="roles"
        :cities="cities"
        :selected-users="selectedUsers"
      />

      <BRow>
        <BCol xl="9" class="mx-auto">
          <div class="hstack gap-2 justify-content-end my-4">
            <Link :href="route('alerts.index')" class="btn btn-light">
              {{ $t('common.cancel') }}
            </Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i>
              {{ $t('alerts.update_button') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
