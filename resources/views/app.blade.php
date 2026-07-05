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
        <x-inertia::app />
    </body>
</html>
