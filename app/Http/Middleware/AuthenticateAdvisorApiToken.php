<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AdvisorApiClient;
use App\Services\Audit\AuditWriter;
use App\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateAdvisorApiToken
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly RequestContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Advisor API token required.'], 401);
        }

        /** @var AdvisorApiClient|null $client */
        $client = AdvisorApiClient::query()
            ->with('advisor')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $client instanceof AdvisorApiClient || ! $client->isApproved() || $client->advisor === null) {
            return response()->json(['message' => 'Advisor API client is not approved.'], 403);
        }

        Auth::setUser($client->advisor);
        $this->context->apply($client->advisor->fsaRole(), $client->advisor->accessibleClientIds(), (string) $client->advisor->id);
        $request->attributes->set('advisor_api_client', $client);
        $request->attributes->set('advisor_api_token_hash', $client->token_hash);

        $response = $next($request);

        $client->forceFill(['last_used_at' => now()])->save();
        $this->audit->record('advisor_api.call', subject: $client, actor: $client->advisor, after: [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'scopes' => $client->scopes,
        ]);

        return $response;
    }
}
