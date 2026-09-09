import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        origin: 'http://localhost:5173',
        hmr: { host: 'localhost' },
        watch: {
            // El bind mount de WSL2 no propaga eventos inotify al contenedor:
            // sin sondeo, el servidor de Vite no ve los cambios.
            usePolling: true,
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
