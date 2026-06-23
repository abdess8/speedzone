<script setup>
import { ref, onMounted } from "vue";
import { Link } from "@inertiajs/vue3";
import axios from "axios";

const props = defineProps({
  objectType: { type: String, required: true },
  objectId: { type: [Number, String], required: true },
});

const tickets = ref([]);
const canCreate = ref(false);
const loading = ref(true);

const loadTickets = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(route("support-tickets.for-object"), {
      params: { object_type: props.objectType, object_id: props.objectId },
    });
    tickets.value = data.data ?? [];
    canCreate.value = data.can_create ?? false;
  } catch {
    tickets.value = [];
  } finally {
    loading.value = false;
  }
};

const createUrl = () =>
  route("support-tickets.create", { object_type: props.objectType, object_id: props.objectId });

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : "—");

onMounted(loadTickets);
</script>

<template>
  <BCard no-body>
    <BCardHeader class="d-flex align-items-center">
      <h5 class="card-title mb-0 flex-grow-1">
        <i class="ri-customer-service-2-line align-bottom me-1"></i>
        {{ $t('support_tickets.panel.title') }}
      </h5>
      <Link v-if="canCreate" :href="createUrl()" class="btn btn-soft-success btn-sm">
        <i class="ri-add-line align-bottom me-1"></i> {{ $t('support_tickets.panel.create') }}
      </Link>
    </BCardHeader>
    <BCardBody>
      <div v-if="loading" class="text-center text-muted py-3">
        <span class="spinner-border spinner-border-sm me-2"></span>
        …
      </div>
      <div v-else-if="tickets.length === 0" class="text-muted text-center py-3">
        {{ $t('support_tickets.panel.empty') }}
      </div>
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light text-muted">
            <tr>
              <th>{{ $t('support_tickets.table.reference') }}</th>
              <th>{{ $t('support_tickets.table.subject') }}</th>
              <th>{{ $t('support_tickets.table.status') }}</th>
              <th>{{ $t('support_tickets.table.created_at') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ticket in tickets" :key="ticket.id">
              <td>
                <Link :href="route('support-tickets.show', ticket.id)" class="fw-semibold">
                  {{ ticket.reference }}
                </Link>
              </td>
              <td class="text-truncate" style="max-width: 160px">{{ ticket.subject }}</td>
              <td>
                <span class="badge" :class="`bg-${ticket.status_color}-subtle text-${ticket.status_color}`">
                  {{ ticket.status_label }}
                </span>
              </td>
              <td class="text-muted fs-13">{{ formatDate(ticket.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BCardBody>
  </BCard>
</template>
