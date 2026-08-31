<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ChangeAccountEmailForm from '@/Components/Auth/ChangeAccountEmailForm.vue';

defineProps({
    user: { type: Object, required: true },
    supportEmail: { type: String, default: '' },
});
</script>

<template>
    <Head :title="$t('seller_registration.pending.title')" />

    <div class="auth-page-wrapper pt-5">
        <div class="auth-one-bg-position auth-one-bg" id="auth-particles">
            <div class="bg-overlay"></div>
            <div class="shape">
                <svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 1440 120">
                    <path d="M 0,36 C 144,53.6 432,123.2 720,124 C 1008,124.8 1296,56.8 1440,40L1440 140L0 140z"></path>
                </svg>
            </div>
        </div>

        <div class="auth-page-content">
            <BContainer>
                <BRow class="justify-content-center">
                    <BCol md="8" lg="6" xl="5">
                        <div class="text-center mt-sm-5 mb-4">
                            <Link href="/" class="d-inline-block auth-logo">
                                <img src="@assets/images/logo-light.png" alt="Speed Zone Express" height="52">
                            </Link>
                        </div>

                        <BCard no-body class="mt-4">
                            <BCardBody class="p-4 text-center">
                                <div class="avatar-lg mx-auto mb-4">
                                    <div class="avatar-title bg-warning-subtle text-warning rounded-circle fs-1">
                                        <i class="ri-time-line"></i>
                                    </div>
                                </div>

                                <h4 class="text-primary">{{ $t('seller_registration.pending.heading') }}</h4>
                                <p class="text-muted mb-4">
                                    {{ $t('seller_registration.pending.message') }}
                                </p>

                                <div class="alert alert-info text-start mb-4">
                                    <p class="mb-1"><strong>{{ user.full_name || user.email }}</strong></p>
                                    <p class="mb-0 small text-muted">{{ $t('seller_registration.pending.review_note') }}</p>
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    <a
                                        v-if="supportEmail"
                                        :href="`mailto:${supportEmail}`"
                                        class="btn btn-soft-primary"
                                    >
                                        <i class="ri-customer-service-2-line me-1"></i>
                                        {{ $t('seller_registration.pending.contact_support') }}
                                    </a>
                                    <Link :href="route('logout')" method="post" as="button" class="btn btn-light">
                                        {{ $t('seller_registration.pending.sign_out') }}
                                    </Link>
                                </div>

                                <ChangeAccountEmailForm :email="user.email" />
                            </BCardBody>
                        </BCard>
                    </BCol>
                </BRow>
            </BContainer>
        </div>
    </div>
</template>
