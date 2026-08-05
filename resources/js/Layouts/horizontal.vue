<script>
import NavBar from '@/Components/nav-bar.vue';
import TopNav from '@/Components/TopNav.vue';
import BottomNav from '@/Components/BottomNav.vue';
import Footer from '@/Components/footer.vue';
import StorePickerModal from '@/Components/StorePickerModal.vue';
import { layoutComputed } from '@/state/helpers';
import { applyLayoutAttributes } from '@/utils/applyLayoutAttributes';

/**
 * Top navigation shell.
 *
 * `TopNav` replaces the sidebar rather than restyling it: a scrolling column
 * with a caption per group does not become a bar by changing its flex
 * direction. Both read the same permission-filtered tree.
 *
 * Below `md` the bar has nowhere to go, so `BottomNav` carries navigation
 * exactly as it does in the vertical layout.
 */
export default {
  components: { NavBar, TopNav, BottomNav, Footer, StorePickerModal },
  computed: {
    ...layoutComputed,
  },
  mounted() {
    applyLayoutAttributes({ ...this.$store.state.layout, layoutType: 'horizontal' });
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
    <TopNav />

    <div class="main-content">
      <div class="page-content">
        <BContainer fluid>
          <slot />
        </BContainer>
      </div>
      <Footer />
    </div>

    <BottomNav />

    <StorePickerModal />
  </div>
</template>
