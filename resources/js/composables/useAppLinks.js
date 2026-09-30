import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Absolute back-office URLs when the vitrine and the app live on different
 * hosts. Relative paths otherwise (local / tests).
 */
export function useAppLinks() {
    const page = usePage();
    const appUrl = computed(() => page.props.domains?.app_url ?? null);

    const toApp = (path = '/') => {
        const suffix = path.startsWith('/') ? path : `/${path}`;

        if (!appUrl.value) {
            return suffix;
        }

        return `${String(appUrl.value).replace(/\/$/, '')}${suffix}`;
    };

    return { appUrl, toApp };
}
