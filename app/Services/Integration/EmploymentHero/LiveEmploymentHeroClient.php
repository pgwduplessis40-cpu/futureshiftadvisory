<?php

declare(strict_types=1);

namespace App\Services\Integration\EmploymentHero;

use App\Models\NzToolConnection;
use App\Services\Integration\BusinessTools\NzBusinessToolLiveClient;
use App\Services\Integration\EmploymentHero\Contracts\EmploymentHeroClient;
use App\Services\Integration\Resilience\ResilientHttp;

final class LiveEmploymentHeroClient extends NzBusinessToolLiveClient implements EmploymentHeroClient
{
    public function __construct(ResilientHttp $http, FakeEmploymentHeroClient $fake)
    {
        parent::__construct($http, $fake);
    }

    protected function provider(): string
    {
        return NzToolConnection::PROVIDER_EMPLOYMENT_HERO;
    }
}
