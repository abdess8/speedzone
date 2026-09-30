<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const href = computed(() => page.props.domains?.marketing_url || '/');
const isRemote = computed(() => /^https?:\/\//i.test(String(href.value)));
</script>

<template>
    <component :is="isRemote ? 'a' : Link" :href="href" class="d-inline-block auth-logo">
        <img src="@assets/images/logo-light.png" alt="SpeedZone Express" height="88">
    </component>
</template>
