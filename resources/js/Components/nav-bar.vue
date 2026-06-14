<script setup>
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
    toggleHamburgerMenu() {
      const windowSize = document.documentElement.clientWidth;
      const layoutType = document.documentElement.getAttribute('data-layout');
      const visibilityType = document.documentElement.getAttribute('data-sidebar-visibility');

      document.documentElement.setAttribute('data-sidebar-visibility', 'show');

      if (windowSize > 767) {
        document.querySelector('.hamburger-icon')?.classList.toggle('open');
      }

      if (layoutType === 'horizontal') {
        document.body.classList.toggle('menu');
      }

      if (visibilityType === 'show' && (layoutType === 'vertical' || layoutType === 'semibox')) {
        if (windowSize < 1025 && windowSize > 767) {
          document.body.classList.remove('vertical-sidebar-enable');
          document.documentElement.getAttribute('data-sidebar-size') === 'sm'
            ? document.documentElement.setAttribute('data-sidebar-size', '')
            : document.documentElement.setAttribute('data-sidebar-size', 'sm');
        } else if (windowSize > 1025) {
          document.body.classList.remove('vertical-sidebar-enable');
          document.documentElement.getAttribute('data-sidebar-size') === 'lg'
            ? document.documentElement.setAttribute('data-sidebar-size', 'sm')
            : document.documentElement.setAttribute('data-sidebar-size', 'lg');
        } else if (windowSize <= 767) {
          document.body.classList.add('vertical-sidebar-enable');
          document.documentElement.setAttribute('data-sidebar-size', 'lg');
        }
      }

      if (layoutType === 'twocolumn') {
        document.body.classList.toggle('twocolumn-panel');
      }
    },
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

    document.getElementById('topnav-hamburger-icon')?.addEventListener('click', this.toggleHamburgerMenu);
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

          <button
            type="button"
            class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger"
            id="topnav-hamburger-icon"
          >
            <span class="hamburger-icon">
              <span></span>
              <span></span>
              <span></span>
            </span>
          </button>
        </div>

        <div class="d-flex align-items-center">
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

          <BDropdown
            variant="ghost-dark"
            dropstart
            class="ms-1 dropdown"
            :offset="{ alignmentAxis: 57, crossAxis: 0, mainAxis: -42 }"
            toggle-class="btn-icon btn-topbar rounded-circle arrow-none"
            id="page-header-notifications-dropdown"
            menu-class="dropdown-menu-lg dropdown-menu-end p-0"
            auto-close="outside"
          >
            <template #button-content>
              <i class="bx bx-bell fs-22"></i>
              <span class="position-absolute topbar-badge fs-10 translate-middle badge rounded-pill bg-danger">
                <span class="notification-badge">3</span>
                <span class="visually-hidden">{{ $t('common.unread_messages') }}</span>
              </span>
            </template>
            <div class="dropdown-head bg-primary bg-pattern rounded-top dropdown-menu-lg">
              <div class="p-3">
                <BRow class="align-items-center">
                  <BCol>
                    <h6 class="m-0 fs-16 fw-semibold text-white">{{ $t('common.notifications') }}</h6>
                  </BCol>
                  <BCol cols="auto" class="dropdown-tabs">
                    <BBadge variant="light-subtle" class="bg-light-subtle text-body fs-13">
                      {{ $t('common.new_count', { count: 4 }) }}
                    </BBadge>
                  </BCol>
                </BRow>
              </div>
            </div>
            <BTabs nav-class="dropdown-tabs nav-tab-custom bg-primary px-2 pt-2">
              <BTab :title="$t('common.all_notifications', { count: 4 })" class="tab-pane fade py-2 ps-2 show">
                <simplebar data-simplebar style="max-height: 300px" class="pe-2">
                  <div class="text-center py-4 text-muted">
                    <i class="bx bx-bell fs-1 d-block mb-2"></i>
                    <p class="mb-0">{{ $t('common.no_notifications') }}</p>
                  </div>
                  <div class="my-3 text-center">
                    <BButton type="button" variant="soft-success">
                      {{ $t('common.view_all_notifications') }}
                      <i class="ri-arrow-right-line align-middle"></i>
                    </BButton>
                  </div>
                </simplebar>
              </BTab>
              <BTab :title="$t('common.messages')" class="tab-pane fade py-2 ps-2">
                <simplebar data-simplebar style="max-height: 300px" class="pe-2">
                  <div class="text-center py-4 text-muted">
                    <i class="bx bx-message-rounded-dots fs-1 d-block mb-2"></i>
                    <p class="mb-0">{{ $t('common.no_notifications') }}</p>
                  </div>
                  <div class="my-3 text-center">
                    <Link href="/chat" class="btn btn-soft-success btn-sm">
                      {{ $t('common.view_all_messages') }}
                      <i class="ri-arrow-right-line align-middle"></i>
                    </Link>
                  </div>
                </simplebar>
              </BTab>
              <BTab :title="$t('common.alerts')" class="p-4">
                <simplebar data-simplebar style="max-height: 300px" class="pe-2">
                  <div class="w-25 w-sm-50 pt-3 mx-auto">
                    <img src="@assets/images/svg/bell.svg" class="img-fluid" alt="" />
                  </div>
                  <div class="text-center pb-5 mt-2">
                    <h6 class="fs-18 fw-semibold lh-base">{{ $t('common.no_notifications') }}</h6>
                  </div>
                </simplebar>
              </BTab>
            </BTabs>
          </BDropdown>

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
