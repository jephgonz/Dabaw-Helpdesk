import React from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';

const pages = import.meta.glob('./Pages/**/*.jsx');

createInertiaApp({
    title: (title) => `${title} · Dabaw Helpdesk`,
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.jsx`];
        if (!page) throw new Error(`Unknown Inertia page: ${name}`);
        return (await page()).default;
    },
    setup({ el, App, props }) {
        if (import.meta.env.SSR) {
            hydrateRoot(el, <App {...props} />);
            return;
        }
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#2563eb' },
});
