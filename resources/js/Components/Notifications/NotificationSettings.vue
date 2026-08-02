<script setup>
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    initialPreferences: {
        type: Object,
        default: null,
    },
});

const saving = ref(false);
const saved = ref(false);

const form = reactive({
    enabled: true,
    invoice_generated: true,
    ticket_created: true,
    ticket_message: true,
    ticket_closed: true,
    return_requested: true,
    system_notifications: true,
});

const typeKeys = [
    'invoice_generated',
    'ticket_created',
    'ticket_message',
    'ticket_closed',
    'return_requested',
    'system_notifications',
];

const applyPreferences = (data) => {
    if (!data) {
        return;
    }

    Object.keys(form).forEach((key) => {
        if (key in data) {
            form[key] = Boolean(data[key]);
        }
    });
};

onMounted(async () => {
    if (props.initialPreferences) {
        applyPreferences(props.initialPreferences);
        return;
    }

    try {
        const { data } = await axios.get(route('notification-preferences.show'));
        applyPreferences(data.data);
    } catch {
        // Keep defaults when preferences cannot be loaded.
    }
});

const save = async () => {
    saving.value = true;
    saved.value = false;

    try {
        const { data } = await axios.put(route('notification-preferences.update'), { ...form });
        applyPreferences(data.data);
        saved.value = true;
    } finally {
        saving.value = false;
    }
};
</script>

<template>
  <BCard no-body class="mt-4">
    <BCardHeader>
      <BCardTitle>{{ $t('notifications.settings.title') }}</BCardTitle>
    </BCardHeader>
    <BCardBody>
      <p class="text-muted mb-4">{{ $t('notifications.settings.description') }}</p>

      <div class="mb-4 pb-3 border-bottom">
        <BFormCheckbox v-model="form.enabled" switch size="lg">
          {{ $t('notifications.settings.master_toggle') }}
        </BFormCheckbox>
        <small class="text-muted d-block mt-1">{{ $t('notifications.settings.master_toggle_help') }}</small>
      </div>

      <div class="vstack gap-3">
        <BFormCheckbox
          v-for="typeKey in typeKeys"
          :key="typeKey"
          v-model="form[typeKey]"
          switch
          :disabled="!form.enabled"
        >
          {{ $t(`notifications.types.${typeKey}`) }}
        </BFormCheckbox>
      </div>

      <div class="mt-4 d-flex align-items-center gap-3">
        <BButton variant="primary" :disabled="saving" @click="save">
          <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
          {{ $t('common.save_changes') }}
        </BButton>
        <span v-if="saved" class="text-success fs-13">{{ $t('notifications.settings.saved') }}</span>
      </div>
    </BCardBody>
  </BCard>
</template>
