<?php

namespace App\Providers;

use App\Events\NpoEngagementWeightingChanged;
use App\Listeners\RecomputeNpoScoresForWeightingChange;
use App\Models\AdvisorApiClient;
use App\Models\Client;
use App\Notifications\Channels\FsaDatabaseChannel;
use App\Observers\ClientLifecycleObserver;
use App\Services\Integration\Resilience\RetryPolicy;
use App\Services\Integration\VirusScanner\ClamAvScanner;
use App\Services\Integration\VirusScanner\Contracts\FileScanner;
use App\Services\Integration\VirusScanner\NoopScanner;
use App\Services\Pdf\BrowsershotRenderer;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pptx\Contracts\PptxGenerator;
use App\Services\Pptx\OpenXmlPptxGenerator;
use App\Services\Storage\Hsm\HsmClient;
use App\Services\Storage\Hsm\HsmKeyManager;
use App\Services\Storage\Hsm\SoftwareHsmClient;
use App\Services\Storage\Hsm\UnsupportedHsmClient;
use App\Services\Storage\KeyEnvelope;
use App\Services\Storage\Pqc\PqcEnvelopeCipher;
use App\Services\Storage\WriteWrappedAdapter;
use App\Services\Voice\Contracts\WhisperClient;
use App\Services\Voice\FakeWhisperClient;
use App\Services\Voice\LiveWhisperClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->singleton(PptxGenerator::class, OpenXmlPptxGenerator::class);
        $this->app->singleton(HsmClient::class, function (): HsmClient {
            $driver = (string) config('hsm.driver', 'software');

            return match ($driver) {
                '', 'software' => new SoftwareHsmClient,
                default => new UnsupportedHsmClient($driver),
            };
        });
        $this->app->singleton(HsmKeyManager::class);
        $this->app->singleton(PqcEnvelopeCipher::class);
        $this->app->singleton(KeyEnvelope::class);
        $this->app->singleton(WhisperClient::class, fn (): WhisperClient => (bool) config('services.whisper.live', false)
            ? $this->app->make(LiveWhisperClient::class)
            : $this->app->make(FakeWhisperClient::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Client::observe(ClientLifecycleObserver::class);
        $this->registerSecureLocalDisk();
        Notification::extend('fsa_database', fn ($app): FsaDatabaseChannel => $app->make(FsaDatabaseChannel::class));
        Event::listen(NpoEngagementWeightingChanged::class, RecomputeNpoScoresForWeightingChange::class);
        $this->registerRateLimiters();
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

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('advisor-api', function (Request $request): Limit {
            $tokenHash = (string) ($request->attributes->get('advisor_api_token_hash')
                ?: hash('sha256', (string) $request->bearerToken()));

            // Resolve the API client for the per-client limit. Do NOT rely on the
            // auth middleware having set the request attribute first: Laravel's
            // middleware priority can run ThrottleRequests before the custom
            // `advisor.api` middleware, leaving the attribute unset and the limit
            // silently defaulting to 60. Resolve by token hash as a fallback so
            // the client's configured rate_limit_per_minute is always honoured.
            $client = $request->attributes->get('advisor_api_client');
            if (! is_object($client) && $request->bearerToken() !== null) {
                $client = AdvisorApiClient::query()->where('token_hash', $tokenHash)->first();
            }

            $limit = is_object($client) && isset($client->rate_limit_per_minute)
                ? (int) $client->rate_limit_per_minute
                : 60;

            return Limit::perMinute(max(1, $limit))->by($tokenHash ?: $request->ip());
        });

        RateLimiter::for('mobile-api', function (Request $request): Limit {
            $device = $request->attributes->get('mobile_device');
            $key = is_object($device) && isset($device->id)
                ? (string) $device->id
                : hash('sha256', (string) $request->bearerToken());

            return Limit::perMinute(120)->by($key ?: $request->ip());
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
