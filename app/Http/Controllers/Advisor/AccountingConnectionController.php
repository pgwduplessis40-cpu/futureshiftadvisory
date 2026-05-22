<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\AccountingConnection;
use App\Models\Client;
use App\Models\User;
use App\Services\Accounting\AccountingConnectionRevokedException;
use App\Services\Accounting\AccountingConnector;
use App\Services\Accounting\FinancialSnapshotPuller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class AccountingConnectionController extends Controller
{
    public function connect(
        Request $request,
        Client $client,
        string $provider,
        AccountingConnector $connector,
    ): RedirectResponse {
        Gate::authorize('update', $client);
        $this->assertProvider($provider);
        $user = $this->user($request);

        return redirect()->away($connector->authorizeUrl($client, $user, $provider));
    }

    public function callback(
        Request $request,
        Client $client,
        string $provider,
        AccountingConnector $connector,
    ): RedirectResponse {
        Gate::authorize('update', $client);
        $this->assertProvider($provider);

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $connection = $connector->connectFromCallback(
                client: $client,
                user: $this->user($request),
                provider: $provider,
                code: $validated['code'],
                state: $validated['state'],
            );
        } catch (InvalidArgumentException) {
            return to_route('advisor.clients.show', $client)
                ->with('error', 'accounting-connection-invalid');
        }

        return to_route('advisor.clients.show', $client)
            ->with('status', "accounting-connected:{$connection->provider}");
    }

    public function pull(
        Request $request,
        Client $client,
        AccountingConnection $accountingConnection,
        FinancialSnapshotPuller $puller,
    ): RedirectResponse {
        Gate::authorize('update', $client);
        $this->assertBelongsToClient($accountingConnection, $client);

        try {
            $snapshot = $puller->pull($accountingConnection, $this->user($request));
        } catch (AccountingConnectionRevokedException) {
            return to_route('advisor.clients.show', $client)
                ->with('error', 'accounting-connection-revoked');
        }

        return to_route('advisor.clients.show', $client)
            ->with('status', "financial-snapshot-pulled:{$snapshot->provider}");
    }

    public function revoke(
        Request $request,
        Client $client,
        AccountingConnection $accountingConnection,
        AccountingConnector $connector,
    ): RedirectResponse {
        Gate::authorize('update', $client);
        $this->assertBelongsToClient($accountingConnection, $client);

        $connector->revoke($accountingConnection, $this->user($request));

        return to_route('advisor.clients.show', $client)
            ->with('status', "accounting-revoked:{$accountingConnection->provider}");
    }

    private function assertBelongsToClient(AccountingConnection $connection, Client $client): void
    {
        abort_unless((string) $connection->client_id === (string) $client->getKey(), 404);
    }

    private function assertProvider(string $provider): void
    {
        abort_unless(AccountingConnection::validProvider($provider), 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
