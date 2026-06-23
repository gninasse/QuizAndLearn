import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css', 
                'resources/js/app.js',
                'resources/css/learner.css',
                'resources/js/learner.js'
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
