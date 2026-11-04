import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const { ASSET_URL = '' } = loadEnv(mode, process.cwd(), '');

    return {
        base: `${ASSET_URL.replace(/\/+$/, '')}/build/`,
        plugins: [
            laravel({
                input: ['resources/js/app.jsx'],
                refresh: true,
            }),
            react(),
            tailwindcss(),
        ],
        server: {
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
