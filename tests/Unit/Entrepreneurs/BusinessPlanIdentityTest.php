<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs;

use App\Models\Client;
use App\Models\EntrepreneurProfile;
use App\Services\Entrepreneurs\BusinessPlanIdentity;
use Tests\TestCase;

final class BusinessPlanIdentityTest extends TestCase
{
    public function test_uses_the_saved_proposed_company_name_before_linked_client_names(): void
    {
        $profile = new EntrepreneurProfile([
            'name' => 'Tania Hassounia',
            'company_name' => 'Harbour Studio Limited',
        ]);
        $profile->setRelation('client', new Client([
            'trading_name' => 'Drawer Full of Giants',
        ]));

        $this->assertSame(
            'Harbour Studio Limited',
            app(BusinessPlanIdentity::class)->businessName($profile),
        );
    }

    public function test_uses_legal_business_name_when_trading_name_is_the_founder_name(): void
    {
        $profile = new EntrepreneurProfile(['name' => 'Tania Hassounia']);
        $profile->setRelation('client', new Client([
            'trading_name' => 'Tania Hassounia',
            'legal_name' => 'Drawer Full of Giants Limited',
        ]));

        $this->assertSame(
            'Drawer Full of Giants Limited',
            app(BusinessPlanIdentity::class)->businessName($profile),
        );
    }
}
