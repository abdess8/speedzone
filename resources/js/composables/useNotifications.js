import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const notifications = ref([]);
const unreadCount = ref(0);
const loading = ref(false);
const initialized = ref(false);
let echoChannel = null;

function normalizeNotification(payload) {
    const raw = payload?.data ?? payload ?? {};
    const display = resolveDisplay(raw, payload);

    return {
        id: payload.id ?? crypto.randomUUID(),
        type: display.type,
        title: display.title,
        message: display.message,
        reference: raw.reference ?? payload.reference ?? null,
        url: raw.url ?? payload.url ?? null,
        data: raw,
        read_at: payload.read_at ?? null,
        created_at: payload.created_at ?? new Date().toISOString(),
        is_read: Boolean(payload.read_at ?? payload.is_read),
    };
}

function resolveDisplay(raw, payload = {}) {
    const topTitle = payload.title ?? raw.title;
    const topMessage = payload.message ?? raw.message;

    if (topTitle && topMessage) {
        return {
            type: normalizeType(raw.type ?? payload.type),
            title: topTitle,
            message: topMessage,
        };
    }

    switch (raw.type) {
        case 'support.ticket.created':
            return {
                type: 'ticket_created',
                title: 'New support ticket',
                message: raw.subject
                    ? `${raw.reference}: ${raw.subject}`
                    : `New ticket ${raw.reference ?? ''}`,
            };
        case 'support.ticket.reply':
            return {
                type: 'ticket_message',
                title: 'New ticket message',
                message: `New message on ticket ${raw.reference ?? ''}`,
            };
        case 'support.ticket.assigned':
            return {
                type: 'system_notifications',
                title: 'Ticket assigned',
                message: `You have been assigned to ticket ${raw.reference ?? ''}`,
            };
        default:
            return {
                type: normalizeType(raw.type ?? payload.type),
                title: raw.subject ?? raw.reference ?? topTitle ?? 'Notification',
                message: topMessage ?? raw.reference ?? raw.subject ?? '',
            };
    }
}

function normalizeType(type) {
    const map = {
        'support.ticket.created': 'ticket_created',
        'support.ticket.reply': 'ticket_message',
        'support.ticket.assigned': 'system_notifications',
    };

    return map[type] ?? type ?? 'system_notifications';
}

async function fetchNotifications() {
    loading.value = true;

    try {
        const { data } = await axios.get(route('notifications.index'));
        notifications.value = (data.data ?? []).map(normalizeNotification);
        unreadCount.value = data.unread_count ?? 0;
        initialized.value = true;
    } finally {
        loading.value = false;
    }
}

async function markAsRead(id) {
    const { data } = await axios.post(route('notifications.read', { notification: id }));
    const updated = normalizeNotification(data.data);

    notifications.value = notifications.value.map((item) =>
        item.id === id ? updated : item,
    );
    unreadCount.value = data.unread_count ?? 0;
}

async function markAllAsRead() {
    const { data } = await axios.post(route('notifications.read-all'));
    notifications.value = notifications.value.map((item) => ({
        ...item,
        is_read: true,
        read_at: item.read_at ?? new Date().toISOString(),
    }));
    unreadCount.value = data.unread_count ?? 0;
}

function prependNotification(payload) {
    const normalized = normalizeNotification(payload);

    if (notifications.value.some((item) => item.id === normalized.id)) {
        return;
    }

    notifications.value.unshift(normalized);

    if (!normalized.is_read) {
        unreadCount.value += 1;
    }

    if (notifications.value.length > 50) {
        notifications.value.pop();
    }
}

function subscribeToChannel(userId) {
    if (!userId || !window.Echo || echoChannel) {
        return;
    }

    const channelName = `user.${userId}`;
    echoChannel = window.Echo.private(channelName);

    echoChannel.notification((notification) => {
        prependNotification(notification);
    });
}

function unsubscribeFromChannel(userId) {
    if (window.Echo && userId) {
        window.Echo.leave(`user.${userId}`);
    }

    echoChannel = null;
}

export function useNotifications() {
    const page = usePage();
    const userId = computed(() => page.props.auth?.user?.id ?? null);

    onMounted(() => {
        if (!initialized.value) {
            unreadCount.value = page.props.notifications?.unread_count ?? 0;
            fetchNotifications();
        }

        subscribeToChannel(userId.value);
    });

    onUnmounted(() => {
        unsubscribeFromChannel(userId.value);
    });

    return {
        notifications,
        unreadCount,
        loading,
        fetchNotifications,
        markAsRead,
        markAllAsRead,
    };
}

export function notificationIcon(type) {
    const icons = {
        invoice_generated: 'bx-receipt',
        ticket_created: 'bx-support',
        ticket_message: 'bx-message-dots',
        ticket_closed: 'bx-check-circle',
        return_requested: 'bx-undo',
        stock_pickup_requested: 'bx-package',
        system_notifications: 'bx-cog',
    };

    return icons[type] ?? 'bx-bell';
}

export function formatNotificationDate(isoDate) {
    if (!isoDate) {
        return '';
    }

    const date = new Date(isoDate);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 1) {
        return 'Just now';
    }

    if (diffMins < 60) {
        return `${diffMins}m ago`;
    }

    const diffHours = Math.floor(diffMins / 60);

    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }

    return date.toLocaleDateString();
}
