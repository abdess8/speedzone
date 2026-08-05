<script setup>
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

/**
 * The one edit an account still waiting for verification or approval is allowed
 * to make. Lives on the holding pages, which render no navigation, so a visitor
 * who has not been let in never sees the application shell.
 */
const props = defineProps({
    email: { type: String, default: '' },
});

const form = useForm({
    email: props.email,
});

const submit = () => {
    form.put(route('account.email.update'));
};
</script>

<template>
    <div class="border-top pt-3 mt-3 text-start">
        <h6 class="mb-1">{{ $t('seller_registration.pending.change_email.title') }}</h6>
        <p class="text-muted fs-12">{{ $t('seller_registration.pending.change_email.description') }}</p>

        <form @submit.prevent="submit">
            <label class="form-label" for="account-email">
                {{ $t('seller_registration.pending.change_email.label') }}
            </label>
            <input
                id="account-email"
                v-model="form.email"
                type="email"
                class="form-control"
                :class="{ 'is-invalid': form.errors.email }"
                autocomplete="email"
                required
            >
            <InputError :message="form.errors.email" />

            <BButton
                variant="soft-primary"
                type="submit"
                class="w-100 mt-3"
                :disabled="form.processing || form.email === email"
            >
                {{ $t('seller_registration.pending.change_email.submit') }}
            </BButton>
        </form>
    </div>
</template>
