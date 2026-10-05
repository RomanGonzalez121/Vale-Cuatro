import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            // docs/ no tiene nada que recargar, y en Windows copiar ahí una captura tiraba abajo a Vite.
            ignored: ['**/storage/framework/views/**', '**/docs/**'],
        },
    },
});
