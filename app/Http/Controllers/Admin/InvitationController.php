<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\User;
use App\Services\Security\InviteIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InvitationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeInvites($request);

        return Inertia::render('admin/invitations/Index', [
            'invites' => InviteToken::query()
                ->latest()
                ->limit(50)
                ->get(['id', 'email', 'target_role', 'target_user_type', 'expires_at', 'accepted_at']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeInvites($request);

        return Inertia::render('admin/invitations/Create', [
            'userTypes' => User::userTypes(),
        ]);
    }

    public function store(Request $request, InviteIssuer $issuer): RedirectResponse
    {
        $this->authorizeInvites($request);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'target_user_type' => ['required', 'string'],
            'target_role' => ['required', 'string', 'max:80'],
        ]);

        $issuer->issue(
            email: $validated['email'],
            targetUserType: $validated['target_user_type'],
            targetRole: $validated['target_role'],
            issuedBy: $request->user(),
        );

        return to_route('admin.invitations.index');
    }

    private function authorizeInvites(Request $request): void
    {
        abort_unless($request->user()?->user_type === User::TYPE_SUPER_ADMIN, 403);
    }
}
