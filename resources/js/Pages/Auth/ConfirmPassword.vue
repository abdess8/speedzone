<script setup>
import { ref } from 'vue';
import { Link, Head, useForm } from '@inertiajs/vue3';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthPageFooter from '@/Components/AuthPageFooter.vue';

const form = useForm({
    password: '',
});

const passwordInput = ref(null);

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();

            passwordInput.value.focus();
        },
    });
};
</script>

<template>
    <Head :title="$t('seller_registration.password.confirm_title')" />

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
                                <img src="@assets/images/logo-light.png" alt="SpeedZone Express" height="88">
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
                                    <h5 class="text-primary">{{ $t('seller_registration.password.confirm_heading') }}</h5>
                                    <p class="text-muted">{{ $t('seller_registration.password.confirm_description') }}</p>
                                </div>
                                <div class="p-2 mt-4">
                                    <form @submit.prevent="submit">
                                        <TextInput id="username" type="text" class="" style="display: none;" autocomplete="username" />
                                        <div class="mb-3">
                                            <InputLabel for="password" :value="$t('seller_registration.password.password')" />
                                            <TextInput id="password" ref="passwordInput" v-model="form.password" type="password" class="" required :placeholder="$t('seller_registration.password.password_placeholder')" autocomplete="current-password" autofocus />
                                        </div>
                                        <div class="mb-2 mt-4">
                                            <BButton variant="secondary" class="w-100" type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">{{ $t('seller_registration.password.confirm_submit') }}</BButton>
                                        </div>
                                    </form>
                                </div>
                            </BCardBody>
                        </BCard>

                        <div class="mt-4 text-center">
                            <p class="mb-0">
                                <Link :href="route('login')" class="fw-semibold text-primary text-decoration-underline">{{ $t('seller_registration.password.back_to_login') }}</Link>
                            </p>
                        </div>
                    </BCol>
                </BRow>
            </BContainer>
        </div>

        <AuthPageFooter />
    </div>
</template>
