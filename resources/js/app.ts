import '../css/app.css';
import '../css/rubric-bg.css';
import './bootstrap';
import 'flowbite';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { createPinia } from 'pinia';
import { ZiggyVue } from 'ziggy-js';

const syncCsrfToken = (token: unknown) => {
    if (typeof token !== 'string' || token === '') {
        return;
    }

    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
};

router.on('success', (event) => {
    syncCsrfToken((event.detail.page.props as { csrf_token?: string }).csrf_token);
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
