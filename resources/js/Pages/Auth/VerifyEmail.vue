<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import ChangeAccountEmailForm from '@/Components/Auth/ChangeAccountEmailForm.vue';

const props = defineProps({
    status: String,
});

const page = usePage();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const currentEmail = computed(() => page.props.auth?.user?.email ?? '');

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head :title="$t('seller_registration.verify.title')" />

    <div class="auth-page-wrapper pt-5">
        <div class="auth-one-bg-position auth-one-bg" id="auth-particles">
            <div class="bg-overlay"></div>

            <div class="shape">
                <svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 1440 120">
                    <path d="M 0,36 C 144,53.6 432,123.2 720,124 C 1008,124.8 1296,56.8 1440,40L1440 140L0 140z"></path>
                </svg>
            </div>
        </div>

        <div class="auth-page-content">
            <BContainer>
                <BRow>
                    <BCol lg="12">
                        <div class="text-center mt-sm-5 mb-4 text-white-50">
                            <div>
                                <Link href="/" class="d-inline-block auth-logo">
                                <img src="@assets/images/logo-light.png" alt="OWL Delivery" height="58">
                                </Link>
                            </div>
                            <p class="mt-3 fs-15 fw-medium">{{ $t('seller_registration.login.subtitle') }}</p>
                        </div>
                    </BCol>
                </BRow>

                <BRow class="justify-content-center">
                    <BCol md="8" lg="6" xl="5">
                        <BCard no-body class="mt-4">

                            <BCardBody class="p-4">
                                <div class="mb-4">
                                    <div class="avatar-lg mx-auto">
                                        <div class="avatar-title bg-light text-primary display-5 rounded-circle">
                                            <i class="ri-mail-line"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-2 mt-4">
                                    <div class="text-muted text-center mb-4 mx-lg-3">
                                        <h4>{{ $t('seller_registration.verify.heading') }}</h4>
                                        <div class="text-sm text-muted">
                                            {{ $t('seller_registration.verify.message', { email: currentEmail }) }}
                                        </div>
                                    </div>

                                    <div v-if="verificationLinkSent" class="mb-4 alert alert-success text-sm text-success">
                                        {{ $t('seller_registration.verify.link_sent') }}
                                    </div>
                                    <div v-else-if="status" class="mb-4 alert alert-info">
                                        {{ status }}
                                    </div>

                                    <form @submit.prevent="submit">
                                        <div class="w-100 mb-3">
                                            <BButton variant="primary" type="submit" class="w-100" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                                {{ $t('seller_registration.verify.resend') }}
                                            </BButton>
                                        </div>
                                    </form>

                                    <ChangeAccountEmailForm :email="currentEmail" />

                                    <div class="text-center mt-3">
                                        <Link :href="route('logout')" method="post" as="button" class="btn btn-link text-muted text-decoration-underline p-0">
                                            {{ $t('seller_registration.pending.sign_out') }}
                                        </Link>
                                    </div>
                                </div>
                            </BCardBody>
                        </BCard>
                    </BCol>
                </BRow>
            </BContainer>
        </div>

        <footer class="footer">
            <BContainer>
                <BRow>
                    <BCol lg="12">
                        <div class="text-center">
                            <p class="mb-0 text-muted">&copy; {{ new Date().getFullYear() }} OWL Delivery &mdash; une société OWL Media.</p>
                        </div>
                    </BCol>
                </BRow>
            </BContainer>
        </footer>
    </div>
</template>
