<script>
import { Link, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import Swal from 'sweetalert2';

export default {
    components: { Layout, PageHeader, Link },
    props: {
        users: { type: Object, required: true },
        filters: { type: Object, default: () => ({}) },
        statuses: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            search: this.filters.search || '',
            status: this.filters.status || '',
            searchTimer: null,
        };
    },
    watch: {
        search() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.applyFilters(), 350);
        },
        status() {
            this.applyFilters();
        },
    },
    mounted() {
        this.flashMessage();
    },
    methods: {
        applyFilters() {
            router.get(
                route('admin.pending-users.index'),
                {
                    search: this.search || undefined,
                    status: this.status || undefined,
                },
                { preserveState: true, replace: true, preserveScroll: true }
            );
        },
        statusLabel(value) {
            return this.statuses[value] || value;
        },
        statusBadgeClass(value) {
            return {
                PENDING_EMAIL_VERIFICATION: 'bg-info-subtle text-info',
                PENDING_APPROVAL: 'bg-warning-subtle text-warning',
                REJECTED: 'bg-danger-subtle text-danger',
            }[value] || 'bg-secondary-subtle text-secondary';
        },
        cityName(user) {
            if (!user.city) return this.$t('common.empty_value_short');
            return typeof user.city === 'object' ? user.city.name : user.city;
        },
        formatDate(value) {
            if (!value) return this.$t('common.empty_value_short');
            return new Date(value).toLocaleDateString(this.$page.props.locale === 'en' ? 'en-GB' : 'fr-FR', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        },
        flashMessage() {
            const success = this.$page.props.flash?.success;
            if (success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: success,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                });
            }
        },
    },
};
</script>

<template>
    <Layout>
        <PageHeader
            :title="$t('seller_registration.admin.page_title')"
            :pageTitle="$t('seller_registration.admin.page_title')"
        />

        <BCard no-body>
            <BCardHeader class="border-0">
                <BRow class="g-3 align-items-center">
                    <BCol md="4">
                        <div class="search-box">
                            <input
                                v-model="search"
                                type="text"
                                class="form-control search"
                                :placeholder="$t('seller_registration.admin.search_placeholder')"
                            >
                            <i class="ri-search-line search-icon"></i>
                        </div>
                    </BCol>
                    <BCol md="3">
                        <select v-model="status" class="form-select">
                            <option value="">{{ $t('seller_registration.admin.all_statuses') }}</option>
                            <option v-for="(label, value) in statuses" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </BCol>
                </BRow>
            </BCardHeader>

            <BCardBody>
                <div class="table-responsive">
                    <table class="table align-middle table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ $t('seller_registration.admin.columns.name') }}</th>
                                <th>{{ $t('seller_registration.admin.columns.email') }}</th>
                                <th>{{ $t('seller_registration.admin.columns.phone') }}</th>
                                <th>{{ $t('seller_registration.admin.columns.city') }}</th>
                                <th>{{ $t('seller_registration.admin.columns.registered_at') }}</th>
                                <th>{{ $t('seller_registration.admin.columns.status') }}</th>
                                <th>{{ $t('seller_registration.admin.columns.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users.data" :key="user.id">
                                <td>{{ user.full_name || user.name }}</td>
                                <td>{{ user.email }}</td>
                                <td>{{ user.phone_number || $t('common.empty_value_short') }}</td>
                                <td>{{ cityName(user) }}</td>
                                <td>{{ formatDate(user.created_at) }}</td>
                                <td>
                                    <span class="badge" :class="statusBadgeClass(user.status)">
                                        {{ statusLabel(user.status) }}
                                    </span>
                                </td>
                                <td>
                                    <Link
                                        :href="route('admin.pending-users.show', user.id)"
                                        class="btn btn-sm btn-soft-primary"
                                    >
                                        {{ $t('seller_registration.admin.view_details') }}
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!users.data.length">
                                <td colspan="7" class="text-center text-muted py-4">
                                    {{ $t('seller_registration.admin.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="users.links?.length > 3" class="d-flex justify-content-end mt-3">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li
                                v-for="link in users.links"
                                :key="link.label"
                                class="page-item"
                                :class="{ active: link.active, disabled: !link.url }"
                            >
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="page-link"
                                    v-html="link.label"
                                    preserve-scroll
                                />
                                <span v-else class="page-link" v-html="link.label"></span>
                            </li>
                        </ul>
                    </nav>
                </div>
            </BCardBody>
        </BCard>
    </Layout>
</template>
