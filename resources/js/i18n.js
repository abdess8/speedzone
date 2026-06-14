import { createI18n } from 'vue-i18n';

import ar from './lang/ar.json';
import ch from './lang/ch.json';
import en from './lang/en.json';
import fr from './lang/fr.json';
import gr from './lang/gr.json';
import it from './lang/it.json';
import ru from './lang/ru.json';
import sp from './lang/sp.json';

const messages = {
  ar,
  ch,
  en,
  fr,
  gr,
  it,
  ru,
  sp,
};

const defaultLocale = 'fr';

const resolveInitialLocale = () => {
  const stored = sessionStorage.getItem('locale');
  if (stored === 'fr' || stored === 'en') {
    return stored;
  }

  return defaultLocale;
};

const i18n = createI18n({
  legacy: true,
  locale: resolveInitialLocale(),
  fallbackLocale: 'en',
  messages,
});

function laravelToVueI18n(value) {
  if (typeof value === 'string') {
    return value.replace(/:(\w+)/g, '{$1}');
  }

  if (Array.isArray(value)) {
    return value.map(laravelToVueI18n);
  }

  if (value && typeof value === 'object') {
    return Object.fromEntries(
      Object.entries(value).map(([key, nested]) => [key, laravelToVueI18n(nested)])
    );
  }

  return value;
}

export function syncLocaleFromPage(pageProps) {
  const locale = pageProps?.locale ?? defaultLocale;
  const translations = pageProps?.translations ?? {};

  i18n.global.locale = locale;
  sessionStorage.setItem('locale', locale);

  Object.entries(translations).forEach(([group, strings]) => {
    i18n.global.mergeLocaleMessage(locale, { [group]: laravelToVueI18n(strings) });
  });
}

export default i18n;
