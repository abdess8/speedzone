<script setup>
import NotificationSettings from '@/Components/Notifications/NotificationSettings.vue';
import ProfileScoreCard from '@/Components/ProfileScoreCard.vue';
import Layout from "@/Layouts/main.vue";
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Profile/Partials/UpdateProfileInformationForm.vue';
import UpdateSellerDetailsForm from '@/Pages/Profile/Partials/UpdateSellerDetailsForm.vue';

defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
    cities: { type: Array, default: () => [] },
});
</script>

<template>
    <Layout :title="$t('profile.title')">
        <template #header>
            <h2 class="fw-semibold">
                {{ $t('profile.title') }}
            </h2>
        </template>

        <div>
            <BRow class="justify-content-center">
                <BCol lg="8">
                    <ProfileScoreCard />

                    <div v-if="$page.props.jetstream.canUpdateProfileInformation">
                        <UpdateProfileInformationForm :user="$page.props.auth.user" />
                    </div>

                    <UpdateSellerDetailsForm
                        v-if="$page.props.auth.user.is_seller"
                        :user="$page.props.auth.user"
                        :cities="cities"
                    />

                    <div v-if="$page.props.jetstream.canUpdatePassword">
                        <UpdatePasswordForm />
                    </div>

                    <NotificationSettings />

                    <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication">
                        <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
                    </div>

                    <LogoutOtherBrowserSessionsForm :sessions="sessions" />

                    <template v-if="$page.props.jetstream.hasAccountDeletionFeatures">
                        <DeleteUserForm />
                    </template>
                </BCol>
            </BRow>
        </div>
    </Layout>
</template>
