<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        @php($googleAnalyticsMeasurementId = config('services.google_analytics.measurement_id'))
        @if (is_string($googleAnalyticsMeasurementId) && $googleAnalyticsMeasurementId !== '')
            <!-- Google tag (gtag.js) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($googleAnalyticsMeasurementId) }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', @json($googleAnalyticsMeasurementId));
            </script>
        @endif

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect an explicit system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            #app-launch-skeleton {
                min-height: 100vh;
                background: oklch(1 0 0);
                color: oklch(0.145 0 0);
            }

            html.dark #app-launch-skeleton {
                background: oklch(0.145 0 0);
                color: oklch(0.985 0 0);
            }

            .app-launch-shell {
                width: min(1120px, calc(100% - 32px));
                margin: 0 auto;
                padding: 32px 0;
            }

            .app-launch-topbar,
            .app-launch-card,
            .app-launch-panel {
                border: 1px solid color-mix(in oklch, currentColor 12%, transparent);
                background: color-mix(in oklch, currentColor 4%, transparent);
                border-radius: 8px;
            }

            .app-launch-topbar {
                height: 56px;
                margin-bottom: 24px;
            }

            .app-launch-grid {
                display: grid;
                gap: 16px;
                grid-template-columns: repeat(6, minmax(0, 1fr));
                margin-bottom: 24px;
            }

            .app-launch-card {
                height: 76px;
            }

            .app-launch-layout {
                display: grid;
                gap: 16px;
                grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.8fr);
            }

            .app-launch-panel {
                height: 280px;
            }

            .app-launch-panel:first-child {
                height: 360px;
            }

            .app-launch-shimmer {
                position: relative;
                overflow: hidden;
            }

            .app-launch-shimmer::after {
                position: absolute;
                inset: 0;
                content: "";
                transform: translateX(-100%);
                background: linear-gradient(
                    90deg,
                    transparent,
                    color-mix(in oklch, currentColor 8%, transparent),
                    transparent
                );
                animation: app-launch-shimmer 1.4s infinite;
            }

            @keyframes app-launch-shimmer {
                100% {
                    transform: translateX(100%);
                }
            }

            @media (max-width: 900px) {
                .app-launch-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .app-launch-layout {
                    grid-template-columns: 1fr;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .app-launch-shimmer::after {
                    animation: none;
                }
            }
        </style>

        <link rel="icon" href="/favicon.svg?v=fsa-20260703" type="image/svg+xml">
        <link rel="alternate icon" href="/favicon.ico?v=fsa-20260703" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=fsa-20260703">
        <link rel="manifest" href="/manifest.webmanifest?v=fsa-20260703">
        <meta name="theme-color" content="#2f6f68">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Future Shift Advisory') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <div id="app-launch-skeleton" aria-hidden="true">
            <div class="app-launch-shell">
                <div class="app-launch-topbar app-launch-shimmer"></div>
                <div class="app-launch-grid">
                    <div class="app-launch-card app-launch-shimmer"></div>
                    <div class="app-launch-card app-launch-shimmer"></div>
                    <div class="app-launch-card app-launch-shimmer"></div>
                    <div class="app-launch-card app-launch-shimmer"></div>
                    <div class="app-launch-card app-launch-shimmer"></div>
                    <div class="app-launch-card app-launch-shimmer"></div>
                </div>
                <div class="app-launch-layout">
                    <div class="app-launch-panel app-launch-shimmer"></div>
                    <div class="app-launch-panel app-launch-shimmer"></div>
                </div>
            </div>
        </div>
        <x-inertia::app />
    </body>
</html>
