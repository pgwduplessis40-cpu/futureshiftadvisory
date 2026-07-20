import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }

                    const normalizedId = id.replace(/\\/g, '/');

                    if (
                        normalizedId.includes('/react/') ||
                        normalizedId.includes('/react-dom/') ||
                        normalizedId.includes('/scheduler/')
                    ) {
                        return 'vendor-react';
                    }

                    if (normalizedId.includes('/@inertiajs/')) {
                        return 'vendor-inertia';
                    }

                    if (
                        normalizedId.includes('/@radix-ui/') ||
                        normalizedId.includes('/lucide-react/') ||
                        normalizedId.includes('/sonner/') ||
                        normalizedId.includes('/class-variance-authority/') ||
                        normalizedId.includes('/clsx/') ||
                        normalizedId.includes('/tailwind-merge/')
                    ) {
                        return 'vendor-ui';
                    }

                    return 'vendor';
                },
            },
        },
    },
});
