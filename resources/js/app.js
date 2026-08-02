import './bootstrap';
import '../scss/config/corporate/app.scss';
import '@vueform/slider/themes/default.css';
import '../scss/mermaid.min.css';

import { createApp, defineAsyncComponent, h } from 'vue';
import { createInertiaApp, Link, Head } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
import BootstrapVueNext from 'bootstrap-vue-next';
import vClickOutside from "click-outside-vue3";
import VueFeather from 'vue-feather';
import VueTheMask from 'vue-the-mask';

/**
 * ApexCharts is ~1 MB of JavaScript (290 KB gzipped). Registering it eagerly put
 * it in the entry bundle, so every page paid for it even though only the
 * dashboards and chart pages render a chart. As an async component it is fetched
 * the first time an <apexchart> is actually rendered.
 */
const ApexChart = defineAsyncComponent(() =>
    import('vue3-apexcharts').then((module) => module.default)
);

import AOS from 'aos';
import 'aos/dist/aos.css';

import store from "./state/store";
import i18n, { syncLocaleFromPage } from './i18n'
import { router } from '@inertiajs/vue3';

AOS.init({
    easing: 'ease-out-back',
    duration: 1000
});

createInertiaApp({
    title: title => title ? `${title} | SpeedZone Express` : 'SpeedZone Express',
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        syncLocaleFromPage(props.initialPage?.props);

        router.on('success', (event) => {
            syncLocaleFromPage(event.detail.page.props);
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(store)
            .use(i18n)
            .use(ZiggyVue)
            .use(BootstrapVueNext)
            .component('Link', Link)
            .component('Head', Head)
            .component('apexchart', ApexChart)
            .use(VueTheMask)
            .use(vClickOutside)
            .component(VueFeather.type, VueFeather)
            .mount(el);
    },
    progress: {
        color: '#0D4A9D',
    },
});
