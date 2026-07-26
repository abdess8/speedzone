import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

/**
 * Split the heaviest third-party libraries into their own chunks.
 *
 * Without this, everything imported from app.js lands in a single entry chunk
 * that has to be re-downloaded in full after every deploy. Splitting them keeps
 * the entry small and lets the browser reuse the vendor chunks, whose content
 * hashes only change when the dependency itself changes.
 */
function manualChunks(id) {
    // Vite's preload helper is used by the entry. If Rollup parks it inside one
    // of the lazy vendor chunks, the entry has to import that chunk eagerly and
    // the code splitting below is silently undone.
    if (id.includes('preload-helper') || id.includes('modulepreload-polyfill')) {
        return 'vendor';
    }

    if (!id.includes('node_modules')) {
        return undefined;
    }

    if (id.includes('apexcharts')) return 'vendor-apexcharts';
    if (id.includes('echarts') || id.includes('zrender')) return 'vendor-echarts';
    // The Vue wrapper and @kurkle/color belong with chart.js, otherwise they
    // stay in the shared chunk and the two chunks end up importing each other.
    if (id.includes('chart.js') || id.includes('vue3-chartjs') || id.includes('@kurkle')) {
        return 'vendor-chartjs';
    }
    if (id.includes('@amcharts')) return 'vendor-amcharts';
    if (id.includes('@fullcalendar')) return 'vendor-fullcalendar';
    if (id.includes('ckeditor')) return 'vendor-ckeditor';
    if (id.includes('leaflet')) return 'vendor-leaflet';
    if (id.includes('sweetalert2')) return 'vendor-sweetalert';
    if (id.includes('lottie-web')) return 'vendor-lottie';
    if (id.includes('swiper')) return 'vendor-swiper';
    if (id.includes('bootstrap')) return 'vendor-bootstrap';
    if (id.includes('pusher-js') || id.includes('laravel-echo')) return 'vendor-echo';
    if (id.includes('moment')) return 'vendor-moment';

    // Everything else (Vue runtime, Inertia, Vuex, i18n, axios, ...) stays in a
    // single chunk. Splitting the core runtime further produced chunks that
    // imported each other, which Rollup reports as circular.
    return 'vendor';
}

export default defineConfig({
    build: {
        // esbuild minification is the Vite default; stated explicitly so it is
        // obvious that production builds are minified and tree-shaken.
        minify: 'esbuild',
        cssCodeSplit: true,
        sourcemap: false,
        chunkSizeWarningLimit: 1500,
        rollupOptions: {
            output: {
                manualChunks,
            },
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@assets': '/resources/', // Update this with the correct path to your images
            '@favicon': '/resources/images/', // Update this with the correct path to your images
            '@': '/resources/js', // Matches jsconfig.json — only matches imports starting with "@/"
        },
    },
});
