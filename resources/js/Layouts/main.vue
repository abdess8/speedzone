<script>
import { layoutComputed } from "@/state/helpers";

import Vertical from "./vertical.vue";
import Horizontal from "./horizontal.vue";
import TwoColumns from "./twocolumn.vue";
import AlertBanner from "@/Components/AlertBanner.vue";
import AlertModal from "@/Components/AlertModal.vue";
import ChatbotWidget from "@/Components/Chatbot/ChatbotWidget.vue";
import GuideHost from "@/Components/Guide/GuideHost.vue";

export default {
    components: {
        Vertical,
        Horizontal,
        TwoColumns,
        AlertBanner,
        AlertModal,
        ChatbotWidget,
        GuideHost
    },
    data() {
        return {};
    },
    computed: {
        ...layoutComputed,
    },
    mounted() {
        // document.querySelector("html").setAttribute('dir', 'rtl');
    }
};
</script>

<template>
    <div>
        <!-- Announcements ride inside the content area of whichever layout is
             active, so they appear above the page on every screen. -->
        <Vertical v-if="layoutType === 'vertical' || layoutType === 'semibox'" :layout="layoutType">
            <AlertBanner />
            <slot />
        </Vertical>

        <Horizontal v-if="layoutType === 'horizontal'" :layout="layoutType">
            <AlertBanner />
            <slot />
        </Horizontal>

        <TwoColumns v-if="layoutType === 'twocolumn'" :layout="layoutType">
            <AlertBanner />
            <slot />
        </TwoColumns>

        <AlertModal />

        <!-- Sibling of every layout variant, like AlertModal: the assistant has
             to be reachable from any screen, in any layout mode. -->
        <ChatbotWidget />

        <!-- Same reason, plus one of its own: a guide outlives the page it
             started on, so its overlay cannot live inside one. -->
        <GuideHost />
    </div>
</template>
