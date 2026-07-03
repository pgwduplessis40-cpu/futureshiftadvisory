<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
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
            'backUrl' => $this->safeReturnUrl($request->query('return_to')),
        ]);
    }

    public function store(Request $request, InviteIssuer $issuer): RedirectResponse
    {
        $this->authorizeInvites($request);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'target_user_type' => ['required', 'string'],
            'target_role' => ['required', 'string', 'max:80'],
            'return_to' => ['nullable', 'string', 'max:255'],
        ]);

        $issuer->issue(
            email: $validated['email'],
            targetUserType: $validated['target_user_type'],
            targetRole: $validated['target_role'],
            issuedBy: $request->user(),
            deliver: true,
        );

        return redirect($this->safeReturnUrl($validated['return_to'] ?? null));
    }

    private function safeReturnUrl(mixed $value): string
    {
        $path = is_string($value) ? trim($value) : '';

        return in_array($path, [
            route('admin.staff.index', absolute: false),
            route('admin.invitations.index', absolute: false),
        ], true)
            ? $path
            : route('admin.invitations.index', absolute: false);
    }

    private function authorizeInvites(Request $request): void
    {
        $user = $request->user();

        abort_unless($user?->user_type === User::TYPE_SUPER_ADMIN, 403);
        abort_unless($user->can(Permission::USERS_INVITE->value) || $user->fsaRole() === User::TYPE_SUPER_ADMIN, 403);
    }
}
