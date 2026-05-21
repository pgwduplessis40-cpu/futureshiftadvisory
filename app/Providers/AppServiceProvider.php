<?php

namespace App\Providers;

use App\Models\Client;
use App\Notifications\Channels\FsaDatabaseChannel;
use App\Observers\ClientLifecycleObserver;
use App\Services\Integration\Resilience\RetryPolicy;
use App\Services\Integration\VirusScanner\ClamAvScanner;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\NoopScanner;
use App\Services\Pdf\BrowsershotRenderer;
use App\Services\Pdf\PdfRenderer;
use App\Services\Storage\KeyEnvelope;
use App\Services\Storage\WriteWrappedAdapter;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RetryPolicy::class, fn (): RetryPolicy => RetryPolicy::fromConfig());
        $this->app->singleton(FileScanner::class, fn (): FileScanner => (bool) config('virus-scanner.live', false)
            ? $this->app->make(ClamAvScanner::class)
            : $this->app->make(NoopScanner::class));
        $this->app->singleton(PdfRenderer::class, BrowsershotRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Client::observe(ClientLifecycleObserver::class);
        $this->registerSecureLocalDisk();
        Notification::extend('fsa_database', fn ($app): FsaDatabaseChannel => $app->make(FsaDatabaseChannel::class));
        $this->configureDefaults();
    }

    protected function registerSecureLocalDisk(): void
    {
        Storage::extend('encrypted-local', function ($app, array $config): LaravelFilesystemAdapter {
            $visibility = PortableVisibilityConverter::fromArray(
                $config['permissions'] ?? [],
                $config['directory_visibility'] ?? $config['visibility'] ?? Visibility::PRIVATE,
            );
            $links = ($config['links'] ?? null) === 'skip'
                ? LocalFilesystemAdapter::SKIP_LINKS
                : LocalFilesystemAdapter::DISALLOW_LINKS;
            $local = new LocalFilesystemAdapter(
                location: $config['root'],
                visibility: $visibility,
                writeFlags: $config['lock'] ?? LOCK_EX,
                linkHandling: $links,
            );
            $adapter = new WriteWrappedAdapter($local, $app->make(KeyEnvelope::class));
            $filesystem = new Flysystem($adapter, Arr::only($config, [
                'directory_visibility',
                'disable_asserts',
                'retain_visibility',
                'visibility',
            ]));

            return new LaravelFilesystemAdapter($filesystem, $adapter, $config);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
