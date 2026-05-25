<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use App\Services\Storage\Exceptions\InvalidEnvelopeException;
use App\Services\Storage\Exceptions\UnsupportedEnvelopeVersionException;
use App\Services\Storage\KeyEnvelope;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class KeyEnvelopeTest extends TestCase
{
    public function test_round_trips_a_plaintext_string(): void
    {
        $envelope = app(KeyEnvelope::class);

        $ciphertext = $envelope->encrypt('hello world');

        $this->assertNotSame('hello world', $ciphertext);
        $this->assertSame('hello world', $envelope->decrypt($ciphertext));
    }

    public function test_emits_versioned_envelope_metadata(): void
    {
        $envelope = app(KeyEnvelope::class);

        $ciphertext = $envelope->encrypt('payload');
        $meta = $envelope->inspect($ciphertext);

        $this->assertSame(KeyEnvelope::VERSION_V1, $meta['v']);
        $this->assertSame(KeyEnvelope::ALG_V1, $meta['alg']);
        $this->assertNotEmpty($meta['kid']);
    }

    public function test_envelope_body_is_json_with_required_fields(): void
    {
        $envelope = app(KeyEnvelope::class);

        $ciphertext = $envelope->encrypt('payload');
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($ciphertext, true);

        $this->assertIsArray($decoded);
        foreach (['v', 'alg', 'kid', 'body'] as $field) {
            $this->assertArrayHasKey($field, $decoded);
        }
    }

    public function test_decrypting_a_malformed_envelope_raises(): void
    {
        $envelope = app(KeyEnvelope::class);

        $this->expectException(InvalidEnvelopeException::class);
        $envelope->decrypt('not-json-at-all');
    }

    public function test_decrypting_an_envelope_missing_required_fields_raises(): void
    {
        $envelope = app(KeyEnvelope::class);

        $this->expectException(InvalidEnvelopeException::class);
        $envelope->decrypt(json_encode(['v' => 1, 'alg' => 'aes-256-laravel']));
    }

    public function test_decrypting_an_unknown_version_raises(): void
    {
        $envelope = app(KeyEnvelope::class);

        $futureEnvelope = json_encode([
            'v' => 99,
            'alg' => 'kyber-from-the-future',
            'kid' => 'kid:future',
            'body' => 'whatever',
        ]);

        $this->expectException(UnsupportedEnvelopeVersionException::class);
        $envelope->decrypt($futureEnvelope);
    }

    public function test_decrypting_a_version_algorithm_mismatch_raises(): void
    {
        $envelope = app(KeyEnvelope::class);
        $ciphertext = $envelope->encrypt('payload');
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($ciphertext, true, flags: JSON_THROW_ON_ERROR);
        $decoded['alg'] = KeyEnvelope::ALG_V2;

        $this->expectException(UnsupportedEnvelopeVersionException::class);
        $envelope->decrypt(json_encode($decoded, JSON_THROW_ON_ERROR));
    }

    public function test_pqc_feature_flag_writes_v2_and_v1_still_decrypts(): void
    {
        $envelope = app(KeyEnvelope::class);
        $v1 = $envelope->encrypt('historical');

        Config::set('crypto.pqc.enabled', true);
        $v2 = $envelope->encrypt('modern');
        $meta = $envelope->inspect($v2);

        $this->assertSame(KeyEnvelope::VERSION_V2, $meta['v']);
        $this->assertSame(KeyEnvelope::ALG_V2, $meta['alg']);
        $this->assertSame('modern', $envelope->decrypt($v2));
        $this->assertSame('historical', $envelope->decrypt($v1));
    }

    public function test_explicit_v2_round_trip_without_changing_call_sites(): void
    {
        $envelope = app(KeyEnvelope::class);

        $ciphertext = $envelope->encryptForVersion('post quantum payload', KeyEnvelope::VERSION_V2);
        $meta = $envelope->inspect($ciphertext);

        $this->assertSame(KeyEnvelope::VERSION_V2, $meta['v']);
        $this->assertStringStartsWith(KeyEnvelope::ALG_V2.':', $meta['kid']);
        $this->assertSame('post quantum payload', $envelope->decrypt($ciphertext));
    }

    public function test_key_id_changes_when_app_key_changes(): void
    {
        /** @var Encrypter $encrypter */
        $encrypter = app(Encrypter::class);

        Config::set('app.key', 'base64:'.base64_encode(str_repeat('A', 32)));
        $kidA = (new KeyEnvelope($encrypter))->inspect((new KeyEnvelope($encrypter))->encrypt('x'))['kid'];

        Config::set('app.key', 'base64:'.base64_encode(str_repeat('B', 32)));
        $kidB = (new KeyEnvelope($encrypter))->inspect((new KeyEnvelope($encrypter))->encrypt('x'))['kid'];

        $this->assertNotSame($kidA, $kidB);
        $this->assertStringStartsWith(KeyEnvelope::ALG_V1.':', $kidA);
        $this->assertStringStartsWith(KeyEnvelope::ALG_V1.':', $kidB);
    }
}
