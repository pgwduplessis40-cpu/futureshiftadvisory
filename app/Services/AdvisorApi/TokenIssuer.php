<?php

declare(strict_types=1);

namespace App\Services\AdvisorApi;

use App\Models\AdvisorApiClient;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use Illuminate\Support\Str;

final class TokenIssuer
{
    public function __construct(private readonly AuditWriter $audit) {}

    /**
     * @param  array<int, string>  $scopes
     * @return array{client: AdvisorApiClient, token: string}
     */
    public function issue(
        string $name,
        User $advisor,
        User $approvedBy,
        array $scopes,
        int $rateLimitPerMinute = 60,
    ): array {
        $token = 'fsa_api_'.Str::random(48);

        /** @var AdvisorApiClient $client */
        $client = AdvisorApiClient::query()->create([
            'name' => $name,
            'advisor_user_id' => $advisor->id,
            'token_hash' => hash('sha256', $token),
            'scopes' => array_values(array_unique($scopes)),
            'status' => AdvisorApiClient::STATUS_ACTIVE,
            'rate_limit_per_minute' => max(1, $rateLimitPerMinute),
            'approved_by_user_id' => $approvedBy->id,
            'approved_at' => now(),
        ]);

        $this->audit->record('advisor_api.client_approved', subject: $client, actor: $approvedBy, after: [
            'advisor_user_id' => $advisor->id,
            'scopes' => $client->scopes,
            'token_stored' => 'sha256_hash_only',
        ]);

        return ['client' => $client, 'token' => $token];
    }
}
