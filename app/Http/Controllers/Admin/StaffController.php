<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InviteToken;
use App\Models\User;
use App\Services\Audit\AuditWriter;
use App\Services\Clients\AdvisorClientCapacity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class StaffController extends Controller
{
    public function __construct(
        private readonly AdvisorClientCapacity $clientCapacity,
    ) {}

    public function index(): Response
    {
        $staffTypes = $this->staffUserTypes();
        $staff = User::query()
            ->whereIn('user_type', $staffTypes)
            ->orderBy('user_type')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->userPayload($user))
            ->values()
            ->all();

        $pendingInvites = InviteToken::query()
            ->whereIn('target_user_type', $staffTypes)
            ->latest()
            ->limit(25)
            ->get(['id', 'email', 'target_role', 'target_user_type', 'expires_at', 'accepted_at'])
            ->map(fn (InviteToken $invite): array => [
                'id' => $invite->id,
                'email' => $invite->email,
                'target_role' => $invite->target_role,
                'target_user_type' => $invite->target_user_type,
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'accepted_at' => $invite->accepted_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/staff/Index', [
            'staff' => $staff,
            'pendingInvites' => $pendingInvites,
            'staffTypes' => $staffTypes,
            'inviteUrl' => route('admin.invitations.create', [
                'return_to' => route('admin.staff.index', absolute: false),
            ], absolute: false),
        ]);
    }

    public function update(Request $request, User $user, AuditWriter $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->user_type === User::TYPE_SUPER_ADMIN, 403);

        $staffTypes = $this->staffUserTypes();
        abort_unless(in_array($user->user_type, $staffTypes, true), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_type' => ['required', 'string', Rule::in($staffTypes)],
            'primary_role' => ['required', 'string', Rule::in($staffTypes)],
            'session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'advisor_client_capacity_limit' => [
                'nullable',
                'required_if:user_type,'.User::TYPE_ADVISOR.','.User::TYPE_JUNIOR_ADVISOR,
                'integer',
                'min:1',
                'max:500',
            ],
            'suspended' => ['required', 'boolean'],
            'suspended_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ((string) $actor->getKey() === (string) $user->getKey()) {
            if ($validated['user_type'] !== User::TYPE_SUPER_ADMIN || $validated['primary_role'] !== User::TYPE_SUPER_ADMIN) {
                throw ValidationException::withMessages([
                    'user_type' => 'You cannot remove your own super-admin access.',
                ]);
            }

            if ((bool) $validated['suspended']) {
                throw ValidationException::withMessages([
                    'suspended' => 'You cannot suspend your own account.',
                ]);
            }
        }

        $before = $this->userPayload($user);

        DB::transaction(function () use ($user, $validated): void {
            $suspended = (bool) $validated['suspended'];
            $user->forceFill([
                'name' => $validated['name'],
                'user_type' => $validated['user_type'],
                'primary_role' => $validated['primary_role'],
                'session_timeout_minutes' => (int) $validated['session_timeout_minutes'],
                'advisor_client_capacity_limit' => $this->isAdvisorType((string) $validated['user_type'])
                    ? (int) $validated['advisor_client_capacity_limit']
                    : null,
                'suspended_at' => $suspended ? ($user->suspended_at ?? now()) : null,
                'suspended_reason' => $suspended ? ($validated['suspended_reason'] ?: 'Suspended by administrator') : null,
            ])->save();

            if (Role::query()->where('name', $validated['primary_role'])->where('guard_name', 'web')->exists()) {
                $user->syncRoles([$validated['primary_role']]);
            }
        });

        $audit->record('admin.staff.updated', subject: $user->refresh(), actor: $actor, before: $before, after: $this->userPayload($user));

        return to_route('admin.staff.index')->with('status', 'staff-updated');
    }

    /**
     * @return array<int, string>
     */
    private function staffUserTypes(): array
    {
        return [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADVISOR,
            User::TYPE_JUNIOR_ADVISOR,
            User::TYPE_ENTREPRENEUR_MENTOR,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $isAdvisor = $this->isAdvisorType($user->user_type);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'primary_role' => $user->primary_role,
            'session_timeout_minutes' => $user->session_timeout_minutes,
            'advisor_client_capacity_limit' => $user->advisor_client_capacity_limit,
            'client_capacity' => $isAdvisor ? $this->clientCapacity->summary($user) : null,
            'mfa_enabled_at' => $user->mfa_enabled_at?->toIso8601String(),
            'suspended_at' => $user->suspended_at?->toIso8601String(),
            'suspended_reason' => $user->suspended_reason,
            'update_url' => route('admin.staff.update', $user, absolute: false),
        ];
    }

    private function isAdvisorType(?string $userType): bool
    {
        return in_array($userType, [User::TYPE_ADVISOR, User::TYPE_JUNIOR_ADVISOR], true);
    }
}
