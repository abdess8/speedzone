<script setup>
import GlobalSearch from '@/Components/GlobalSearch.vue';
import NavModeSwitcher from '@/Components/NavModeSwitcher.vue';
import NotificationBell from '@/Components/Notifications/NotificationBell.vue';
import SettingsMenu from '@/Components/SettingsMenu.vue';
import StoreSwitcher from '@/Components/StoreSwitcher.vue';
import { layoutMethods } from '@/state/helpers';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import french from '@assets/images/flags/fr.svg';
import us_flag from '@assets/images/flags/us.svg';

const page = usePage();

const logout = () => {
  router.post(route('logout'));
};

const setLanguage = (locale) => {
  router.post(route('locale.update'), { locale }, {
    preserveScroll: true,
    onSuccess: () => {
      const flag = locale === 'fr' ? french : us_flag;
      document.getElementById('header-lang-img')?.setAttribute('src', flag);
    },
  });
};

const user = computed(() => page.props.auth?.user ?? null);
const roleLabel = computed(() => user.value?.role_label ?? '');
</script>

<script>
import { Link } from '@inertiajs/vue3';
import simplebar from 'simplebar-vue';
import french from '@assets/images/flags/fr.svg';
import us_flag from '@assets/images/flags/us.svg';

export default {
  data() {
    return {
      languages: [
        { flag: french, language: 'fr', title: 'Français', emoji: '🇫🇷' },
        { flag: us_flag, language: 'en', title: 'English', emoji: '🇬🇧' },
      ],
    };
  },
  components: {
    simplebar,
    Link,
  },
  computed: {
    currentLocale() {
      return this.$page.props.locale ?? 'fr';
    },
    currentLanguage() {
      return this.languages.find((entry) => entry.language === this.currentLocale) ?? this.languages[0];
    },
  },
  methods: {
    ...layoutMethods,
    initFullScreen() {
      document.body.classList.toggle('fullscreen-enable');
      if (
        !document.fullscreenElement &&
        !document.mozFullScreenElement &&
        !document.webkitFullscreenElement
      ) {
        if (document.documentElement.requestFullscreen) {
          document.documentElement.requestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
          document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
          document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
        }
      } else if (document.cancelFullScreen) {
        document.cancelFullScreen();
      } else if (document.mozCancelFullScreen) {
        document.mozCancelFullScreen();
      } else if (document.webkitCancelFullScreen) {
        document.webkitCancelFullScreen();
      }
    },
    toggleDarkMode() {
      const nextTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-bs-theme', nextTheme);
      this.changeMode({ mode: nextTheme });
    },
  },
  mounted() {
    const flag = this.currentLanguage.flag;
    document.getElementById('header-lang-img')?.setAttribute('src', flag);

    document.addEventListener('scroll', () => {
      const pageTopbar = document.getElementById('page-topbar');
      if (!pageTopbar) {
        return;
      }
      if (document.body.scrollTop >= 50 || document.documentElement.scrollTop >= 50) {
        pageTopbar.classList.add('topbar-shadow');
      } else {
        pageTopbar.classList.remove('topbar-shadow');
      }
    });
  },
};
</script>

<template>
  <header id="page-topbar">
    <div class="layout-width">
      <div class="navbar-header">
        <div class="d-flex">
          <div class="navbar-brand-box horizontal-logo">
            <Link href="/" class="logo logo-dark">
              <span class="logo-sm">
                <img src="@assets/images/logo-sm.png" alt="SpeedZone Express" class="brand-logo-icon" />
              </span>
              <span class="logo-lg">
                <img src="@assets/images/logo-dark.png" alt="SpeedZone Express" class="brand-logo-full" />
              </span>
            </Link>
            <Link href="/" class="logo logo-light">
              <span class="logo-sm">
                <img src="@assets/images/logo-sm.png" alt="SpeedZone Express" class="brand-logo-icon" />
              </span>
              <span class="logo-lg">
                <img src="@assets/images/logo-light.png" alt="SpeedZone Express" class="brand-logo-full" />
              </span>
            </Link>
          </div>

        </div>

        <GlobalSearch />

        <div class="d-flex align-items-center">
          <StoreSwitcher />

          <NavModeSwitcher />

          <BDropdown
            class="dropdown"
            variant="ghost-secondary"
            dropstart
            :offset="{ alignmentAxis: 55, crossAxis: 15, mainAxis: -50 }"
            toggle-class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle arrow-none"
            menu-class="dropdown-menu-end"
          >
            <template #button-content>
              <img
                id="header-lang-img"
                :src="currentLanguage.flag"
                :alt="$t('navbar.language')"
                height="20"
                class="rounded"
              />
            </template>
            <BLink
              v-for="entry in languages"
              :key="entry.language"
              href="javascript:void(0);"
              class="dropdown-item notify-item language py-2"
              :class="{ active: currentLocale === entry.language }"
              @click="setLanguage(entry.language)"
            >
              <img :src="entry.flag" :alt="entry.title" class="me-2 rounded" height="18" />
              <span class="align-middle">{{ entry.emoji }} {{ entry.title }}</span>
            </BLink>
          </BDropdown>

          <div class="ms-1 header-item d-none d-sm-flex">
            <BButton
              type="button"
              variant="ghost-secondary"
              class="btn-icon btn-topbar rounded-circle"
              data-toggle="fullscreen"
              @click="initFullScreen"
            >
              <i class="bx bx-fullscreen fs-22"></i>
            </BButton>
          </div>

          <div class="ms-1 header-item d-none d-sm-flex">
            <BButton
              type="button"
              variant="ghost-secondary"
              class="btn-icon btn-topbar rounded-circle light-dark-mode"
              @click="toggleDarkMode"
            >
              <i class="bx bx-moon fs-22"></i>
            </BButton>
          </div>

          <Link href="/chat" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle ms-1 header-item d-none d-sm-flex">
            <i class="bx bx-message-rounded-dots fs-22"></i>
          </Link>

          <SettingsMenu />

          <NotificationBell />

          <BDropdown
            variant="link"
            class="ms-sm-3 header-item topbar-user"
            toggle-class="rounded-circle arrow-none"
            menu-class="dropdown-menu-end"
            :offset="{ alignmentAxis: -14, crossAxis: 0, mainAxis: 0 }"
          >
            <template #button-content>
              <span class="d-flex align-items-center">
                <img
                  v-if="$page.props.jetstream?.managesProfilePhotos && user"
                  class="rounded-circle header-profile-user"
                  :src="user.profile_photo_url"
                  :alt="user.name"
                />
                <span class="text-start ms-xl-2">
                  <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ user?.name }}</span>
                  <span v-if="roleLabel" class="d-none d-xl-block ms-1 fs-12 user-name-sub-text">{{ roleLabel }}</span>
                </span>
              </span>
            </template>

            <h6 class="dropdown-header">{{ $t('navbar.welcome', { name: user?.name ?? '' }) }}</h6>

            <Link class="dropdown-item" :href="route('profile.show')">
              <i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i>
              <span class="align-middle">{{ $t('navbar.profile') }}</span>
            </Link>

            <Link class="dropdown-item" href="/chat">
              <i class="mdi mdi-message-text-outline text-muted fs-16 align-middle me-1"></i>
              <span class="align-middle">{{ $t('common.chat') }}</span>
            </Link>

            <Link class="dropdown-item" :href="route('guides.index')">
              <i class="mdi mdi-school-outline text-muted fs-16 align-middle me-1"></i>
              <span class="align-middle">{{ $t('sidebar.guides') }}</span>
            </Link>

            <div class="dropdown-divider"></div>

            <h6 class="dropdown-header">{{ $t('navbar.language') }}</h6>
            <BLink
              v-for="entry in languages"
              :key="`user-${entry.language}`"
              href="javascript:void(0);"
              class="dropdown-item"
              :class="{ active: currentLocale === entry.language }"
              @click="setLanguage(entry.language)"
            >
              <span class="align-middle">{{ entry.emoji }} {{ entry.title }}</span>
            </BLink>

            <div class="dropdown-divider"></div>

            <form method="POST" class="dropdown-item" @submit.prevent="logout">
              <BButton variant="none" type="submit" class="btn p-0">
                <i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i>
                {{ $t('navbar.logout') }}
              </BButton>
            </form>
          </BDropdown>
        </div>
      </div>
    </div>
  </header>
</template>
