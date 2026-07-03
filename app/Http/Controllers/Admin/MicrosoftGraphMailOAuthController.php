<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mail\MicrosoftGraphMailOAuthConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class MicrosoftGraphMailOAuthController extends Controller
{
    public function connect(Request $request, MicrosoftGraphMailOAuthConnector $connector): Response|RedirectResponse
    {
        try {
            $authorizeUrl = $connector->authorizeUrl($this->user($request));
        } catch (InvalidArgumentException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Microsoft Graph mail connection failed: :message', ['message' => $exception->getMessage()]),
            ]);

            return to_route('admin.project-settings.index')
                ->with('error', 'graph-mail-oauth-config-missing');
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location($authorizeUrl);
        }

        return redirect()->away($authorizeUrl);
    }

    public function callback(Request $request, MicrosoftGraphMailOAuthConnector $connector): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        try {
            $connection = $connector->connectFromCallback(
                user: $this->user($request),
                code: (string) $validated['code'],
                state: (string) $validated['state'],
            );
        } catch (InvalidArgumentException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Microsoft Graph mail connection failed: :message', ['message' => $exception->getMessage()]),
            ]);

            return to_route('admin.project-settings.index')
                ->with('error', 'graph-mail-oauth-invalid');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Microsoft Graph mail connected for :mailbox.', ['mailbox' => $connection->mailbox_email]),
        ]);

        return to_route('admin.project-settings.index')
            ->with('status', 'graph-mail-oauth-connected');
    }

    public function disconnect(Request $request, MicrosoftGraphMailOAuthConnector $connector): RedirectResponse
    {
        $connector->disconnect($this->user($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Microsoft Graph mail disconnected.'),
        ]);

        return to_route('admin.project-settings.index')
            ->with('status', 'graph-mail-oauth-disconnected');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
