// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4
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
});
