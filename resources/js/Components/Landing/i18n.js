import { computed, ref } from 'vue';

import ar from './messages/ar';
import en from './messages/en';
import fr from './messages/fr';

/**
 * Translations for the public marketing site.
 *
 * The dashboard runs on vue-i18n fed by Laravel through Inertia props, which
 * only ships the locales an authenticated user can pick (fr / en). The landing
 * page has to offer Arabic to anonymous visitors and to remember their choice
 * without a session, so it keeps its own tiny, self-contained layer instead of
 * widening the application-wide locale.
 */

export const DEFAULT_LOCALE = 'fr';

export const LOCALES = [
    { code: 'fr', label: 'Français', short: 'FR', dir: 'ltr' },
    { code: 'en', label: 'English', short: 'EN', dir: 'ltr' },
    { code: 'ar', label: 'العربية', short: 'AR', dir: 'rtl' },
];

const MESSAGES = { fr, en, ar };

const STORAGE_KEY = 'sz.landing.locale';

const isSupported = (code) => LOCALES.some((entry) => entry.code === code);

const storedLocale = () => {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return isSupported(stored) ? stored : DEFAULT_LOCALE;
    } catch (error) {
        // Private browsing modes can throw on access rather than return null.
        return DEFAULT_LOCALE;
    }
};

// Module scope on purpose: every landing component reads the same ref, so the
// switcher in the navbar re-renders the whole page without any prop drilling.
const locale = ref(typeof window === 'undefined' ? DEFAULT_LOCALE : storedLocale());

const resolve = (code, path) =>
    path.split('.').reduce((carry, segment) => (carry == null ? carry : carry[segment]), MESSAGES[code]);

const interpolate = (value, params) =>
    Object.entries(params).reduce(
        (carry, [key, replacement]) => carry.replaceAll(`{${key}}`, String(replacement)),
        value
    );

export function useLandingLocale() {
    const current = computed(() => LOCALES.find((entry) => entry.code === locale.value) ?? LOCALES[0]);
    const dir = computed(() => current.value.dir);
    const isRtl = computed(() => dir.value === 'rtl');

    /**
     * Translate a dotted key, falling back to French and then to the key
     * itself so a missing string never renders as an empty element.
     */
    const t = (path, params = {}) => {
        const value = resolve(locale.value, path) ?? resolve(DEFAULT_LOCALE, path);

        if (typeof value !== 'string') {
            return path;
        }

        return interpolate(value, params);
    };

    /**
     * Translate a value the server already sent in French (city and region
     * names), keyed by its stable uppercase identifier.
     */
    const tName = (group, key, fallback = '') => {
        const value = resolve(locale.value, `${group}.${key}`);

        return typeof value === 'string' ? value : fallback || key;
    };

    const setLocale = (code) => {
        if (!isSupported(code)) {
            return;
        }

        locale.value = code;

        try {
            window.localStorage.setItem(STORAGE_KEY, code);
        } catch (error) {
            // Persistence is a nicety; the switch itself must still work.
        }

        if (typeof document !== 'undefined') {
            document.documentElement.setAttribute('lang', code);
        }
    };

    return { locale, locales: LOCALES, current, dir, isRtl, t, tName, setLocale };
}
