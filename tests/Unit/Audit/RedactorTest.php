<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use App\Services\Audit\Redactor;
use Tests\TestCase;

final class RedactorTest extends TestCase
{
    private Redactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redactor = new Redactor;
    }

    public function test_masks_email_addresses(): void
    {
        $result = $this->redactor->redact('Contact: pgw@example.co.nz');

        $this->assertStringNotContainsString('pgw@example.co.nz', $result);
        $this->assertMatchesRegularExpression('/\[email:[a-f0-9]{10}\]/', $result);
    }

    public function test_masks_nz_phone_numbers_with_and_without_country_code(): void
    {
        $samples = [
            '+64 21 555 1234',
            '021 555 1234',
            '021-555-1234',
            '+6421 555 1234',
        ];

        foreach ($samples as $sample) {
            $result = $this->redactor->redact("Phone: {$sample}");
            $this->assertStringNotContainsString($sample, $result, "Expected to mask: {$sample}");
            $this->assertMatchesRegularExpression('/\[phone:[a-f0-9]{10}\]/', $result);
        }
    }

    public function test_masks_nz_bank_account_numbers(): void
    {
        $result = $this->redactor->redact('Account: 02-1234-5678901-00');

        $this->assertStringNotContainsString('02-1234-5678901-00', $result);
        $this->assertMatchesRegularExpression('/\[bank:[a-f0-9]{10}\]/', $result);
    }

    public function test_masks_ird_numbers(): void
    {
        $result = $this->redactor->redact('IRD: 12-345-678');

        $this->assertStringNotContainsString('12-345-678', $result);
        $this->assertMatchesRegularExpression('/\[ird:[a-f0-9]{10}\]/', $result);
    }

    public function test_does_not_mask_nzbn(): void
    {
        // NZBN is a public identifier; redacting it would harm audit utility.
        $nzbn = '9429000000000';
        $result = $this->redactor->redact("NZBN: {$nzbn}");

        $this->assertStringContainsString($nzbn, $result);
    }

    public function test_walks_nested_arrays(): void
    {
        $input = [
            'user' => [
                'email' => 'a@b.co.nz',
                'phone' => '021 555 1234',
                'name' => 'Pieter',
            ],
            'note' => 'Sent to a@b.co.nz on 2026-05-18',
        ];

        $result = $this->redactor->redact($input);

        $this->assertStringNotContainsString('a@b.co.nz', json_encode($result));
        $this->assertStringNotContainsString('021 555 1234', json_encode($result));
        $this->assertSame('Pieter', $result['user']['name']);
    }

    public function test_redactions_for_same_value_are_deterministic(): void
    {
        $a = $this->redactor->redact('a@b.co.nz');
        $b = $this->redactor->redact('a@b.co.nz');
        $c = $this->redactor->redact('different@b.co.nz');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }

    public function test_scalars_and_nulls_pass_through_untouched(): void
    {
        $this->assertSame(42, $this->redactor->redact(42));
        $this->assertSame(3.14, $this->redactor->redact(3.14));
        $this->assertTrue($this->redactor->redact(true));
        $this->assertNull($this->redactor->redact(null));
    }
}
