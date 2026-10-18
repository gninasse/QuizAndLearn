import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'happy-dom',
        // Aucun accès réseau depuis les tests : scripts tiers (API YouTube /
        // Vimeo) et iframes ne sont jamais chargés ; un script « chargé » est
        // signalé comme réussi pour que les tests pilotent les faux fournisseurs.
        environmentOptions: {
            happyDOM: {
                settings: {
                    disableJavaScriptFileLoading: true,
                    handleDisabledFileLoadingAsSuccess: true,
                    navigation: { disableChildFrameNavigation: true },
                },
            },
        },
        include: ['resources/js/learner/**/*.test.ts'],
        setupFiles: ['resources/js/learner/test-setup.ts'],
    },
});
