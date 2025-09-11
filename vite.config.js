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
        watch: {
            usePolling: true, // active le polling
            interval: 1000,   // (optionnel) réduit la fréquence pour moins de charge
            ignored: ["**/vendor/**", "**/node_modules/**"], // ignore fichiers inutiles
        },
    },
});
