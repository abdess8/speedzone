<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
  user: { type: Object, default: null },
  name: { type: String, default: "" },
  photoUrl: { type: String, default: null },
  size: { type: [Number, String], default: 36 },
  href: { type: String, default: null },
  clickable: { type: Boolean, default: false },
  showName: { type: Boolean, default: false },
});

const displayName = computed(
  () => props.name || props.user?.name || props.user?.full_name || ""
);

const hasPhoto = computed(() => {
  if (props.photoUrl) return true;
  if (props.user?.has_profile_photo != null) return props.user.has_profile_photo;
  return !!(props.user?.profile_photo_path || props.user?.photo || props.user?.photo_url);
});

const src = computed(() => props.photoUrl || props.user?.photo_url || props.user?.profile_photo_url);

const linkHref = computed(() => {
  if (props.href) return props.href;
  if (props.clickable && props.user?.id) return route("users.show", props.user.id);
  return null;
});

const sizeStyle = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
}));
</script>

<template>
  <component
    :is="linkHref ? Link : 'span'"
    :href="linkHref"
    class="d-inline-flex align-items-center gap-2"
    :class="linkHref ? 'text-body text-decoration-none' : ''"
  >
    <img
      v-if="hasPhoto && src"
      :src="src"
      :alt="displayName"
      class="rounded-circle object-fit-cover flex-shrink-0"
      :style="sizeStyle"
    />
    <span
      v-else
      class="rounded-circle bg-light text-muted d-inline-flex align-items-center justify-content-center flex-shrink-0"
      :style="sizeStyle"
    >
      <i class="ri-user-3-line" :style="{ fontSize: `${Number(size) * 0.45}px` }"></i>
    </span>
    <span v-if="showName && displayName" class="fw-semibold">{{ displayName }}</span>
    <slot />
  </component>
</template>
