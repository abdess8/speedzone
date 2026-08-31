<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import InputError from '@/Components/InputError.vue';
import DocumentPreview from '@/Components/DocumentPreview.vue';
import FlipCardPreview from '@/Components/FlipCardPreview.vue';
import { roleLabel as sharedRoleLabel } from '@/utils/roleLabel';
import Multiselect from '@vueform/multiselect';
import '@vueform/multiselect/themes/default.css';
import Swal from 'sweetalert2';

const { t } = useI18n();

const props = defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    cities: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const form = useForm({
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    email: props.user.email || '',
    role_id: props.user.role_id || '',
    city_id: props.user.city_id || '',
    phone_number: props.user.phone_number || '',
    cin: props.user.cin || '',
    ice_number: props.user.ice_number || '',
    address: props.user.address || '',
    pickup_address_1: props.user.pickup_address_1 || '',
    pickup_address_2: props.user.pickup_address_2 || '',
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const approveForm = useForm({});
const rejectForm = useForm({ rejection_reason: '' });

const statusLabel = computed(() => props.statuses[props.user.status] || props.user.status);

const statusBadgeClass = computed(() => ({
    PENDING_EMAIL_VERIFICATION: 'bg-info-subtle text-info',
    PENDING_APPROVAL: 'bg-warning-subtle text-warning',
    REJECTED: 'bg-danger-subtle text-danger',
}[props.user.status] || 'bg-secondary-subtle text-secondary'));

const emailVerified = computed(() => Boolean(props.user.email_verified_at));

const cityOptions = computed(() => props.cities.map((city) => ({ value: city.id, label: city.name })));

// The loaded relation serialises over the foreign key of the same name, so the
// prop is only a reviewer once it comes back as an object.
const reviewedBy = computed(() => {
    const approver = props.user.approved_by;

    return approver && typeof approver === 'object' ? approver.full_name || approver.name : null;
});

const roleLabel = (role) => sharedRoleLabel(role, t);

const formatDate = (value) => {
    if (!value) return t('common.empty_value');

    return new Date(value).toLocaleDateString(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const toast = (title) => {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
};

const save = () => {
    form.put(route('admin.pending-users.update', props.user.id), {
        preserveScroll: true,
    });
};

const changePassword = () => {
    passwordForm.put(route('admin.pending-users.password.update', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            toast(t('seller_registration.admin.password_updated'));
        },
    });
};

const resendVerification = () => {
    router.post(route('admin.pending-users.resend-verification', props.user.id), {}, {
        preserveScroll: true,
        onSuccess: () => toast(t('seller_registration.admin.verification_sent')),
    });
};

const approve = () => {
    approveForm.post(route('admin.users.approve', props.user.id));
};

const reactivate = () => {
    Swal.fire({
        title: t('seller_registration.admin.reactivate_confirm_title'),
        text: t('seller_registration.admin.reactivate_confirm_text'),
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: t('seller_registration.admin.reactivate'),
        cancelButtonText: t('common.cancel'),
    }).then((result) => {
        if (result.isConfirmed) {
            approveForm.post(route('admin.users.reactivate', props.user.id));
        }
    });
};

const reject = () => {
    Swal.fire({
        title: t('seller_registration.admin.reject_confirm_title'),
        input: 'textarea',
        inputLabel: t('seller_registration.admin.reject_reason_label'),
        inputValue: rejectForm.rejection_reason,
        showCancelButton: true,
        confirmButtonColor: '#f06548',
        confirmButtonText: t('seller_registration.admin.reject'),
        cancelButtonText: t('common.cancel'),
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
            <BCol lg="8">
                <form @submit.prevent="save">
                    <BCard no-body>
                        <BCardHeader class="d-flex align-items-center justify-content-between">
                            <h5 class="card-title mb-0">{{ $t('users.form.account_info') }}</h5>
                            <Link :href="route('admin.pending-users.index')" class="btn btn-sm btn-light">
                                {{ $t('common.back') }}
                            </Link>
                        </BCardHeader>
                        <BCardBody>
                            <BRow class="g-3">
                                <BCol md="6">
                                    <label class="form-label">
                                        {{ $t('users.form.first_name') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" v-model="form.first_name" :class="{ 'is-invalid': form.errors.first_name }" />
                                    <InputError :message="form.errors.first_name" />
                                </BCol>
                                <BCol md="6">
                                    <label class="form-label">
                                        {{ $t('users.form.last_name') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" v-model="form.last_name" :class="{ 'is-invalid': form.errors.last_name }" />
                                    <InputError :message="form.errors.last_name" />
                                </BCol>
                                <BCol md="6">
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <label class="form-label mb-0">
                                            {{ $t('users.form.email') }} <span class="text-danger">*</span>
                                        </label>
                                        <span
                                            class="badge"
                                            :class="emailVerified ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'"
                                        >
                                            <i :class="emailVerified ? 'ri-mail-check-line' : 'ri-mail-close-line'" class="align-bottom me-1"></i>
                                            {{ emailVerified ? $t('seller_registration.admin.email_verified') : $t('seller_registration.admin.email_unverified') }}
                                        </span>
                                    </div>
                                    <input type="email" class="form-control mt-1" v-model="form.email" :class="{ 'is-invalid': form.errors.email }" />
                                    <InputError :message="form.errors.email" />
                                    <BButton
                                        v-if="!emailVerified"
                                        type="button"
                                        variant="soft-primary"
                                        size="sm"
                                        class="mt-2"
                                        @click="resendVerification"
                                    >
                                        <i class="ri-mail-send-line align-bottom me-1"></i>
                                        {{ $t('seller_registration.verify.resend') }}
                                    </BButton>
                                </BCol>
                                <BCol md="6">
                                    <label class="form-label">
                                        {{ $t('users.form.role') }} <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        class="form-select"
                                        v-model="form.role_id"
                                        :disabled="!can.assign_roles"
                                        :class="{ 'is-invalid': form.errors.role_id }"
                                    >
                                        <option value="" disabled>{{ $t('users.form.select_role') }}</option>
                                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ roleLabel(role) }}</option>
                                    </select>
                                    <InputError :message="form.errors.role_id" />
                                    <p class="form-text mb-0">{{ $t('seller_registration.admin.role_help') }}</p>
                                </BCol>
                                <BCol md="6">
                                    <label class="form-label">{{ $t('users.form.phone') }}</label>
                                    <input type="text" class="form-control" v-model="form.phone_number" :class="{ 'is-invalid': form.errors.phone_number }" />
                                    <InputError :message="form.errors.phone_number" />
                                </BCol>
                                <BCol md="6">
                                    <label class="form-label">
                                        {{ $t('users.form.city') }} <span class="text-danger">*</span>
                                    </label>
                                    <Multiselect
                                        v-model="form.city_id"
                                        :options="cityOptions"
                                        :searchable="true"
                                        :close-on-select="true"
                                        :placeholder="$t('users.form.select_city')"
                                        :class="{ 'is-invalid': form.errors.city_id }"
                                    />
                                    <InputError :message="form.errors.city_id" />
                                </BCol>
                                <BCol md="6">
                                    <label class="form-label">{{ $t('users.form.cin') }}</label>
                                    <input type="text" class="form-control" v-model="form.cin" :class="{ 'is-invalid': form.errors.cin }" />
                                    <InputError :message="form.errors.cin" />
                                </BCol>
                                <BCol md="6">
                                    <label class="form-label">{{ $t('users.form.ice') }}</label>
                                    <input type="text" class="form-control" v-model="form.ice_number" :class="{ 'is-invalid': form.errors.ice_number }" />
                                    <InputError :message="form.errors.ice_number" />
                                </BCol>
                                <BCol md="12">
                                    <label class="form-label">{{ $t('users.form.address') }}</label>
                                    <textarea class="form-control" rows="2" v-model="form.address" :class="{ 'is-invalid': form.errors.address }"></textarea>
                                    <InputError :message="form.errors.address" />
                                </BCol>
                                <BCol md="6">
                                    <label class="form-label">{{ $t('users.form.pickup_address_1') }}</label>
                                    <textarea class="form-control" rows="2" v-model="form.pickup_address_1" :class="{ 'is-invalid': form.errors.pickup_address_1 }"></textarea>
                                    <InputError :message="form.errors.pickup_address_1" />
                                </BCol>
                                <BCol md="6">
                                    <label class="form-label">{{ $t('users.form.pickup_address_2') }}</label>
                                    <textarea class="form-control" rows="2" v-model="form.pickup_address_2" :class="{ 'is-invalid': form.errors.pickup_address_2 }"></textarea>
                                    <InputError :message="form.errors.pickup_address_2" />
                                </BCol>
                            </BRow>

                            <div class="hstack gap-2 justify-content-end mt-4">
                                <BButton type="submit" variant="primary" :disabled="form.processing">
                                    <i class="ri-save-line align-bottom me-1"></i>
                                    {{ $t('common.save_changes') }}
                                </BButton>
                            </div>
                        </BCardBody>
                    </BCard>
                </form>

                <form v-if="can.change_password" @submit.prevent="changePassword">
                    <BCard no-body>
                        <BCardHeader>
                            <h5 class="card-title mb-0">{{ $t('seller_registration.admin.change_password') }}</h5>
                        </BCardHeader>
                        <BCardBody>
                            <p class="text-muted">{{ $t('seller_registration.admin.password_help') }}</p>

                            <BRow class="g-3">
                                <BCol md="6">
                                    <label class="form-label">
                                        {{ $t('users.form.password') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="password" class="form-control" autocomplete="new-password" v-model="passwordForm.password" :class="{ 'is-invalid': passwordForm.errors.password }" />
                                    <InputError :message="passwordForm.errors.password" />
                                </BCol>
                                <BCol md="6">
                                    <label class="form-label">
                                        {{ $t('users.form.confirm_password') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="password" class="form-control" autocomplete="new-password" v-model="passwordForm.password_confirmation" />
                                </BCol>
                            </BRow>

                            <div class="hstack gap-2 justify-content-end mt-4">
                                <BButton type="submit" variant="soft-primary" :disabled="passwordForm.processing">
                                    <i class="ri-lock-password-line align-bottom me-1"></i>
                                    {{ $t('seller_registration.admin.change_password') }}
                                </BButton>
                            </div>
                        </BCardBody>
                    </BCard>
                </form>
            </BCol>

            <BCol lg="4">
                <BCard no-body>
                    <BCardHeader>
                        <h5 class="card-title mb-0">{{ $t('seller_registration.admin.review_section') }}</h5>
                    </BCardHeader>
                    <BCardBody>
                        <dl class="row mb-0">
                            <dt class="col-5">{{ $t('seller_registration.admin.columns.status') }}</dt>
                            <dd class="col-7">
                                <span class="badge" :class="statusBadgeClass">{{ statusLabel }}</span>
                            </dd>

                            <dt class="col-5">{{ $t('seller_registration.admin.columns.registered_at') }}</dt>
                            <dd class="col-7">{{ formatDate(user.created_at) }}</dd>

                            <template v-if="reviewedBy">
                                <dt class="col-5">{{ $t('seller_registration.admin.reviewed_by') }}</dt>
                                <dd class="col-7">{{ reviewedBy }}</dd>
                            </template>

                            <template v-if="user.rejection_reason">
                                <dt class="col-5">{{ $t('seller_registration.admin.rejection_reason') }}</dt>
                                <dd class="col-7">{{ user.rejection_reason }}</dd>
                            </template>
                        </dl>

                        <p class="text-muted mt-3 mb-0">{{ $t('seller_registration.admin.review_help') }}</p>

                        <div class="d-grid gap-2 mt-3">
                            <BButton
                                v-if="can.approve"
                                type="button"
                                variant="success"
                                :disabled="approveForm.processing"
                                @click="approve"
                            >
                                <i class="ri-check-double-line align-bottom me-1"></i>
                                {{ $t('seller_registration.admin.approve') }}
                            </BButton>
                            <BButton
                                v-if="can.reactivate"
                                type="button"
                                variant="success"
                                :disabled="approveForm.processing"
                                @click="reactivate"
                            >
                                <i class="ri-restart-line align-bottom me-1"></i>
                                {{ $t('seller_registration.admin.reactivate') }}
                            </BButton>
                            <BButton
                                v-if="can.reject"
                                type="button"
                                variant="danger"
                                :disabled="rejectForm.processing"
                                @click="reject"
                            >
                                <i class="ri-close-circle-line align-bottom me-1"></i>
                                {{ $t('seller_registration.admin.reject') }}
                            </BButton>
                        </div>
                    </BCardBody>
                </BCard>

                <BCard no-body>
                    <BCardHeader>
                        <h5 class="card-title mb-0">{{ $t('seller_registration.admin.documents_section') }}</h5>
                    </BCardHeader>
                    <BCardBody>
                        <FlipCardPreview
                            :front-url="user.cin_front_attachment_url"
                            :back-url="user.cin_back_attachment_url"
                            :label="$t('users.form.cin')"
                            class="mb-3"
                        />
                        <DocumentPreview
                            :url="user.rib_attachment_url"
                            :label="$t('users.form.rib_attachment')"
                        />
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>
    </Layout>
</template>
