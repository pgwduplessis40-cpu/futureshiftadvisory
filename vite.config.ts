import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

// Wayfinder regenerates every route/action type on boot, which measured at ~78s
// of dev-server startup on this codebase. Both dev and asset builds reuse the
// committed output, so builds are reproducible. After changing routes or
// controller signatures, refresh them explicitly with:
//   php artisan wayfinder:generate --with-form
// or `npm run wayfinder:generate`. Set WAYFINDER_GENERATE=true only when
// intentionally checking generator output locally.
const wayfinderOverride = process.env.WAYFINDER_GENERATE;
const clientReleaseSha =
    process.env.GITHUB_SHA ?? process.env.VITE_RELEASE_SHA ?? 'local';

export default defineConfig(({ command, isSsrBuild }) => ({
    define: {
        __CLIENT_RELEASE_SHA__: JSON.stringify(clientReleaseSha),
    },
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
        // SSR is production-only here (config/inertia.php: INERTIA_SSR_ENABLED
        // defaults to APP_ENV === 'production'). Outside `vite build --ssr` the
        // plugin would still resolve resources/js/app.tsx as its SSR entry and
        // warm that whole module graph on dev-server boot — minutes of work the
        // dev server never uses, which times out the transport at 60s.
        inertia(isSsrBuild ? {} : { ssr: false }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        ...((
            wayfinderOverride !== undefined
                ? wayfinderOverride !== 'false'
                : command === 'build'
        )
            ? [
                  wayfinder({
                      formVariants: true,
                  }),
              ]
            : []),
    ],
    // Pages are resolved lazily by glob, so Vite's boot-time scan only sees the
    // eager graph (app.tsx + layouts). Packages imported solely by a lazily
    // loaded page get discovered on first navigation, which re-runs the
    // optimizer, swaps the hashed files under node_modules/.vite/deps, forces a
    // full reload, and makes in-flight requests fail with "Pre-transform error:
    // The file does not exist at .../deps/dist-*.js". Declaring them up front
    // means one optimize pass at boot and no mid-session re-bundles.
    optimizeDeps: {
        include: [
            'react',
            'react-dom',
            '@inertiajs/react',
            'lucide-react',
            'clsx',
            'tailwind-merge',
            'class-variance-authority',
            'sonner',
            // Discovered late in practice — only reachable from lazy pages.
            '@dnd-kit/core',
            '@stripe/stripe-js',
            'input-otp',
            'laravel-echo',
            'pusher-js',
            '@radix-ui/react-avatar',
            '@radix-ui/react-checkbox',
            '@radix-ui/react-collapsible',
            '@radix-ui/react-dialog',
            '@radix-ui/react-dropdown-menu',
            '@radix-ui/react-hover-card',
            '@radix-ui/react-label',
            '@radix-ui/react-navigation-menu',
            '@radix-ui/react-popover',
            '@radix-ui/react-select',
            '@radix-ui/react-separator',
            '@radix-ui/react-slot',
            '@radix-ui/react-toggle',
            '@radix-ui/react-toggle-group',
            '@radix-ui/react-tooltip',
        ],
    },
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
}));
