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
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class AccountingConnectionController extends Controller
{
    public function connect(
        Request $request,
        Client $client,
        string $provider,
        AccountingConnector $connector,
    ): Response|RedirectResponse {
        Gate::authorize('update', $client);
        $this->assertProvider($provider);
        $user = $this->user($request);
        $authorizeUrl = $connector->authorizeUrl($client, $user, $provider);

        if ($request->header('X-Inertia')) {
            return Inertia::location($authorizeUrl);
        }

        return redirect()->away($authorizeUrl);
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
                redirectUri: $connector->legacyCallbackUrl($client, $provider),
            );
        } catch (InvalidArgumentException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Accounting connection failed. Check the provider configuration and reconnect.'),
            ]);

            return to_route('advisor.clients.show', $client)
                ->with('error', 'accounting-connection-invalid');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Accounting connection saved.'),
        ]);

        return to_route('advisor.clients.show', $client)
            ->with('status', "accounting-connected:{$connection->provider}");
    }

    public function callbackFromState(
        Request $request,
        string $provider,
        AccountingConnector $connector,
    ): RedirectResponse {
        $this->assertProvider($provider);

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $client = $connector->clientFromState(
                state: (string) $validated['state'],
                provider: $provider,
                user: $this->user($request),
            );

            Gate::authorize('update', $client);

            $connection = $connector->connectFromCallback(
                client: $client,
                user: $this->user($request),
                provider: $provider,
                code: (string) $validated['code'],
                state: (string) $validated['state'],
                redirectUri: $connector->callbackUrl($provider),
            );
        } catch (InvalidArgumentException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Accounting connection failed. Check the provider configuration and reconnect.'),
            ]);

            return to_route('advisor.clients.index')
                ->with('error', 'accounting-connection-invalid');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Accounting connection saved.'),
        ]);

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
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This accounting connection has been revoked. Reconnect before pulling data.'),
            ]);

            return to_route('advisor.clients.show', $client)
                ->with('error', 'accounting-connection-revoked');
        } catch (InvalidArgumentException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Accounting pull failed. Check the Xero organisation, tenant access, and report scopes.'),
            ]);

            return to_route('advisor.clients.show', $client)
                ->with('error', 'accounting-pull-failed');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Accounting snapshot pulled.'),
        ]);

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
