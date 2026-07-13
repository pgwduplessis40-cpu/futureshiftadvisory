<?php

declare(strict_types=1);

namespace Tests\Unit\Proposals;

use App\Models\Proposal;
use App\Services\Proposals\ProposalBrief;
use Tests\TestCase;

final class ProposalBriefTest extends TestCase
{
    public function test_it_summarises_an_integration_quote_pack(): void
    {
        $proposal = new Proposal([
            'scope' => [
                'integration_quote_pack' => [
                    'systems' => [
                        ['name' => 'Xero'],
                        ['vendor' => 'Field Service Board'],
                    ],
                    'connections' => [
                        ['from_system' => 'field-service', 'to_system' => 'xero'],
                        ['from_system' => 'crm', 'to_system' => 'field-service'],
                    ],
                    'delivery_mode' => 'inhouse',
                ],
            ],
        ]);

        self::assertSame(
            'Systems integration: Xero, Field Service Board. 2 scoped connections. In-house.',
            (new ProposalBrief)->for($proposal),
        );
    }

    public function test_it_derives_a_brief_when_a_legacy_scope_summary_is_missing(): void
    {
        $proposal = new Proposal([
            'scope' => [
                'proposal_variant' => 'governance_review',
                'focus_areas' => [
                    ['title' => 'Board decision rights'],
                    ['label' => 'Risk oversight'],
                ],
            ],
            'services' => [
                ['name' => 'Governance review'],
            ],
        ]);

        self::assertSame(
            'Governance review and board effectiveness engagement. Focus: Board decision rights, Risk oversight.',
            (new ProposalBrief)->for($proposal),
        );
    }
}
