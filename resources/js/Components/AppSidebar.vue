<script>
import { Link } from '@inertiajs/vue3';
import simplebar from 'simplebar-vue';
import Menu from '@/Components/menu.vue';

export default {
  components: {
    simplebar,
    Menu,
    Link,
  },
  methods: {
    toggleSidebarHover() {
      if (document.documentElement.getAttribute('data-sidebar-size') === 'sm-hover') {
        localStorage.setItem('hoverd', true);
        document.documentElement.setAttribute('data-sidebar-size', 'sm-hover-active');
      } else if (document.documentElement.getAttribute('data-sidebar-size') === 'sm-hover-active') {
        localStorage.setItem('hoverd', false);
        document.documentElement.setAttribute('data-sidebar-size', 'sm-hover');
      } else {
        document.documentElement.setAttribute('data-sidebar-size', 'sm-hover');
      }
    },
  },
};
</script>

<template>
  <div class="app-menu navbar-menu">
    <div class="navbar-brand-box">
      <Link href="/" class="logo logo-dark">
        <span class="logo-sm">
          <img src="@assets/images/logo-sm.png" alt="OWL Delivery" class="brand-logo-icon" />
        </span>
        <span class="logo-lg">
          <img src="@assets/images/logo-dark.png" alt="OWL Delivery" class="brand-logo-full" />
        </span>
      </Link>
      <Link href="/" class="logo logo-light">
        <span class="logo-sm">
          <img src="@assets/images/logo-sm.png" alt="OWL Delivery" class="brand-logo-icon" />
        </span>
        <span class="logo-lg">
          <img src="@assets/images/logo-light.png" alt="OWL Delivery" class="brand-logo-full" />
        </span>
      </Link>
      <BButton
        size="sm"
        class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
        id="vertical-hover"
        @click="toggleSidebarHover"
      >
        <i class="ri-record-circle-line"></i>
      </BButton>
    </div>

    <simplebar id="scrollbar" class="h-100" ref="scrollbar">
      <Menu />
    </simplebar>
    <div class="sidebar-background"></div>
  </div>
</template>
