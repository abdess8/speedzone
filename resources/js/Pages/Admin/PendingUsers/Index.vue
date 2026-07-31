<script>
import { Link, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import EntityCard from '@/Components/EntityCard.vue';
import EntityDetailSheet from '@/Components/EntityDetailSheet.vue';
import Swal from 'sweetalert2';

/** Contextual colour per registration status, used by the badge and the mobile card. */
const STATUS_COLORS = {
    PENDING_EMAIL_VERIFICATION: 'info',
    PENDING_APPROVAL: 'warning',
    REJECTED: 'danger',
};

export default {
    components: { Layout, PageHeader, Link, FilterPanel, EntityCard, EntityDetailSheet },
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
            /** Row whose mobile detail sheet is open. */
            selectedUser: null,
        };
    },
    computed: {
        /** Drives the "Filter" badge, since the form itself is collapsed by default. */
        activeFilterCount() {
            return [this.search, this.status].filter(Boolean).length;
        },
    },
    watch: {
        // Both go through one timer so clearing several filters at once — which the
        // reset button does — results in a single request.
        search() {
            this.scheduleFilters(350);
        },
        status() {
            this.scheduleFilters(0);
        },
    },
    mounted() {
        this.flashMessage();
    },
    methods: {
        scheduleFilters(delay) {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.applyFilters(), delay);
        },
        resetFilters() {
            this.search = '';
            this.status = '';
        },
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
        statusColor(value) {
            return STATUS_COLORS[value] ?? 'secondary';
        },
        statusBadgeClass(value) {
            const color = this.statusColor(value);

            return `bg-${color}-subtle text-${color}`;
        },
        userName(user) {
            return user.full_name || user.name || '';
        },
        /** Detail lines shared by the mobile card and its sheet. */
        cardRows(user) {
            return [
                { label: this.$t('seller_registration.admin.columns.phone'), value: user.phone_number },
                { label: this.$t('seller_registration.admin.columns.city'), value: this.cityName(user) },
                {
                    label: this.$t('seller_registration.admin.columns.registered_at'),
                    value: this.formatDate(user.created_at),
                },
            ];
        },
        sheetRows(user) {
            return [
                { label: this.$t('seller_registration.admin.columns.email'), value: user.email },
                ...this.cardRows(user),
            ];
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
            <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
                <template #title>
                    <h5 class="card-title mb-0">{{ $t('seller_registration.admin.page_title') }}</h5>
                </template>

                <BCol md="4">
                    <label class="form-label">{{ $t('common.search') }}</label>
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
                    <label class="form-label">{{ $t('seller_registration.admin.columns.status') }}</label>
                    <select v-model="status" class="form-select">
                        <option value="">{{ $t('seller_registration.admin.all_statuses') }}</option>
                        <option v-for="(label, value) in statuses" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                </BCol>
            </FilterPanel>

            <BCardBody>
                <div class="d-lg-none">
                    <EntityCard
                        v-for="user in users.data"
                        :key="user.id"
                        :title="userName(user)"
                        :subtitle="user.email ?? ''"
                        :status-label="statusLabel(user.status)"
                        :status-color="statusColor(user.status)"
                        :rows="cardRows(user)"
                        @open="selectedUser = user"
                    />
                    <p v-if="!users.data.length" class="text-center text-muted py-4 mb-0">
                        {{ $t('seller_registration.admin.empty') }}
                    </p>
                </div>

                <div class="table-responsive d-none d-lg-block">
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

        <EntityDetailSheet
            :show="selectedUser !== null"
            :title="selectedUser ? userName(selectedUser) : ''"
            :subtitle="selectedUser?.email ?? ''"
            :status-label="selectedUser ? statusLabel(selectedUser.status) : ''"
            :status-color="selectedUser ? statusColor(selectedUser.status) : 'secondary'"
            :rows="selectedUser ? sheetRows(selectedUser) : []"
            @close="selectedUser = null"
        >
            <template #actions>
                <Link
                    :href="route('admin.pending-users.show', selectedUser?.id)"
                    class="btn btn-primary flex-fill sheet-action"
                >
                    <i class="ri-eye-line align-bottom me-1"></i>
                    {{ $t('seller_registration.admin.view_details') }}
                </Link>
            </template>
        </EntityDetailSheet>
    </Layout>
</template>
