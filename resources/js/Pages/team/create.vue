<script setup>
import { watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import AdminSellerCard from '@/Components/AdminSellerCard.vue';
import MemberForm from './Partials/MemberForm.vue';

const props = defineProps({
  stores: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  admin: { type: Boolean, default: false },
  sellers: { type: Array, default: () => [] },
  seller: { type: Object, default: null },
});

const form = useForm({
  seller_id: props.seller?.id ?? '',
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

watch(
  () => form.seller_id,
  (sellerId) => {
    if (!props.admin || Number(sellerId || 0) === Number(props.seller?.id || 0)) {
      return;
    }

    router.get(route('team.create'), sellerId ? { seller_id: sellerId } : {});
  },
);

const submit = () => {
  form.post(route('team.store'));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('team.create_title')" :pageTitle="$t('team.title')" />

    <form @submit.prevent="submit">
      <AdminSellerCard
        v-if="admin"
        v-model="form.seller_id"
        :sellers="sellers"
        :error="form.errors.seller_id"
        :label="$t('team.admin.seller_field')"
        :placeholder="$t('team.admin.seller_placeholder')"
        :help="$t('team.admin.seller_help')"
      />

      <MemberForm :form="form" :stores="stores" :roles="roles" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('team.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton
              data-guide="team-submit"
              type="submit"
              variant="success"
              :disabled="form.processing || (admin && !form.seller_id)"
            >
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.create') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
