import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
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
    server: {
        host: '0.0.0.0', // listen on all interfaces
        port: 5173, // match docker-compose port
        strictPort: true, // fail fast if port in use
        hmr: {
            host: 'localhost', // use localhost for browser connection
        },
        watch: {
            usePolling: true, // active le polling
            interval: 1000,   // (optionnel) réduit la fréquence pour moins de charge
            ignored: ["**/vendor/**", "**/node_modules/**"], // ignore fichiers inutiles
        },
    },
});
