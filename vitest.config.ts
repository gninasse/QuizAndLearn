import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'happy-dom',
        include: ['resources/js/learner/**/*.test.ts'],
        setupFiles: ['resources/js/learner/test-setup.ts'],
    },
});
