<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingConnection;
use App\Models\PracticeAccountingConnection;
use App\Models\User;
use App\Services\Accounting\PracticeAccountingConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class PracticeAccountingConnectionController extends Controller
{
    public function connect(
        Request $request,
        string $provider,
        PracticeAccountingConnector $connector,
    ): Response|RedirectResponse {
        $this->assertProvider($provider);
        $authorizeUrl = $connector->authorizeUrl($this->user($request), $provider);

        if ($request->header('X-Inertia')) {
            return Inertia::location($authorizeUrl);
        }

        return redirect()->away($authorizeUrl);
    }

    public function callback(
        Request $request,
        string $provider,
        PracticeAccountingConnector $connector,
    ): RedirectResponse {
        $this->assertProvider($provider);

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $connector->connectFromCallback(
                user: $this->user($request),
                provider: $provider,
                code: (string) $validated['code'],
                state: (string) $validated['state'],
            );
        } catch (InvalidArgumentException) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Practice Xero connection failed. Check the redirect URI, scopes, and credentials.'),
            ]);

            return to_route('admin.integration-credentials.index')
                ->with('error', 'practice-accounting-connection-invalid');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Practice Xero connection saved. Future accepted proposals can now create invoice batches.'),
        ]);

        return to_route('admin.integration-credentials.index')
            ->with('status', "practice-accounting-connected:{$provider}");
    }

    public function revoke(
        Request $request,
        PracticeAccountingConnection $practiceAccountingConnection,
        PracticeAccountingConnector $connector,
    ): RedirectResponse {
        $connector->revoke($practiceAccountingConnection, $this->user($request));

        return to_route('admin.integration-credentials.index')
            ->with('status', "practice-accounting-revoked:{$practiceAccountingConnection->provider}");
    }

    private function assertProvider(string $provider): void
    {
        abort_unless($provider === AccountingConnection::PROVIDER_XERO, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
