<script>
import { Link, router } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import EntityCard from '@/Components/EntityCard.vue';
import EntityDetailSheet from '@/Components/EntityDetailSheet.vue';
import { roleLabel as sharedRoleLabel } from '@/utils/roleLabel';
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
        roleCounts: { type: Object, default: () => ({ all: 0, seller: 0, driver: 0 }) },
    },
    data() {
        return {
            search: this.filters.search || '',
            status: this.filters.status || '',
            role: this.filters.role || '',
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
        roleTabs() {
            return [
                { value: '', label: this.$t('seller_registration.admin.role_filter.all'), count: this.roleCounts.all, icon: 'ri-team-line' },
                { value: 'seller', label: this.$t('seller_registration.admin.role_filter.seller'), count: this.roleCounts.seller, icon: 'ri-store-2-line' },
                { value: 'driver', label: this.$t('seller_registration.admin.role_filter.driver'), count: this.roleCounts.driver, icon: 'ri-truck-line' },
            ];
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
        role() {
            this.selectedUser = null;
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
                    role: this.role || undefined,
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
        roleName(user) {
            return sharedRoleLabel(user.role, this.$t);
        },
        /** Detail lines shared by the mobile card and its sheet. */
        cardRows(user) {
            return [
                { label: this.$t('seller_registration.admin.columns.role'), value: this.roleName(user) },
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

            <div class="pending-role-bar px-3 py-2 border-bottom">
                <div class="pending-role-switch" role="tablist" :aria-label="$t('seller_registration.admin.columns.role')">
                    <button
                        v-for="tab in roleTabs"
                        :key="tab.value || 'all'"
                        type="button"
                        role="tab"
                        class="pending-role-switch__btn"
                        :class="{ 'is-active': role === tab.value }"
                        :aria-selected="role === tab.value"
                        @click="role = tab.value"
                    >
                        <i :class="tab.icon" class="align-middle"></i>
                        <span>{{ tab.label }}</span>
                        <span class="pending-role-switch__count">{{ tab.count }}</span>
                    </button>
                </div>
            </div>

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
                                <th>{{ $t('seller_registration.admin.columns.role') }}</th>
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
                                <td>{{ roleName(user) }}</td>
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
                                <td colspan="8" class="text-center text-muted py-4">
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

<style scoped>
.pending-role-bar {
    background: var(--vz-card-bg, #fff);
}

.pending-role-switch {
    display: flex;
    gap: 0.35rem;
    padding: 0.2rem;
    border-radius: 999px;
    background: var(--vz-light, #f3f6f9);
    overflow-x: auto;
}

.pending-role-switch__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    flex: 1 1 0;
    min-width: max-content;
    padding: 0.45rem 0.85rem;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--vz-body-color, #6d7080);
    font-size: 0.8125rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: background-color 0.18s ease, color 0.18s ease, box-shadow 0.18s ease;
}

.pending-role-switch__btn.is-active {
    background: var(--vz-card-bg, #fff);
    color: var(--vz-heading-color, #495057);
    box-shadow: 0 1px 3px rgba(56, 65, 74, 0.14);
}

.pending-role-switch__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.35rem;
    height: 1.35rem;
    padding: 0 0.35rem;
    border-radius: 999px;
    background: var(--vz-secondary-bg, #e9ebec);
    font-size: 0.7rem;
    font-weight: 700;
}

.pending-role-switch__btn.is-active .pending-role-switch__count {
    background: rgba(64, 81, 137, 0.12);
    color: var(--vz-primary, #405189);
}

@media (prefers-reduced-motion: reduce) {
    .pending-role-switch__btn {
        transition: none;
    }
}
</style>
