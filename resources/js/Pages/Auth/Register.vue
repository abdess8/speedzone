<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Multiselect from '@vueform/multiselect';
import '@vueform/multiselect/themes/default.css';

const props = defineProps({
    cities: { type: Array, default: () => [] },
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone_number: '',
    city_id: '',
    password: '',
    password_confirmation: '',
});

const cityOptions = computed(() =>
    (props.cities ?? []).map((city) => ({ value: city.id, label: city.name }))
);

// Mirrors Password::min(8)->mixedCase()->numbers() enforced server side, so the
// rules are visible while typing instead of only after a failed submit.
const passwordChecks = computed(() => [
    { key: 'length', met: form.password.length >= 8 },
    { key: 'uppercase', met: /\p{Lu}/u.test(form.password) },
    { key: 'lowercase', met: /\p{Ll}/u.test(form.password) },
    { key: 'number', met: /\d/.test(form.password) },
]);

const passwordIsStrong = computed(() => passwordChecks.value.every((check) => check.met));

const passwordsMatch = computed(
    () => form.password.length > 0 && form.password === form.password_confirmation
);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<script>
export default {
    data() {
        return {
            togglePassword: false,
            togglePasswordConf: false,
        };
    },
};
</script>

<template>
    <Head :title="$t('seller_registration.register.title')" />

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
                <BRow>
                    <BCol lg="12">
                        <div class="text-center mt-sm-5 mb-4 text-white-50">
                            <Link href="/" class="d-inline-block auth-logo">
                                <img src="@assets/images/logo-light.png" alt="Speed Zone Express" height="52">
                            </Link>
                            <p class="mt-3 fs-15 fw-medium">{{ $t('seller_registration.register.subtitle') }}</p>
                        </div>
                    </BCol>
                </BRow>

                <BRow class="justify-content-center">
                    <BCol md="10" lg="8" xl="6">
                        <BCard no-body class="mt-4">
                            <BCardBody class="p-4">
                                <div class="text-center mt-2 mb-4">
                                    <h5 class="text-primary">{{ $t('seller_registration.register.heading') }}</h5>
                                    <p class="text-muted">{{ $t('seller_registration.register.description') }}</p>
                                </div>

                                <form @submit.prevent="submit" class="needs-validation" novalidate>
                                    <BRow class="g-3">
                                        <BCol md="6">
                                            <InputLabel for="first_name" :value="$t('seller_registration.register.first_name')" />
                                            <TextInput
                                                id="first_name"
                                                v-model="form.first_name"
                                                type="text"
                                                class="mt-1 block w-full"
                                                required
                                                autofocus
                                                autocomplete="given-name"
                                                :class="{ 'is-invalid': form.errors.first_name }"
                                            />
                                            <InputError :message="form.errors.first_name" />
                                        </BCol>

                                        <BCol md="6">
                                            <InputLabel for="last_name" :value="$t('seller_registration.register.last_name')" />
                                            <TextInput
                                                id="last_name"
                                                v-model="form.last_name"
                                                type="text"
                                                class="mt-1 block w-full"
                                                required
                                                autocomplete="family-name"
                                                :class="{ 'is-invalid': form.errors.last_name }"
                                            />
                                            <InputError :message="form.errors.last_name" />
                                        </BCol>

                                        <BCol md="6">
                                            <InputLabel for="email" :value="$t('seller_registration.register.email')" />
                                            <TextInput
                                                id="email"
                                                v-model="form.email"
                                                type="email"
                                                class="mt-1 block w-full"
                                                required
                                                autocomplete="email"
                                                :class="{ 'is-invalid': form.errors.email }"
                                            />
                                            <InputError :message="form.errors.email" />
                                        </BCol>

                                        <BCol md="6">
                                            <InputLabel for="phone_number" :value="$t('seller_registration.register.phone')" />
                                            <TextInput
                                                id="phone_number"
                                                v-model="form.phone_number"
                                                type="tel"
                                                class="mt-1 block w-full"
                                                required
                                                autocomplete="tel"
                                                :class="{ 'is-invalid': form.errors.phone_number }"
                                            />
                                            <InputError :message="form.errors.phone_number" />
                                        </BCol>

                                        <BCol md="12">
                                            <InputLabel for="city_id" :value="$t('seller_registration.register.city')" />
                                            <Multiselect
                                                id="city_id"
                                                v-model="form.city_id"
                                                :options="cityOptions"
                                                :searchable="true"
                                                :close-on-select="true"
                                                :placeholder="$t('seller_registration.register.city_placeholder')"
                                                class="mt-1"
                                                :class="{ 'is-invalid': form.errors.city_id }"
                                            />
                                            <InputError :message="form.errors.city_id" />
                                        </BCol>

                                        <BCol md="6">
                                            <InputLabel for="password" :value="$t('seller_registration.register.password')" />
                                            <div class="position-relative auth-pass-inputgroup mt-1">
                                                <input
                                                    :type="togglePassword ? 'text' : 'password'"
                                                    class="form-control pe-5 password-input"
                                                    id="password"
                                                    required
                                                    v-model="form.password"
                                                    autocomplete="new-password"
                                                    :class="{ 'is-invalid': form.errors.password }"
                                                >
                                                <BButton
                                                    variant="link"
                                                    class="position-absolute end-0 top-0 text-decoration-none text-muted password-addon"
                                                    type="button"
                                                    @click="togglePassword = !togglePassword"
                                                >
                                                    <i class="ri-eye-fill align-middle"></i>
                                                </BButton>
                                                <InputError :message="form.errors.password" />
                                            </div>

                                            <ul class="list-unstyled mb-0 mt-2 fs-12">
                                                <li
                                                    v-for="check in passwordChecks"
                                                    :key="check.key"
                                                    :class="check.met ? 'text-success' : 'text-muted'"
                                                >
                                                    <i
                                                        class="align-middle me-1"
                                                        :class="check.met ? 'ri-checkbox-circle-fill' : 'ri-checkbox-blank-circle-line'"
                                                    ></i>
                                                    {{ $t(`seller_registration.register.password_rules.${check.key}`) }}
                                                </li>
                                            </ul>
                                        </BCol>

                                        <BCol md="6">
                                            <InputLabel for="password_confirmation" :value="$t('seller_registration.register.password_confirmation')" />
                                            <div class="position-relative auth-pass-inputgroup mt-1">
                                                <input
                                                    :type="togglePasswordConf ? 'text' : 'password'"
                                                    class="form-control pe-5 password-input"
                                                    id="password_confirmation"
                                                    required
                                                    v-model="form.password_confirmation"
                                                    autocomplete="new-password"
                                                    :class="{ 'is-invalid': form.errors.password_confirmation }"
                                                >
                                                <BButton
                                                    variant="link"
                                                    class="position-absolute end-0 top-0 text-decoration-none text-muted password-addon"
                                                    type="button"
                                                    @click="togglePasswordConf = !togglePasswordConf"
                                                >
                                                    <i class="ri-eye-fill align-middle"></i>
                                                </BButton>
                                                <InputError :message="form.errors.password_confirmation" />
                                            </div>

                                            <p
                                                v-if="form.password_confirmation && !passwordsMatch"
                                                class="fs-12 text-danger mb-0 mt-2"
                                            >
                                                {{ $t('seller_registration.register.password_rules.mismatch') }}
                                            </p>
                                        </BCol>
                                    </BRow>

                                    <div class="mt-4">
                                        <BButton
                                            variant="primary"
                                            class="w-100"
                                            type="submit"
                                            :class="{ 'opacity-25': form.processing }"
                                            :disabled="form.processing || !passwordIsStrong || !passwordsMatch"
                                        >
                                            {{ $t('seller_registration.register.submit') }}
                                        </BButton>
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
                            </BCardBody>
                        </BCard>

                        <div class="mt-4 text-center">
                            <p class="mb-0 text-white-50">
                                {{ $t('seller_registration.register.already_have_account') }}
                                <Link :href="route('login')" class="fw-semibold text-white text-decoration-underline">
                                    {{ $t('seller_registration.register.sign_in') }}
                                </Link>
                            </p>
                        </div>
                    </BCol>
                </BRow>
            </BContainer>
        </div>

        <footer class="footer">
            <BContainer>
                <div class="text-center">
                    <p class="mb-0 text-muted">&copy; {{ new Date().getFullYear() }} SpeedZone Express.</p>
                </div>
            </BContainer>
        </footer>
    </div>
</template>
