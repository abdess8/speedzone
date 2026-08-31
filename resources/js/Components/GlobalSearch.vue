<script setup>
import GlobalSearchBar from '@/Components/Search/GlobalSearchBar.vue';
import GlobalSearchOverlay from '@/Components/Search/GlobalSearchOverlay.vue';
import { useMediaQuery } from '@/composables/useMediaQuery';

/**
 * Which shape of global search the topbar gets.
 *
 * The cut is at `lg` rather than the usual `md`: a tablet in portrait already
 * carries the store switcher, the notifications and the account menu across the
 * same row, and a field wide enough to read a customer address in leaves none of
 * them room. Below that width the search is a trigger and a full-screen view.
 *
 * Only one of the two mounts. A CSS-only split would keep both alive, which for
 * search means two components answering the same keystrokes and two copies of
 * the recent history drifting apart.
 */
const isCompact = useMediaQuery('(max-width: 991.98px)');
</script>

<template>
  <div class="global-search-mount">
    <GlobalSearchOverlay v-if="isCompact" />
    <GlobalSearchBar v-else />
  </div>
</template>
