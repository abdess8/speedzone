<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import PermissionSelector from './Partials/PermissionSelector.vue';
import Swal from 'sweetalert2';

const props = defineProps({
    user: { type: Object, required: true },
    permissionGroups: { type: Array, default: () => [] },
    defaultPermissionIds: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
});

const approveForm = useForm({
    permission_ids: [...props.defaultPermissionIds],
});

const rejectForm = useForm({
    rejection_reason: '',
});

const canReview = computed(() => props.user.status === 'PENDING_APPROVAL');
const canReject = computed(() => ['PENDING_APPROVAL', 'PENDING_EMAIL_VERIFICATION'].includes(props.user.status));

const statusLabel = computed(() => props.statuses[props.user.status] || props.user.status);

const statusBadgeClass = computed(() => ({
    PENDING_EMAIL_VERIFICATION: 'bg-info-subtle text-info',
    PENDING_APPROVAL: 'bg-warning-subtle text-warning',
    REJECTED: 'bg-danger-subtle text-danger',
}[props.user.status] || 'bg-secondary-subtle text-secondary'));

const cityName = computed(() => {
    if (!props.user.city) return '—';
    return typeof props.user.city === 'object' ? props.user.city.name : props.user.city;
});

const approve = () => {
    approveForm.post(route('admin.users.approve', props.user.id), {
        preserveScroll: true,
    });
};

const reject = () => {
    Swal.fire({
        title: 'Reject registration?',
        input: 'textarea',
        inputLabel: 'Rejection reason (optional)',
        inputValue: rejectForm.rejection_reason,
        showCancelButton: true,
        confirmButtonColor: '#f06548',
        confirmButtonText: 'Reject',
    }).then((result) => {
        if (result.isConfirmed) {
            rejectForm.rejection_reason = result.value || '';
            rejectForm.post(route('admin.users.reject', props.user.id));
        }
    });
};
</script>

<template>
    <Layout>
        <PageHeader
            :title="$t('seller_registration.admin.details_title')"
            :pageTitle="$t('seller_registration.admin.page_title')"
        />

        <BRow class="g-4">
            <BCol lg="5">
                <BCard no-body>
                    <BCardHeader>
                        <h5 class="card-title mb-0">{{ $t('seller_registration.admin.personal_info') }}</h5>
                    </BCardHeader>
                    <BCardBody>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">{{ $t('seller_registration.admin.columns.name') }}</dt>
                            <dd class="col-sm-8">{{ user.full_name || user.name }}</dd>

                            <dt class="col-sm-4">{{ $t('seller_registration.admin.columns.email') }}</dt>
                            <dd class="col-sm-8">{{ user.email }}</dd>

                            <dt class="col-sm-4">{{ $t('seller_registration.admin.columns.phone') }}</dt>
                            <dd class="col-sm-8">{{ user.phone_number || '—' }}</dd>

                            <dt class="col-sm-4">{{ $t('seller_registration.admin.columns.city') }}</dt>
                            <dd class="col-sm-8">{{ cityName }}</dd>

                            <dt class="col-sm-4">{{ $t('seller_registration.admin.columns.status') }}</dt>
                            <dd class="col-sm-8">
                                <span class="badge" :class="statusBadgeClass">{{ statusLabel }}</span>
                            </dd>

                            <template v-if="user.rejection_reason">
                                <dt class="col-sm-4">{{ $t('seller_registration.admin.rejection_reason') }}</dt>
                                <dd class="col-sm-8">{{ user.rejection_reason }}</dd>
                            </template>
                        </dl>
                    </BCardBody>
                </BCard>
            </BCol>

            <BCol lg="7">
                <BCard no-body>
                    <BCardHeader class="d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">{{ $t('seller_registration.admin.approval_section') }}</h5>
                        <Link :href="route('admin.pending-users.index')" class="btn btn-sm btn-light">
                            {{ $t('common.back') }}
                        </Link>
                    </BCardHeader>
                    <BCardBody>
                        <p class="text-muted">{{ $t('seller_registration.admin.permissions_help') }}</p>

                        <form @submit.prevent="approve">
                            <PermissionSelector :form="approveForm" :permission-groups="permissionGroups" />

                            <div class="hstack gap-2 justify-content-end mt-4">
                                <BButton
                                    v-if="canReject"
                                    type="button"
                                    variant="danger"
                                    :disabled="rejectForm.processing"
                                    @click="reject"
                                >
                                    {{ $t('seller_registration.admin.reject') }}
                                </BButton>
                                <BButton
                                    v-if="canReview"
                                    type="submit"
                                    variant="success"
                                    :disabled="approveForm.processing"
                                >
                                    {{ $t('seller_registration.admin.approve') }}
                                </BButton>
                            </div>
                        </form>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>
    </Layout>
</template>
