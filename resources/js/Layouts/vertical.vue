<script>
import NavBar from '@/Components/nav-bar.vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import BottomNav from '@/Components/BottomNav.vue';
import Footer from '@/Components/footer.vue';
import StorePickerModal from '@/Components/StorePickerModal.vue';
import { layoutComputed } from '@/state/helpers';
import { applyLayoutAttributes } from '@/utils/applyLayoutAttributes';

localStorage.setItem('hoverd', false);

export default {
  components: { NavBar, AppSidebar, BottomNav, Footer, StorePickerModal },
  computed: {
    ...layoutComputed,
  },
  mounted() {
    applyLayoutAttributes({ ...this.$store.state.layout, layoutType: 'vertical' });

    if (localStorage.getItem('hoverd') === 'true') {
      document.documentElement.setAttribute('data-sidebar-size', 'sm-hover-active');
    }
  },
  created() {
    document.body.removeAttribute('data-layout');
    document.body.removeAttribute('data-topbar');
    document.body.removeAttribute('data-layout-size');
  },
};
</script>

<template>
  <div id="layout-wrapper">
    <NavBar />
    <AppSidebar />

    <div class="main-content">
      <div class="page-content">
        <b-container fluid>
          <slot />
        </b-container>
      </div>
      <Footer />
    </div>

    <!-- Mobile navigation. Renders below `lg` only, where the stylesheet also
         hides the off-canvas sidebar and its trigger. -->
    <BottomNav />

    <!-- Blocks the interface until a multi-store user picks the shop to work in. -->
    <StorePickerModal />
  </div>
</template>
