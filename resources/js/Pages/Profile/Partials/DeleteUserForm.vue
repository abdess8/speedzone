<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import BottomSheet from '@/Components/BottomSheet.vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    setTimeout(() => passwordInput.value.focus(), 250);
};

const deleteUser = () => {
    form.delete(route('current-user.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.reset();
};
</script>

<template>
    <BCard no-body>
        <BCardHeader>
            <BCardTitle>{{ $t('profile.delete_account.title') }}</BCardTitle>
            <p class="text-muted mb-0">{{ $t('profile.delete_account.description') }}</p>
        </BCardHeader>
        <BCardBody class="p-4">
            <div class="text-sm text-muted">
                {{ $t('profile.delete_account.info') }}
            </div>

            <div class="mt-5">
                <BButton variant="danger w-100" @click="confirmUserDeletion">{{ $t('profile.delete_account.button') }}</BButton>
            </div>

            <BottomSheet :show="confirmingUserDeletion" :title="$t('profile.delete_account.modal_title')" @close="closeModal">
                <p class="text-muted">{{ $t('profile.delete_account.modal_description') }}</p>
                <div class="mb-3">
                    <TextInput ref="passwordInput" v-model="form.password" type="password" :placeholder="$t('profile.delete_account.password')" required autocomplete="current-password" @keyup.enter="deleteUser" :class="{ 'is-invalid': form.errors.password }" />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="text-end">
                    <BButton variant="danger" @click="closeModal">{{ $t('common.close') }}</BButton>
                    <BButton variant="primary" class="ms-1" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="deleteUser">{{ $t('profile.delete_account.confirm') }}</BButton>
                </div>
            </BottomSheet>
        </BCardBody>
    </BCard>
</template>
