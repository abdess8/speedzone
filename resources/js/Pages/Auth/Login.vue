<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const togglePassword = ref(false);

const submit = () => {
    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head :title="$t('seller_registration.login.title')" />

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
                                <img src="@assets/images/logo-light.png" alt="SpeedZone Express" height="52">
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
                                <div class="text-center mt-2">
                                    <h5 class="text-primary">{{ $t('seller_registration.login.heading') }}</h5>
                                    <p class="text-muted">{{ $t('seller_registration.login.description') }}</p>
                                </div>
                                <div v-if="status" class="alert alert-success text-success">
                                    {{ status }}
                                </div>
                                <div class="p-2 mt-3">
                                    <form @submit.prevent="submit">

                                        <div class="mb-3">
                                            <InputLabel for="email" :value="$t('seller_registration.login.email')" />
                                            <TextInput id="email" v-model="form.email" type="email" class="form-control" autofocus :placeholder="$t('seller_registration.login.email_placeholder')" autocomplete="email" required :class="{ 'is-invalid': form.errors.email }" />
                                            <InputError :message="form.errors.email" />
                                        </div>

                                        <div class="mb-3">
                                            <div class="float-end">
                                                <Link v-if="canResetPassword" :href="route('password.request')" class="text-muted">
                                                {{ $t('seller_registration.login.forgot_password') }}</Link>
                                            </div>
                                            <InputLabel for="password" :value="$t('seller_registration.login.password')" />
                                            <div class="position-relative auth-pass-inputgroup mb-3">
                                                <input :type="togglePassword ? 'text' : 'password'" class="form-control pe-5" :placeholder="$t('seller_registration.login.password_placeholder')" id="password-input" v-model="form.password" autocomplete="current-password" required :class="{ 'is-invalid': form.errors.password }">
                                                <BButton variant="link" class="position-absolute end-0 top-0 text-decoration-none text-muted" type="button" id="password-addon" @click="togglePassword = !togglePassword">
                                                    <i class="ri-eye-fill align-middle"></i>
                                                </BButton>
                                                <InputError :message="form.errors.password" />
                                            </div>
                                        </div>

                                        <div class="form-check">
                                            <Checkbox v-model:checked="form.remember" name="remember" class="form-check-input" id="auth-remember-check" />
                                            <label class="form-check-label" for="auth-remember-check">{{ $t('seller_registration.login.remember_me') }}</label>
                                        </div>

                                        <div class="mt-4">
                                            <BButton variant="primary" class="w-100" type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">{{ $t('seller_registration.login.submit') }}</BButton>
                                        </div>

                                        <div class="mt-4 text-center">
                                            <div class="signin-other-title">
                                                <h5 class="fs-13 mb-4 title">{{ $t('seller_registration.login.or') }}</h5>
                                            </div>

                                            <!-- Full page load, not an Inertia visit: the OAuth handshake
                                                 leaves the SPA for Google's consent screen. -->
                                            <a :href="route('auth.google.redirect')" class="btn btn-light border w-100 d-flex align-items-center justify-content-center gap-2 py-2">
                                                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
                                                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
                                                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
                                                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
                                                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
                                                </svg>
                                                <span class="fw-medium">{{ $t('seller_registration.login.google') }}</span>
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </BCardBody>
                        </BCard>

                        <div class="mt-4 text-center">
                            <p class="mb-0">{{ $t('seller_registration.login.no_account') }}
                                <Link :href="route('register')" class="fw-semibold text-primary text-decoration-underline"> {{ $t('seller_registration.login.sign_up') }} </Link>
                            </p>
                        </div>

                    </BCol>
                </BRow>
            </BContainer>
        </div>

        <footer class="footer">
            <BContainer>
                <BRow>
                    <BCol lg="12">
                        <div class="text-center">
                            <p class="mb-0 text-muted">&copy; {{ new Date().getFullYear() }} SpeedZone Express. Built for reliable logistics operations.</p>
                        </div>
                    </BCol>
                </BRow>
            </BContainer>
        </footer>
    </div>
</template>
