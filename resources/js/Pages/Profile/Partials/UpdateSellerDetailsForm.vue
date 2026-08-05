<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Multiselect from '@vueform/multiselect';
import '@vueform/multiselect/themes/default.css';

const props = defineProps({
    user: { type: Object, required: true },
    cities: { type: Array, default: () => [] },
});

const DOCUMENTS = ['rib_attachment', 'cin_front_attachment', 'cin_back_attachment'];

const form = useForm({
    _method: 'PUT',
    phone_number: props.user.phone_number ?? '',
    city_id: props.user.city_id ?? null,
    address: props.user.address ?? '',
    pickup_address_1: props.user.pickup_address_1 ?? '',
    pickup_address_2: props.user.pickup_address_2 ?? '',
    cin: props.user.cin ?? '',
    ice_number: props.user.ice_number ?? '',
    bank_name: props.user.bank_name ?? '',
    rib: props.user.rib ?? '',
    rib_attachment: null,
    cin_front_attachment: null,
    cin_back_attachment: null,
});

const cityOptions = computed(() =>
    props.cities.map((city) => ({ value: city.id, label: city.name }))
);

// Previews of files picked in this session, before they reach the server.
const pending = reactive({});
const inputs = reactive({});
const cinFlipped = ref(false);

const isPdf = (name = '') => name.toLowerCase().endsWith('.pdf');

/**
 * What to render for a document slot: the file just picked if there is one,
 * otherwise whatever is already stored, otherwise nothing.
 */
const documentOf = (field) => {
    if (pending[field]) {
        return pending[field];
    }

    const url = props.user[`${field}_url`];

    if (!url) {
        return null;
    }

    const name = decodeURIComponent(url.split('/').pop() ?? '');

    return { url, name, isPdf: isPdf(name) };
};

const ribDocument = computed(() => documentOf('rib_attachment'));
const cinFront = computed(() => documentOf('cin_front_attachment'));
const cinBack = computed(() => documentOf('cin_back_attachment'));

const selectFile = (field) => inputs[field]?.click();

const onFileSelected = (field, event) => {
    const file = event.target.files?.[0];

    if (!file) return;

    form[field] = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        pending[field] = { url: e.target.result, name: file.name, isPdf: isPdf(file.name) };
    };
    reader.readAsDataURL(file);
};

const removeDocument = (field) => {
    if (pending[field]) {
        delete pending[field];
        form[field] = null;

        if (inputs[field]) {
            inputs[field].value = null;
        }

        return;
    }

    router.delete(route('user-seller-profile.documents.destroy', field), {
        preserveScroll: true,
    });
};

const submit = () => {
    form.post(route('user-seller-profile.update'), {
        errorBag: 'updateSellerProfile',
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            DOCUMENTS.forEach((field) => {
                delete pending[field];
                form[field] = null;

                if (inputs[field]) {
                    inputs[field].value = null;
                }
            });
        },
    });
};
</script>

<template>
    <FormSection
        @submitted="submit"
        :title="$t('profile.seller_details.title')"
        :description="$t('profile.seller_details.description')"
    >
        <template #form>
            <BRow class="g-3">
                <BCol md="6">
                    <InputLabel for="phone_number" :value="$t('profile.completion.fields.phone_number')" />
                    <TextInput id="phone_number" v-model="form.phone_number" type="text" autocomplete="tel" :class="{ 'is-invalid': form.errors.phone_number }" />
                    <InputError :message="form.errors.phone_number" />
                </BCol>

                <BCol md="6">
                    <InputLabel for="city_id" :value="$t('profile.completion.fields.city_id')" />
                    <Multiselect
                        id="city_id"
                        v-model="form.city_id"
                        :options="cityOptions"
                        :searchable="true"
                        :close-on-select="true"
                        :placeholder="$t('profile.seller_details.city_placeholder')"
                        class="mt-1"
                        :class="{ 'is-invalid': form.errors.city_id }"
                    />
                    <InputError :message="form.errors.city_id" />
                </BCol>

                <BCol md="12">
                    <InputLabel for="address" :value="$t('profile.completion.fields.address')" />
                    <textarea id="address" v-model="form.address" rows="2" class="form-control" :class="{ 'is-invalid': form.errors.address }"></textarea>
                    <InputError :message="form.errors.address" />
                </BCol>

                <BCol md="6">
                    <InputLabel for="pickup_address_1" :value="$t('profile.completion.fields.pickup_address_1')" />
                    <textarea id="pickup_address_1" v-model="form.pickup_address_1" rows="2" class="form-control" :class="{ 'is-invalid': form.errors.pickup_address_1 }"></textarea>
                    <InputError :message="form.errors.pickup_address_1" />
                </BCol>

                <BCol md="6">
                    <InputLabel for="pickup_address_2" :value="$t('profile.completion.fields.pickup_address_2')" />
                    <textarea id="pickup_address_2" v-model="form.pickup_address_2" rows="2" class="form-control" :class="{ 'is-invalid': form.errors.pickup_address_2 }"></textarea>
                    <InputError :message="form.errors.pickup_address_2" />
                </BCol>

                <BCol md="6">
                    <InputLabel for="cin" :value="$t('profile.completion.fields.cin')" />
                    <TextInput id="cin" v-model="form.cin" type="text" :class="{ 'is-invalid': form.errors.cin }" />
                    <InputError :message="form.errors.cin" />
                </BCol>

                <BCol md="6">
                    <InputLabel for="ice_number" :value="$t('profile.completion.fields.ice_number')" />
                    <TextInput id="ice_number" v-model="form.ice_number" type="text" :class="{ 'is-invalid': form.errors.ice_number }" />
                    <InputError :message="form.errors.ice_number" />
                </BCol>

                <BCol md="6">
                    <InputLabel for="bank_name" :value="$t('profile.completion.fields.bank_name')" />
                    <TextInput id="bank_name" v-model="form.bank_name" type="text" :class="{ 'is-invalid': form.errors.bank_name }" />
                    <InputError :message="form.errors.bank_name" />
                </BCol>

                <BCol md="6">
                    <InputLabel for="rib" :value="$t('profile.completion.fields.rib')" />
                    <TextInput id="rib" v-model="form.rib" type="text" :class="{ 'is-invalid': form.errors.rib }" />
                    <InputError :message="form.errors.rib" />
                </BCol>
            </BRow>

            <hr class="my-4">

            <h6 class="mb-1">{{ $t('profile.seller_details.documents_title') }}</h6>
            <p class="text-muted fs-13">{{ $t('profile.seller_details.documents_hint') }}</p>

            <BRow class="g-4">
                <BCol lg="6">
                    <InputLabel :value="$t('profile.completion.fields.rib_attachment')" />

                    <div class="document-preview border rounded p-2 mt-1">
                        <template v-if="ribDocument">
                            <embed
                                v-if="ribDocument.isPdf"
                                :src="ribDocument.url"
                                type="application/pdf"
                                class="w-100 rounded"
                                style="height: 190px"
                            >
                            <img
                                v-else
                                :src="ribDocument.url"
                                :alt="ribDocument.name"
                                class="w-100 rounded object-fit-contain bg-light"
                                style="height: 190px"
                            >

                            <p class="text-truncate fs-12 text-muted mt-2 mb-2">
                                <i class="ri-attachment-2 align-middle me-1"></i>{{ ribDocument.name }}
                            </p>
                        </template>

                        <div
                            v-else
                            class="d-flex flex-column align-items-center justify-content-center text-muted bg-light rounded"
                            style="height: 190px"
                        >
                            <i class="ri-bank-card-line fs-24 mb-1"></i>
                            <span class="fs-12">{{ $t('profile.seller_details.no_document') }}</span>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <BButton size="sm" variant="soft-primary" type="button" @click="selectFile('rib_attachment')">
                                <i class="ri-upload-2-line align-bottom me-1"></i>
                                {{ ribDocument ? $t('profile.seller_details.replace') : $t('profile.seller_details.upload') }}
                            </BButton>
                            <a
                                v-if="ribDocument && !pending.rib_attachment"
                                :href="ribDocument.url"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-sm btn-soft-secondary"
                            >
                                <i class="ri-external-link-line align-bottom me-1"></i>{{ $t('profile.seller_details.open') }}
                            </a>
                            <BButton v-if="ribDocument" size="sm" variant="soft-danger" type="button" @click="removeDocument('rib_attachment')">
                                <i class="ri-delete-bin-line align-bottom"></i>
                            </BButton>
                        </div>

                        <input
                            :ref="(el) => (inputs.rib_attachment = el)"
                            type="file"
                            class="d-none"
                            accept=".pdf,image/*"
                            @change="onFileSelected('rib_attachment', $event)"
                        >
                    </div>
                    <InputError :message="form.errors.rib_attachment" />
                </BCol>

                <BCol lg="6">
                    <InputLabel :value="$t('profile.seller_details.cin_card')" />

                    <!-- One card with two sides: clicking it turns the ID over,
                         the way it is handled on a counter. -->
                    <div class="cin-card mt-1" :class="{ 'is-flipped': cinFlipped }">
                        <div class="cin-card-inner" @click="cinFlipped = !cinFlipped">
                            <div class="cin-card-face">
                                <img
                                    v-if="cinFront && !cinFront.isPdf"
                                    :src="cinFront.url"
                                    :alt="$t('profile.completion.fields.cin_front_attachment')"
                                    class="cin-card-image"
                                >
                                <div v-else class="cin-card-placeholder">
                                    <i :class="cinFront ? 'ri-file-pdf-2-line' : 'ri-id-card-line'" class="fs-24 mb-1"></i>
                                    <span class="fs-12">{{ cinFront ? cinFront.name : $t('profile.seller_details.no_document') }}</span>
                                </div>
                                <span class="cin-card-tag">{{ $t('profile.seller_details.cin_front') }}</span>
                            </div>

                            <div class="cin-card-face cin-card-back">
                                <img
                                    v-if="cinBack && !cinBack.isPdf"
                                    :src="cinBack.url"
                                    :alt="$t('profile.completion.fields.cin_back_attachment')"
                                    class="cin-card-image"
                                >
                                <div v-else class="cin-card-placeholder">
                                    <i :class="cinBack ? 'ri-file-pdf-2-line' : 'ri-id-card-line'" class="fs-24 mb-1"></i>
                                    <span class="fs-12">{{ cinBack ? cinBack.name : $t('profile.seller_details.no_document') }}</span>
                                </div>
                                <span class="cin-card-tag">{{ $t('profile.seller_details.cin_back') }}</span>
                            </div>
                        </div>
                    </div>

                    <p class="text-muted fs-12 mt-2 mb-2">
                        <i class="ri-refresh-line align-middle me-1"></i>{{ $t('profile.seller_details.cin_flip_hint') }}
                    </p>

                    <div class="d-flex flex-wrap gap-2">
                        <BButton size="sm" variant="soft-primary" type="button" @click="selectFile('cin_front_attachment')">
                            {{ cinFront ? $t('profile.seller_details.replace_front') : $t('profile.seller_details.upload_front') }}
                        </BButton>
                        <BButton size="sm" variant="soft-primary" type="button" @click="selectFile('cin_back_attachment')">
                            {{ cinBack ? $t('profile.seller_details.replace_back') : $t('profile.seller_details.upload_back') }}
                        </BButton>
                        <BButton v-if="cinFront" size="sm" variant="soft-danger" type="button" @click="removeDocument('cin_front_attachment')">
                            <i class="ri-delete-bin-line align-bottom me-1"></i>{{ $t('profile.seller_details.cin_front') }}
                        </BButton>
                        <BButton v-if="cinBack" size="sm" variant="soft-danger" type="button" @click="removeDocument('cin_back_attachment')">
                            <i class="ri-delete-bin-line align-bottom me-1"></i>{{ $t('profile.seller_details.cin_back') }}
                        </BButton>
                    </div>

                    <input
                        :ref="(el) => (inputs.cin_front_attachment = el)"
                        type="file"
                        class="d-none"
                        accept=".pdf,image/*"
                        @change="onFileSelected('cin_front_attachment', $event)"
                    >
                    <input
                        :ref="(el) => (inputs.cin_back_attachment = el)"
                        type="file"
                        class="d-none"
                        accept=".pdf,image/*"
                        @change="onFileSelected('cin_back_attachment', $event)"
                    >

                    <InputError :message="form.errors.cin_front_attachment" />
                    <InputError :message="form.errors.cin_back_attachment" />
                </BCol>
            </BRow>
        </template>

        <template #actions>
            <BButton variant="primary w-100" type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                {{ $t('common.save') }}
            </BButton>
            <p v-if="form.recentlySuccessful" class="alert alert-info mt-3">{{ $t('profile.seller_details.saved') }}</p>
        </template>
    </FormSection>
</template>

<style scoped>
.cin-card {
    perspective: 1000px;
    height: 190px;
}

.cin-card-inner {
    position: relative;
    width: 100%;
    height: 100%;
    cursor: pointer;
    transition: transform 0.6s;
    transform-style: preserve-3d;
}

.cin-card.is-flipped .cin-card-inner {
    transform: rotateY(180deg);
}

.cin-card-face {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid var(--vz-border-color);
    border-radius: 0.5rem;
    background-color: var(--vz-light);
    backface-visibility: hidden;
}

.cin-card-back {
    transform: rotateY(180deg);
}

.cin-card-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.cin-card-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: var(--vz-secondary-color);
    text-align: center;
    padding: 0 1rem;
}

.cin-card-tag {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    padding: 0.1rem 0.5rem;
    border-radius: 0.25rem;
    background-color: rgba(0, 0, 0, 0.6);
    color: #fff;
    font-size: 0.7rem;
}

/* Reduced motion: turn the card over without the spin. */
@media (prefers-reduced-motion: reduce) {
    .cin-card-inner {
        transition: none;
    }
}
</style>
