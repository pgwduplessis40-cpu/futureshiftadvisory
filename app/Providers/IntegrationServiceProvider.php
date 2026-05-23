<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Integration\CompaniesOffice\Contracts\CompaniesOfficeClient;
use App\Services\Integration\CompaniesOffice\FakeCompaniesOfficeClient;
use App\Services\Integration\CompaniesOffice\FallbackCompaniesOfficeClient;
use App\Services\Integration\CompaniesOffice\LiveCompaniesOfficeClient;
use App\Services\Integration\Fsp\Contracts\FspClient;
use App\Services\Integration\Fsp\FakeFspClient;
use App\Services\Integration\Fsp\FallbackFspClient;
use App\Services\Integration\Fsp\LiveFspClient;
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
use App\Services\Integration\Mbie\FallbackMbieClient;
use App\Services\Integration\Mbie\LiveMbieClient;
use App\Services\Integration\MicrosoftGraph\Contracts\MicrosoftGraphClient;
use App\Services\Integration\MicrosoftGraph\FakeMicrosoftGraphClient;
use App\Services\Integration\Myob\Contracts\MyobClient;
use App\Services\Integration\Myob\FakeMyobClient;
use App\Services\Integration\Myob\FallbackMyobClient;
use App\Services\Integration\Myob\LiveMyobClient;
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
use App\Services\Integration\QuickBooks\FallbackQuickBooksClient;
use App\Services\Integration\QuickBooks\LiveQuickBooksClient;
use App\Services\Integration\Rbnz\Contracts\RbnzClient;
use App\Services\Integration\Rbnz\FakeRbnzClient;
use App\Services\Integration\Rbnz\FallbackRbnzClient;
use App\Services\Integration\Rbnz\LiveRbnzClient;
use App\Services\Integration\SesSendGrid\Contracts\SesSendGridClient;
use App\Services\Integration\SesSendGrid\FakeSesSendGridClient;
use App\Services\Integration\StatsNz\Contracts\StatsNzClient;
use App\Services\Integration\StatsNz\FakeStatsNzClient;
use App\Services\Integration\StatsNz\FallbackStatsNzClient;
use App\Services\Integration\StatsNz\LiveStatsNzClient;
use App\Services\Integration\Stripe\Contracts\StripeClient;
use App\Services\Integration\Stripe\FakeStripeClient;
use App\Services\Integration\Stripe\FallbackStripeClient;
use App\Services\Integration\Stripe\LiveStripeClient;
use App\Services\Integration\Whisper\Contracts\WhisperClient;
use App\Services\Integration\Whisper\FakeWhisperClient;
use App\Services\Integration\Windcave\Contracts\WindcaveClient;
use App\Services\Integration\Windcave\FakeWindcaveClient;
use App\Services\Integration\Windcave\FallbackWindcaveClient;
use App\Services\Integration\Windcave\LiveWindcaveClient;
use App\Services\Integration\WorkSafe\Contracts\WorkSafeClient;
use App\Services\Integration\WorkSafe\FakeWorkSafeClient;
use App\Services\Integration\Xero\Contracts\XeroClient;
use App\Services\Integration\Xero\FakeXeroClient;
use App\Services\Integration\Xero\FallbackXeroClient;
use App\Services\Integration\Xero\LiveXeroClient;
use Illuminate\Support\ServiceProvider;

final class IntegrationServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private array $scaffolds = [
        PpsrClient::class => FakePpsrClient::class,
        LinzClient::class => FakeLinzClient::class,
        IponzClient::class => FakeIponzClient::class,
        NzParliamentClient::class => FakeNzParliamentClient::class,
        WorkSafeClient::class => FakeWorkSafeClient::class,
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

        $this->app->singleton(FakeRbnzClient::class);
        $this->app->singleton(LiveRbnzClient::class);
        $this->app->singleton(FallbackRbnzClient::class);
        $this->app->singleton(RbnzClient::class, FallbackRbnzClient::class);

        $this->app->singleton(FakeStatsNzClient::class);
        $this->app->singleton(LiveStatsNzClient::class);
        $this->app->singleton(FallbackStatsNzClient::class);
        $this->app->singleton(StatsNzClient::class, FallbackStatsNzClient::class);

        $this->app->singleton(FakeMbieClient::class);
        $this->app->singleton(LiveMbieClient::class);
        $this->app->singleton(FallbackMbieClient::class);
        $this->app->singleton(MbieClient::class, FallbackMbieClient::class);

        $this->app->singleton(FakeFspClient::class);
        $this->app->singleton(LiveFspClient::class);
        $this->app->singleton(FallbackFspClient::class);
        $this->app->singleton(FspClient::class, FallbackFspClient::class);

        $this->app->singleton(FakeXeroClient::class);
        $this->app->singleton(LiveXeroClient::class);
        $this->app->singleton(FallbackXeroClient::class);
        $this->app->singleton(XeroClient::class, FallbackXeroClient::class);

        $this->app->singleton(FakeMyobClient::class);
        $this->app->singleton(LiveMyobClient::class);
        $this->app->singleton(FallbackMyobClient::class);
        $this->app->singleton(MyobClient::class, FallbackMyobClient::class);

        $this->app->singleton(FakeQuickBooksClient::class);
        $this->app->singleton(LiveQuickBooksClient::class);
        $this->app->singleton(FallbackQuickBooksClient::class);
        $this->app->singleton(QuickBooksClient::class, FallbackQuickBooksClient::class);

        $this->app->singleton(FakeStripeClient::class);
        $this->app->singleton(LiveStripeClient::class);
        $this->app->singleton(FallbackStripeClient::class);
        $this->app->singleton(StripeClient::class, FallbackStripeClient::class);

        $this->app->singleton(FakeWindcaveClient::class);
        $this->app->singleton(LiveWindcaveClient::class);
        $this->app->singleton(FallbackWindcaveClient::class);
        $this->app->singleton(WindcaveClient::class, FallbackWindcaveClient::class);
    }

    private function registerScaffolds(): void
    {
        foreach ($this->scaffolds as $interface => $implementation) {
            $this->app->singleton($interface, $implementation);
        }
    }
}
