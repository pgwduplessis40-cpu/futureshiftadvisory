<?php

declare(strict_types=1);

namespace Tests\Unit\Entrepreneurs\Forecast;

use App\Services\Entrepreneurs\Forecast\FundingSource;
use PHPUnit\Framework\TestCase;

final class FundingSourceTest extends TestCase
{
    public function test_an_arrangement_nobody_has_finished_establishing_is_a_valid_record(): void
    {
        $funding = new FundingSource('term-loan', 'Facility letter', null, null, null, null, null, null, null, null);

        self::assertNull($funding->commitment, 'An absent commitment is nobody having said, and must never read as an uncommitted one.');
        self::assertNull($funding->draw(0), 'A source with no amount and no date puts nothing in the ledger.');
    }

    public function test_funding_cannot_be_drawn_before_it_is_there_to_draw(): void
    {
        $this->expectExceptionMessage('Funding cannot be drawn before the date it becomes available.');

        new FundingSource('term-loan', 'Facility letter', 'Westpac', 'Term loan', '25000',
            'committed', '2026-03-01', '2026-02-15', 'unrestricted', true);
    }

    public function test_only_the_named_commitments_and_restrictions_are_recognised(): void
    {
        foreach ([['probably', 'unrestricted'], ['committed', 'mostly']] as [$commitment, $restriction]) {
            try {
                new FundingSource('term-loan', 'Facility letter', 'Westpac', 'Term loan', '25000',
                    $commitment, '2026-02-01', '2026-02-15', $restriction, true);
                self::fail('A word nobody can act on must not be stored as a commitment or a restriction.');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('recognised', $e->getMessage());
            }
        }
    }

    public function test_a_draw_enters_the_ledger_as_a_dated_financing_inflow(): void
    {
        $draw = (new FundingSource('term-loan', 'Facility letter', 'Westpac', 'Term loan', '25000',
            'committed', '2026-02-01', '2026-02-15', 'unrestricted', true))->draw(7);

        self::assertNotNull($draw);
        self::assertSame(['term-loan.draw', '2026-02-15', '25000', 'in', 'funding', 7, true],
            [$draw->id, $draw->date, $draw->amount, $draw->direction, $draw->category, $draw->sequence, $draw->verified]);
    }

    public function test_a_source_missing_either_half_of_its_draw_puts_nothing_in_the_ledger(): void
    {
        // A guessed figure or a guessed date would put cash in a month nobody
        // has said it arrives in, which is worse than reporting the gap.
        $undated = new FundingSource('term-loan', 'Facility letter', 'Westpac', null, '25000', 'committed', null, null, null, null);
        $unpriced = new FundingSource('grant', 'Grant letter', 'Callaghan', null, null, 'committed', null, '2026-05-01', null, null);

        self::assertNull($undated->draw(0));
        self::assertNull($unpriced->draw(0));
    }
}
