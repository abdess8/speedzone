<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import ConfirmsPassword from '@/Components/ConfirmsPassword.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    requiresConfirmation: Boolean,
});

const page = usePage();
const enabling = ref(false);
const confirming = ref(false);
const disabling = ref(false);
const qrCode = ref(null);
const setupKey = ref(null);
const recoveryCodes = ref([]);

const confirmationForm = useForm({
    code: '',
});

const twoFactorEnabled = computed(
    () => !enabling.value && page.props.auth.user?.two_factor_enabled,
);

watch(twoFactorEnabled, () => {
    if (!twoFactorEnabled.value) {
        confirmationForm.reset();
        confirmationForm.clearErrors();
    }
});

const enableTwoFactorAuthentication = () => {
    enabling.value = true;

    router.post(route('two-factor.enable'), {}, {
        preserveScroll: true,
        onSuccess: () => Promise.all([
            showQrCode(),
            showSetupKey(),
            showRecoveryCodes(),
        ]),
        onFinish: () => {
            enabling.value = false;
            confirming.value = props.requiresConfirmation;
        },
    });
};

const showQrCode = () => {
    return axios.get(route('two-factor.qr-code')).then(response => {
        qrCode.value = response.data.svg;
    });
};

const showSetupKey = () => {
    return axios.get(route('two-factor.secret-key')).then(response => {
        setupKey.value = response.data.secretKey;
    });
}

const showRecoveryCodes = () => {
    return axios.get(route('two-factor.recovery-codes')).then(response => {
        recoveryCodes.value = response.data;
    });
};

const confirmTwoFactorAuthentication = () => {
    confirmationForm.post(route('two-factor.confirm'), {
        errorBag: "confirmTwoFactorAuthentication",
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirming.value = false;
            qrCode.value = null;
            setupKey.value = null;
        },
    });
};

const regenerateRecoveryCodes = () => {
    axios
        .post(route('two-factor.recovery-codes'))
        .then(() => showRecoveryCodes());
};

const disableTwoFactorAuthentication = () => {
    disabling.value = true;

    router.delete(route('two-factor.disable'), {
        preserveScroll: true,
        onSuccess: () => {
            disabling.value = false;
            confirming.value = false;
        },
    });
};
</script>

<template>
    <BCard no-body>
        <BCardHeader>
            <BCardTitle>{{ $t('profile.two_factor.title') }}</BCardTitle>
            <p class="text-muted mb-0">{{ $t('profile.two_factor.description') }}</p>
        </BCardHeader>
        <BCardBody class="p-4">
            <h5 v-if="twoFactorEnabled && !confirming">
                {{ $t('profile.two_factor.enabled') }}
            </h5>

            <h5 v-else-if="twoFactorEnabled && confirming">
                {{ $t('profile.two_factor.finish_enabling') }}
            </h5>

            <h5 v-else>
                {{ $t('profile.two_factor.not_enabled') }}
            </h5>

            <div class="mt-3 text-muted text-sm">
                <p>
                    {{ $t('profile.two_factor.not_enabled_hint') }}
                </p>
            </div>

            <div v-if="twoFactorEnabled">
                <div v-if="qrCode">
                    <div class="mt-3 max-w-xl text-sm text-gray-600">
                        <p v-if="confirming" class="text-muted">
                            {{ $t('profile.two_factor.qr_instructions_confirm') }}
                        </p>

                        <p v-else>
                            {{ $t('profile.two_factor.qr_instructions') }}
                        </p>
                    </div>

                    <div class="mt-3 p-2" v-html="qrCode" />

                    <div v-if="setupKey" class="mt-3 text-muted">
                        <p class="fw-semibold">
                            {{ $t('profile.two_factor.setup_key') }}: <span v-html="setupKey"></span>
                        </p>
                    </div>

                    <div v-if="confirming" class="mt-3">
                        <InputLabel for="code" :value="$t('profile.two_factor.code')" />

                        <TextInput id="code" v-model="confirmationForm.code" type="text" name="code" inputmode="numeric" autofocus autocomplete="one-time-code" @keyup.enter="confirmTwoFactorAuthentication" :class="{ 'is-invalid': confirmationForm.errors.code }" />

                        <InputError :message="confirmationForm.errors.code" class="mt-2" />
                    </div>
                </div>

                <div v-if="recoveryCodes.length > 0 && !confirming">
                    <div class="mt-3 text-sm text-muted">
                        <p class="fw-semibold">
                            {{ $t('profile.two_factor.recovery_codes_hint') }}
                        </p>
                    </div>

                    <div class="fw-semibold">
                        <pre class="language-markup"><code><div v-for="code in recoveryCodes" :key="code">{{ code }}</div></code></pre>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <div v-if="!twoFactorEnabled">
                    <ConfirmsPassword @confirmed="enableTwoFactorAuthentication">
                        <BButton variant="danger w-100" type="button" :class="{ 'opacity-25': enabling }" :disabled="enabling">{{ $t('profile.two_factor.enable') }}</BButton>
                    </ConfirmsPassword>
                </div>

                <div v-else>
                    <ConfirmsPassword @confirmed="confirmTwoFactorAuthentication">
                        <BButton v-if="confirming" variant="primary" type="button" class="me-1" :class="{ 'opacity-25': enabling }" :disabled="enabling">{{ $t('profile.two_factor.confirm') }}</BButton>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="regenerateRecoveryCodes">
                        <BButton v-if="recoveryCodes.length > 0 && !confirming" variant="primary" type="button" class="me-1">{{ $t('profile.two_factor.regenerate_recovery_codes') }}</BButton>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="showRecoveryCodes">
                        <BButton v-if="recoveryCodes.length === 0 && !confirming" variant="primary" class="me-1" type="button">{{ $t('profile.two_factor.show_recovery_codes') }}</BButton>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="disableTwoFactorAuthentication">
                        <BButton v-if="confirming" variant="danger" type="button" :class="{ 'opacity-25': disabling }" :disabled="disabling">{{ $t('profile.two_factor.cancel') }}</BButton>
                    </ConfirmsPassword>

                    <ConfirmsPassword @confirmed="disableTwoFactorAuthentication">
                        <BButton v-if="!confirming" variant="danger" type="button"  :class="{ 'opacity-25': disabling }" :disabled="disabling">{{ $t('profile.two_factor.disable') }}</BButton>
                    </ConfirmsPassword>
                </div>
            </div>
        </BCardBody>
    </BCard>
</template>
