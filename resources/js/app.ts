import '../css/app.css';
import { createApp, h } from 'vue';
import type { DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    title: (title) => title ? `${title} · Campus Digital` : 'Campus Digital',
    resolve: (name) => pages[`./Pages/${name}.vue`]() as Promise<DefineComponent>,
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#2B83C6',
        showSpinner: false,
    },
});
