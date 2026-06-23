<script>
import NavBar from '@/Components/nav-bar.vue';
import AppSidebar from '@/Components/AppSidebar.vue';
import Footer from '@/Components/footer.vue';
import { layoutComputed } from '@/state/helpers';
import { applyLayoutAttributes } from '@/utils/applyLayoutAttributes';

localStorage.setItem('hoverd', false);

export default {
  components: { NavBar, AppSidebar, Footer },
  computed: {
    ...layoutComputed,
  },
  mounted() {
    applyLayoutAttributes({ ...this.$store.state.layout, layoutType: 'vertical' });

    if (localStorage.getItem('hoverd') === 'true') {
      document.documentElement.setAttribute('data-sidebar-size', 'sm-hover-active');
    }

    document.getElementById('overlay')?.addEventListener('click', () => {
      document.body.classList.remove('vertical-sidebar-enable');
    });
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
    <div>
      <AppSidebar />
      <div class="vertical-overlay" id="overlay"></div>
    </div>

    <div class="main-content">
      <div class="page-content">
        <b-container fluid>
          <slot />
        </b-container>
      </div>
      <Footer />
    </div>
  </div>
</template>
