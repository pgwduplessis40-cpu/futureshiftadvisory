<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use App\Models\AccountingConnection;
use App\Models\CalendarConnection;
use App\Models\Client;
use App\Models\User;
use App\Services\Storage\KeyEnvelope;
use App\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EnvelopeRewrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewraps_v1_envelopes_to_v2_and_records_idempotent_rotation(): void
    {
        app(RequestContext::class)->apply('system', []);

        $client = Client::query()->create([
            'engagement_type' => 'standard_advisory',
            'legal_name' => 'Harbour Hive Limited',
            'created_by_user_id' => User::factory()->create()->id,
        ]);
        $advisor = User::factory()->create(['user_type' => User::TYPE_ADVISOR]);
        $envelope = app(KeyEnvelope::class);
        $v1 = $envelope->encrypt(json_encode(['access_token' => 'seed'], JSON_THROW_ON_ERROR));

        $connection = AccountingConnection::query()->create([
            'client_id' => $client->id,
            'provider' => AccountingConnection::PROVIDER_XERO,
            'status' => AccountingConnection::STATUS_CONNECTED,
            'token_envelope' => $v1,
            'token_envelope_meta' => $envelope->inspect($v1),
            'scopes' => ['offline_access'],
            'connected_by_user_id' => $advisor->id,
            'connected_at' => now(),
        ]);
        $calendarAccessV1 = $envelope->encrypt('calendar-access-token');
        $calendarRefreshV1 = $envelope->encrypt('calendar-refresh-token');
        $calendarConnection = CalendarConnection::query()->create([
            'user_id' => $advisor->id,
            'provider' => CalendarConnection::PROVIDER_GOOGLE,
            'external_account_id' => 'google-rewrap-fixture',
            'external_account_email' => 'rewrap-calendar@example.test',
            'access_token_envelope' => $calendarAccessV1,
            'access_token_envelope_meta' => $envelope->inspect($calendarAccessV1),
            'refresh_token_envelope' => $calendarRefreshV1,
            'refresh_token_envelope_meta' => $envelope->inspect($calendarRefreshV1),
            'status' => CalendarConnection::STATUS_CONNECTED,
        ]);

        Config::set('crypto.pqc.enabled', true);

        $this->artisan('envelopes:rewrap', ['--target' => KeyEnvelope::VERSION_V2])
            ->assertSuccessful();

        $connection->refresh();
        $meta = $envelope->inspect($connection->token_envelope);

        $this->assertSame(KeyEnvelope::VERSION_V2, $meta['v']);
        $this->assertSame(KeyEnvelope::ALG_V2, $meta['alg']);
        $this->assertSame(['access_token' => 'seed'], json_decode($envelope->decrypt($connection->token_envelope), true, flags: JSON_THROW_ON_ERROR));
        $this->assertDatabaseHas('crypto_rotations', [
            'source_table' => 'accounting_connections',
            'source_column' => 'token_envelope',
            'source_id' => $connection->id,
            'status' => 'rewrapped',
        ]);
        $calendarConnection->refresh();
        $this->assertSame(KeyEnvelope::VERSION_V2, $envelope->inspect($calendarConnection->access_token_envelope)['v']);
        $this->assertSame(KeyEnvelope::VERSION_V2, $envelope->inspect((string) $calendarConnection->refresh_token_envelope)['v']);
        $this->assertSame('calendar-access-token', $envelope->decrypt($calendarConnection->access_token_envelope));
        $this->assertSame('calendar-refresh-token', $envelope->decrypt((string) $calendarConnection->refresh_token_envelope));
        $this->assertDatabaseHas('crypto_rotations', [
            'source_table' => 'calendar_connections',
            'source_column' => 'access_token_envelope',
            'source_id' => $calendarConnection->id,
            'status' => 'rewrapped',
        ]);
        $this->assertDatabaseHas('crypto_rotations', [
            'source_table' => 'calendar_connections',
            'source_column' => 'refresh_token_envelope',
            'source_id' => $calendarConnection->id,
            'status' => 'rewrapped',
        ]);

        $firstCount = DB::table('crypto_rotations')->count();

        $this->artisan('envelopes:rewrap', ['--target' => KeyEnvelope::VERSION_V2])
            ->assertSuccessful();

        $this->assertSame($firstCount + 3, DB::table('crypto_rotations')->count());
        $this->assertDatabaseHas('crypto_rotations', [
            'source_table' => 'accounting_connections',
            'source_column' => 'token_envelope',
            'source_id' => $connection->id,
            'status' => 'skipped',
        ]);
        $this->assertDatabaseHas('crypto_rotations', [
            'source_table' => 'calendar_connections',
            'source_column' => 'access_token_envelope',
            'source_id' => $calendarConnection->id,
            'status' => 'skipped',
        ]);
        $this->assertDatabaseHas('crypto_rotations', [
            'source_table' => 'calendar_connections',
            'source_column' => 'refresh_token_envelope',
            'source_id' => $calendarConnection->id,
            'status' => 'skipped',
        ]);
    }
}
