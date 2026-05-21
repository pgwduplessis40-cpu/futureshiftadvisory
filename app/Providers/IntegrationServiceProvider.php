<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use App\Services\Integration\CompaniesOffice\FakeCompaniesOfficeClient;
use App\Services\Integration\CompaniesOffice\FallbackCompaniesOfficeClient;
use App\Services\Integration\CompaniesOffice\LiveCompaniesOfficeClient;
use App\Services\Integration\Fsp\Contracts\FspClient;
use App\Services\Integration\Fsp\FakeFspClient;
use App\Services\Integration\GoogleCalendar\Contracts\GoogleCalendarClient;
use App\Services\Integration\GoogleCalendar\FakeGoogleCalendarClient;
use App\Services\Integration\Iponz\Contracts\IponzClient;
use App\Services\Integration\Iponz\FakeIponzClient;
use App\Services\Integration\Ird\Contracts\IrdClient;
use App\Services\Integration\Ird\FakeIrdClient;
use App\Services\Integration\Ird\FallbackIrdClient;
use App\Services\Integration\Ird\LiveIrdClient;
use App\Services\Integration\Linz\Contracts\LinzClient;
use App\Services\Integration\Linz\FakeLinzClient;
use App\Services\Integration\Mbie\Contracts\MbieClient;
use App\Services\Integration\Mbie\FakeMbieClient;
use App\Services\Integration\MicrosoftGraph\Contracts\MicrosoftGraphClient;
use App\Services\Integration\MicrosoftGraph\FakeMicrosoftGraphClient;
use App\Services\Integration\Myob\Contracts\MyobClient;
use App\Services\Integration\Myob\FakeMyobClient;
use App\Services\Integration\Nzbn\Contracts\NzbnClient;
use App\Services\Integration\Nzbn\FakeNzbnClient;
use App\Services\Integration\Nzbn\FallbackNzbnClient;
use App\Services\Integration\Nzbn\LiveNzbnClient;
use App\Services\Integration\NzParliament\Contracts\NzParliamentClient;
use App\Services\Integration\NzParliament\FakeNzParliamentClient;
use App\Services\Integration\Ppsr\Contracts\PpsrClient;
use App\Services\Integration\Ppsr\FakePpsrClient;
use App\Services\Integration\QuickBooks\Contracts\QuickBooksClient;
use App\Services\Integration\QuickBooks\FakeQuickBooksClient;
use App\Services\Integration\Rbnz\Contracts\RbnzClient;
use App\Services\Integration\Rbnz\FakeRbnzClient;
use App\Services\Integration\SesSendGrid\Contracts\SesSendGridClient;
use App\Services\Integration\SesSendGrid\FakeSesSendGridClient;
use App\Services\Integration\StatsNz\Contracts\StatsNzClient;
use App\Services\Integration\StatsNz\FakeStatsNzClient;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Integration\Stripe\FakeStripeClient;
use App\Services\Integration\Whisper\Contracts\WhisperClient;
use App\Services\Integration\Whisper\FakeWhisperClient;
use App\Services\Integration\Windcave\Contracts\WindcaveClient;
use App\Services\Integration\Windcave\FakeWindcaveClient;
use App\Services\Integration\WorkSafe\Contracts\WorkSafeClient;
use App\Services\Integration\WorkSafe\FakeWorkSafeClient;
use App\Services\Integration\Xero\Contracts\XeroClient;
use App\Services\Integration\Xero\FakeXeroClient;
use Illuminate\Support\ServiceProvider;

final class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private array $scaffolds = [
        FspClient::class => FakeFspClient::class,
        PpsrClient::class => FakePpsrClient::class,
        LinzClient::class => FakeLinzClient::class,
        IponzClient::class => FakeIponzClient::class,
        StatsNzClient::class => FakeStatsNzClient::class,
        RbnzClient::class => FakeRbnzClient::class,
        MbieClient::class => FakeMbieClient::class,
        NzParliamentClient::class => FakeNzParliamentClient::class,
        WorkSafeClient::class => FakeWorkSafeClient::class,
        StripeClient::class => FakeStripeClient::class,
        WindcaveClient::class => FakeWindcaveClient::class,
        XeroClient::class => FakeXeroClient::class,
        MyobClient::class => FakeMyobClient::class,
        QuickBooksClient::class => FakeQuickBooksClient::class,
        SesSendGridClient::class => FakeSesSendGridClient::class,
        WhisperClient::class => FakeWhisperClient::class,
        GoogleCalendarClient::class => FakeGoogleCalendarClient::class,
        MicrosoftGraphClient::class => FakeMicrosoftGraphClient::class,
    ];

    public function register(): void
    {
        $this->registerActiveClients();
        $this->registerScaffolds();
    }

    private function registerActiveClients(): void
    {
        $this->app->singleton(FakeNzbnClient::class);
        $this->app->singleton(LiveNzbnClient::class);
        $this->app->singleton(FallbackNzbnClient::class);
        $this->app->singleton(NzbnClient::class, FallbackNzbnClient::class);

        $this->app->singleton(FakeCompaniesOfficeClient::class);
        $this->app->singleton(LiveCompaniesOfficeClient::class);
        $this->app->singleton(FallbackCompaniesOfficeClient::class);
        $this->app->singleton(CompaniesOfficeClient::class, FallbackCompaniesOfficeClient::class);

        $this->app->singleton(FakeIrdClient::class);
        $this->app->singleton(LiveIrdClient::class);
        $this->app->singleton(FallbackIrdClient::class);
        $this->app->singleton(IrdClient::class, FallbackIrdClient::class);
    }

    private function registerScaffolds(): void
    {
        foreach ($this->scaffolds as $interface => $implementation) {
            $this->app->singleton($interface, $implementation);
        }
    }
}
