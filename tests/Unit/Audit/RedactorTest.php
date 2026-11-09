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

    public function test_does_not_mask_a_uuid_whose_random_hex_reads_as_a_phone_number(): void
    {
        // The identifier from the CI failure that found this. Inside it,
        // `063-4401472` satisfies the phone pattern: the `a` before it clears
        // the lookbehind, `63-440147` supplies the digit groups, and the `c`
        // after it clears the lookahead. Rewriting part of a UUID corrupts
        // the identifier the audit row was written to preserve.
        $uuid = '01a1be7f-a2b3-7101-a063-4401472c3aa6';

        $this->assertSame($uuid, $this->redactor->redact($uuid));
        $this->assertSame(['model_id' => $uuid], $this->redactor->redact(['model_id' => $uuid]));
    }

    public function test_no_uuid_is_rewritten_however_its_random_hex_falls(): void
    {
        // One in roughly a hundred UUIDs used to be rewritten, so a handful of
        // fixed examples would have passed while the defect was live. This
        // samples enough of the space to fail if any pattern reaches in again.
        $rewritten = [];
        for ($i = 0; $i < 3000; $i++) {
            $uuid = sprintf('%08x-%04x-4%03x-%04x-%012x', random_int(0, 0xFFFFFFFF), random_int(0, 0xFFFF),
                random_int(0, 0xFFF), random_int(0x8000, 0xBFFF), random_int(0, 0xFFFFFFFFFFFF));
            if ($this->redactor->redact($uuid) !== $uuid) {
                $rewritten[] = $uuid;
            }
        }

        $this->assertSame([], $rewritten, 'An audit identifier must survive redaction whatever its random hex spells.');
    }

    public function test_protecting_a_uuid_does_not_shelter_pii_beside_or_inside_it(): void
    {
        $uuid = '01a1be7f-a2b3-7101-a063-4401472c3aa6';

        // Beside it: the phone is still redacted and the identifier is intact.
        $beside = (string) $this->redactor->redact('plan '.$uuid.' for 021 555 1234');
        $this->assertStringContainsString($uuid, $beside);
        $this->assertStringNotContainsString('021 555 1234', $beside);

        // Inside it: an address that happens to carry a UUID in its local
        // part is one address, and it is redacted whole.
        $inside = (string) $this->redactor->redact('user+'.$uuid.'@example.com');
        $this->assertStringNotContainsString('example.com', $inside);
        $this->assertMatchesRegularExpression('/^\[email:[a-f0-9]{10}\]$/', $inside);
    }

    public function test_a_uuid_in_a_secret_position_is_still_redacted(): void
    {
        $uuid = '01a1be7f-a2b3-7101-a063-4401472c3aa6';

        $keyed = $this->redactor->redact(['api_token' => $uuid]);
        $this->assertIsArray($keyed);
        $this->assertMatchesRegularExpression('/^\[secret:[a-f0-9]{10}\]$/', (string) $keyed['api_token']);

        $query = (string) $this->redactor->redact('https://example.test/cb?token='.$uuid);
        $this->assertStringNotContainsString($uuid, $query, 'Identifier protection must not become a way to leak a credential.');
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

    public function test_masks_values_under_secret_like_keys(): void
    {
        $result = $this->redactor->redact([
            'api_key' => 'raw-api-key',
            'client_secret' => 'raw-client-secret',
            'nested' => [
                'Authorization' => 'Bearer raw-token-value',
            ],
        ]);

        $encoded = json_encode($result);

        $this->assertStringNotContainsString('raw-api-key', $encoded);
        $this->assertStringNotContainsString('raw-client-secret', $encoded);
        $this->assertStringNotContainsString('raw-token-value', $encoded);
        $this->assertMatchesRegularExpression('/\\[secret:[a-f0-9]{10}\\]/', $encoded);
    }

    public function test_masks_query_string_secrets_in_urls(): void
    {
        $result = $this->redactor->redact('https://example.test/data?subscription-key=raw-secret&resource=abc');

        $this->assertStringNotContainsString('raw-secret', $result);
        $this->assertStringContainsString('subscription-key=[secret:', $result);
        $this->assertStringContainsString('resource=abc', $result);
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
