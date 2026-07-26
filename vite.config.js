import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/learner.css',
                'resources/js/learner/main.ts',
            ],
            refresh: true,
        }),
        tailwindcss(),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js/learner',
            filename: 'sw.ts',
            registerType: 'autoUpdate',
            injectRegister: false, // enregistrement manuel dans main.ts (route /sw.js)
            manifest: false, // manifest maintenu à la main dans public/manifest.json
            injectManifest: {
                // Le shell SPA est precaché pour le repli de navigation offline.
                additionalManifestEntries: [{ url: '/', revision: `${Date.now()}` }],
                globPatterns: ['**/*.{js,css,woff2}'],
                // Le SW est servi à /sw.js (route Laravel) mais les assets
                // vivent sous /build/ : les URLs du manifest doivent être absolues.
                modifyURLPrefix: { '': '/build/' },
            },
            devOptions: { enabled: false },
        }),
    ],
});
