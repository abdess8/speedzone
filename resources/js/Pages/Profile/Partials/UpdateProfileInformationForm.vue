<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (!photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <FormSection @submitted="updateProfileInformation" :title="$t('profile.profile_information.title')" :description="$t('profile.profile_information.description')">

        <template #form>
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="mb-3">
                <div class="mb-2">
                    <input ref="photoInput" type="file" class="d-none form-control" @change="updatePhotoPreview">
                    <InputLabel for="photo" :value="$t('profile.profile_information.photo')" />
                </div>

                <div class="mb-2">
                    <div v-show="!photoPreview">
                        <img
                            :src="user.profile_photo_url"
                            :alt="user.name"
                            class="rounded-circle object-fit-cover"
                            width="100"
                            height="100"
                        >
                    </div>

                    <div v-show="photoPreview">
                        <img
                            :src="photoPreview"
                            :alt="user.name"
                            class="rounded-circle object-fit-cover"
                            width="100"
                            height="100"
                        >
                    </div>
                </div>

                <BButton variant="primary" class="me-2 btn-sm" type="button" @click.prevent="selectNewPhoto">{{ $t('profile.profile_information.select_new_photo') }}</BButton>
                <BButton v-if="user.profile_photo_path || user.photo" variant="danger" type="button" class="btn-sm" @click.prevent="deletePhoto">{{ $t('profile.profile_information.remove_photo') }}</BButton>

                <div class="text-danger mt-2">
                    <span>{{ form.errors.photo }}</span>
                </div>
            </div>

            <div class="mb-3">
                <InputLabel for="name" :value="$t('profile.profile_information.name')" />
                <TextInput id="name" v-model="form.name" type="text" required autocomplete="name" :class="{ 'is-invalid': form.errors.name }" />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <div class="mb-3">
                <InputLabel for="email" :value="$t('profile.profile_information.email')" />
                <TextInput id="email" v-model="form.email" type="email" required autocomplete="username" :class="{ 'is-invalid': form.errors.email }" />
                <InputError :message="form.errors.email" class="mt-2" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <p class="text-sm mt-2 text-muted">
                        {{ $t('profile.profile_information.unverified') }}

                        <Link :href="route('verification.send')" method="post" as="button" class="btn btn-sm btn-warning" @click.prevent="sendEmailVerification">
                        {{ $t('profile.profile_information.resend_verification') }}
                        </Link>
                    </p>

                    <div v-show="verificationLinkSent" class="alert alert-success text-success">
                        {{ $t('profile.profile_information.verification_sent') }}
                    </div>
                </div>
            </div>
        </template>

        <template #actions>
            <BButton variant="primary w-100" type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">{{ $t('common.save') }}</BButton>
            <p v-if="form.recentlySuccessful" class="alert alert-info mt-3">{{ $t('profile.profile_information.saved') }}</p>
        </template>
    </FormSection>
</template>
