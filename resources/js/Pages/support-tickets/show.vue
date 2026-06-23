<script setup>
import { ref, computed, onMounted, nextTick } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import InputError from "@/Components/InputError.vue";
import UserAvatar from "@/Components/UserAvatar.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  ticket: { type: Object, required: true },
  agents: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const authUser = computed(() => usePage().props.auth?.user ?? {});

const ticket = computed(() => props.ticket);
const messages = computed(() => props.ticket.messages ?? []);
const attachments = computed(() => props.ticket.attachments ?? []);

const chatContainer = ref(null);

const formatDateTime = (value) => (value ? new Date(value).toLocaleString() : "—");

const isOwnMessage = (msg) => msg.sender_id === authUser.value.id;

const scrollToBottom = () => {
  nextTick(() => {
    if (chatContainer.value) {
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
  });
};

// Reply form
const replyForm = useForm({
  message: "",
  attachment: null,
});

const onReplyAttachmentChange = (event) => {
  replyForm.attachment = event.target.files[0] || null;
};

const submitReply = () => {
  replyForm.post(route("support-tickets.messages.store", ticket.value.id), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      replyForm.reset();
      scrollToBottom();
    },
  });
};

// Assign modal
const showAssignModal = ref(false);
const assignForm = useForm({ assigned_to: ticket.value.assigned_to ?? "" });

const submitAssign = () => {
  assignForm.post(route("support-tickets.assign", ticket.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      showAssignModal.value = false;
    },
  });
};

// Status modal
const showStatusModal = ref(false);
const statusForm = useForm({ status: ticket.value.status });

const submitStatus = () => {
  statusForm.post(route("support-tickets.status", ticket.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      showStatusModal.value = false;
    },
  });
};

const closeTicket = () => {
  Swal.fire({
    title: t("support_tickets.confirms.close_title"),
    text: t("support_tickets.confirms.close_text"),
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: t("support_tickets.confirms.confirm"),
    cancelButtonText: t("common.cancel"),
    confirmButtonColor: "#f06548",
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route("support-tickets.close", ticket.value.id), {}, { preserveScroll: true });
    }
  });
};

const isImage = (url) => url && /\.(jpg|jpeg|png|gif|webp)(\?|$)/i.test(url);

onMounted(() => {
  scrollToBottom();
  const flash = usePage().props?.flash ?? {};
  if (flash.success) {
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: flash.success, showConfirmButton: false, timer: 3000, timerProgressBar: true });
  }
  if (flash.error) {
    Swal.fire({ toast: true, position: "top-end", icon: "error", title: flash.error, showConfirmButton: false, timer: 4000, timerProgressBar: true });
  }
});
</script>

<template>
  <Layout>
    <PageHeader
      :title="$t('support_tickets.detail.title', { reference: ticket.reference })"
      :pageTitle="$t('support_tickets.detail.page_title')"
    />

    <BRow>
      <BCol lg="8">
        <!-- Ticket header -->
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center flex-wrap gap-2">
            <h5 class="card-title mb-0 flex-grow-1">
              {{ ticket.reference }}
              <span class="badge ms-2" :class="`bg-${ticket.status_color}-subtle text-${ticket.status_color}`">
                <i :class="ticket.status_icon" class="align-bottom me-1"></i>{{ ticket.status_label }}
              </span>
            </h5>
            <span class="badge" :class="`bg-${ticket.category_color}-subtle text-${ticket.category_color}`">
              <i :class="ticket.category_icon" class="align-bottom me-1"></i>{{ ticket.category_label }}
            </span>
          </BCardHeader>
          <BCardBody>
            <h6 class="fw-semibold mb-2">{{ ticket.subject }}</h6>
            <p class="text-muted mb-0">{{ ticket.message }}</p>

            <!-- Initial attachments -->
            <div v-if="attachments.length" class="mt-3">
              <div class="text-muted fs-13 mb-2">{{ $t('support_tickets.detail.attachments') }}</div>
              <div class="d-flex flex-wrap gap-2">
                <a
                  v-for="att in attachments"
                  :key="att.id"
                  :href="att.url"
                  target="_blank"
                  class="btn btn-soft-secondary btn-sm"
                >
                  <i class="ri-attachment-2 align-bottom me-1"></i>{{ att.file_name }}
                </a>
              </div>
            </div>
          </BCardBody>
        </BCard>

        <!-- Chat conversation -->
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">
              <i class="ri-chat-3-line align-bottom me-1"></i>
              {{ $t('support_tickets.detail.conversation') }}
            </h5>
          </BCardHeader>
          <BCardBody class="p-0">
            <div
              ref="chatContainer"
              class="p-3"
              style="max-height: 480px; overflow-y: auto; background: var(--vz-light, #f3f6f9)"
            >
              <div v-if="messages.length === 0" class="text-center text-muted py-4">
                {{ $t('support_tickets.detail.no_messages') }}
              </div>

              <div
                v-for="msg in messages"
                :key="msg.id"
                class="d-flex mb-3"
                :class="isOwnMessage(msg) ? 'justify-content-end' : 'justify-content-start'"
              >
                <div
                  class="d-flex gap-2"
                  :class="isOwnMessage(msg) ? 'flex-row-reverse' : ''"
                  style="max-width: 75%"
                >
                  <UserAvatar :user="msg.sender" :size="36" />
                  <div>
                    <div
                      class="d-flex align-items-center gap-2 mb-1"
                      :class="isOwnMessage(msg) ? 'justify-content-end' : ''"
                    >
                      <span class="fw-semibold fs-13">{{ msg.sender?.name ?? "—" }}</span>
                      <span class="text-muted fs-11">{{ formatDateTime(msg.created_at) }}</span>
                    </div>
                    <div
                      class="p-3 rounded-3"
                      :class="isOwnMessage(msg)
                        ? 'bg-primary text-white'
                        : 'bg-white border'"
                    >
                      <p v-if="msg.message" class="mb-0" style="white-space: pre-wrap">{{ msg.message }}</p>
                      <div v-if="msg.attachment_url" class="mt-2">
                        <a
                          v-if="!isImage(msg.attachment_url)"
                          :href="msg.attachment_url"
                          target="_blank"
                          class="btn btn-sm"
                          :class="isOwnMessage(msg) ? 'btn-light' : 'btn-soft-secondary'"
                        >
                          <i class="ri-attachment-2 align-bottom me-1"></i>
                          {{ msg.attachment_name ?? 'Attachment' }}
                        </a>
                        <a v-else :href="msg.attachment_url" target="_blank">
                          <img :src="msg.attachment_url" alt="attachment" class="rounded" style="max-width: 200px; max-height: 150px" />
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Reply form -->
            <div v-if="can.reply && !ticket.is_closed" class="border-top p-3">
              <form @submit.prevent="submitReply">
                <div class="mb-2">
                  <textarea
                    v-model="replyForm.message"
                    class="form-control"
                    rows="3"
                    :placeholder="$t('support_tickets.chat.placeholder')"
                    :class="{ 'is-invalid': replyForm.errors.message }"
                  ></textarea>
                  <InputError :message="replyForm.errors.message" />
                </div>
                <div class="d-flex align-items-center gap-2">
                  <input
                    type="file"
                    class="form-control form-control-sm"
                    accept=".pdf,image/*,.doc,.docx,.xls,.xlsx,.csv,.txt"
                    @change="onReplyAttachmentChange"
                  />
                  <BButton variant="primary" type="submit" :disabled="replyForm.processing" class="text-nowrap">
                    <i class="ri-send-plane-2-line align-bottom me-1"></i>
                    {{ $t('support_tickets.chat.send') }}
                  </BButton>
                </div>
              </form>
            </div>

            <div v-else-if="ticket.is_closed" class="border-top p-3 text-center text-muted">
              <i class="ri-lock-line align-bottom me-1"></i>
              {{ $t('support_tickets.detail.read_only') }}
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="4">
        <!-- Actions -->
        <BCard no-body>
          <BCardBody>
            <div class="d-grid gap-2">
              <BButton v-if="can.assign" variant="soft-primary" @click="showAssignModal = true">
                <i class="ri-user-add-line align-bottom me-1"></i> {{ $t('support_tickets.actions.assign') }}
              </BButton>
              <BButton v-if="can.update_status" variant="soft-info" @click="showStatusModal = true">
                <i class="ri-exchange-line align-bottom me-1"></i> {{ $t('support_tickets.actions.change_status') }}
              </BButton>
              <BButton v-if="can.close" variant="soft-danger" @click="closeTicket">
                <i class="ri-lock-line align-bottom me-1"></i> {{ $t('support_tickets.actions.close') }}
              </BButton>
              <Link :href="route('support-tickets.index')" class="btn btn-light">
                <i class="ri-arrow-left-line align-bottom me-1"></i> {{ $t('support_tickets.actions.back_to_list') }}
              </Link>
            </div>
          </BCardBody>
        </BCard>

        <!-- Ticket info -->
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('support_tickets.detail.info') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('support_tickets.table.seller') }}</span>
              <UserAvatar v-if="ticket.creator" :user="ticket.creator" :size="24" show-name />
              <span v-else>—</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('support_tickets.table.assigned') }}</span>
              <UserAvatar v-if="ticket.assignee" :user="ticket.assignee" :size="24" show-name />
              <span v-else class="text-muted">{{ $t('support_tickets.filters.unassigned') }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('support_tickets.table.created_at') }}</span>
              <span>{{ formatDateTime(ticket.created_at) }}</span>
            </div>
            <div v-if="ticket.last_reply_at" class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('support_tickets.table.last_reply') }}</span>
              <span>{{ formatDateTime(ticket.last_reply_at) }}</span>
            </div>
            <div v-if="ticket.closed_at" class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('support_tickets.actions.close') }}</span>
              <span>{{ formatDateTime(ticket.closed_at) }}</span>
            </div>
          </BCardBody>
        </BCard>

        <!-- Related object -->
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('support_tickets.detail.related_object') }}</h5>
          </BCardHeader>
          <BCardBody>
            <template v-if="ticket.object">
              <div class="d-flex align-items-center gap-2">
                <i :class="ticket.object_type_icon" class="text-muted fs-18"></i>
                <div>
                  <div class="text-muted fs-12">{{ ticket.object_type_label }}</div>
                  <Link v-if="ticket.object.url" :href="ticket.object.url" class="fw-semibold">
                    {{ ticket.object.reference }}
                  </Link>
                  <span v-else class="fw-semibold">{{ ticket.object.reference }}</span>
                </div>
              </div>
            </template>
            <span v-else class="text-muted">{{ $t('support_tickets.detail.no_object') }}</span>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <!-- Assign modal -->
    <BModal v-model="showAssignModal" :title="$t('support_tickets.assign.title')" hide-footer>
      <form @submit.prevent="submitAssign">
        <div class="mb-3">
          <label class="form-label">{{ $t('support_tickets.assign.select_agent') }}</label>
          <select v-model="assignForm.assigned_to" class="form-select">
            <option value="">{{ $t('support_tickets.assign.unassign') }}</option>
            <option v-for="agent in agents" :key="agent.id" :value="agent.id">
              {{ agent.name }} ({{ agent.email }})
            </option>
          </select>
          <InputError :message="assignForm.errors.assigned_to" />
        </div>
        <div class="hstack gap-2 justify-content-end">
          <BButton variant="light" type="button" @click="showAssignModal = false">{{ $t('common.cancel') }}</BButton>
          <BButton variant="primary" type="submit" :disabled="assignForm.processing">
            {{ $t('support_tickets.assign.submit') }}
          </BButton>
        </div>
      </form>
    </BModal>

    <!-- Status modal -->
    <BModal v-model="showStatusModal" :title="$t('support_tickets.status.title')" hide-footer>
      <form @submit.prevent="submitStatus">
        <div class="mb-3">
          <label class="form-label">{{ $t('support_tickets.status.select') }}</label>
          <select v-model="statusForm.status" class="form-select">
            <option v-for="s in ticket.next_statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
          <InputError :message="statusForm.errors.status" />
        </div>
        <div class="hstack gap-2 justify-content-end">
          <BButton variant="light" type="button" @click="showStatusModal = false">{{ $t('common.cancel') }}</BButton>
          <BButton variant="info" type="submit" :disabled="statusForm.processing">
            {{ $t('support_tickets.status.submit') }}
          </BButton>
        </div>
      </form>
    </BModal>
  </Layout>
</template>
