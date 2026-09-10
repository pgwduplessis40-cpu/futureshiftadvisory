<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\EntrepreneurProfile;
use App\Models\User;
use App\Notifications\EntrepreneurDeactivationRequestedNotification;
use App\Services\Audit\AuditWriter;
use App\Services\Entrepreneurs\IdeaValidationCancellation;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly IdeaValidationCancellation $ideaValidationCancellation) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'deactivationRequestedAt' => $request->user()?->deactivation_requested_at?->toIso8601String(),
            'ideaValidationCancellation' => $request->user() instanceof User
                ? $this->ideaValidationCancellation->summaryFor($request->user())
                : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        abort(403);
    }

    public function requestDeactivation(Request $request, AuditWriter $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'confirm_deactivation' => ['accepted'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($user->deactivation_requested_at === null) {
            $user->forceFill([
                'deactivation_requested_at' => now(),
                'deactivation_requested_reason' => $validated['reason'] ?? null,
            ])->save();

            $profile = $user->user_type === User::TYPE_ENTREPRENEUR
                ? $user->entrepreneurProfile()
                    ->with('assignedAdvisor')
                    ->first()
                : null;

            if ($profile instanceof EntrepreneurProfile && $profile->assignedAdvisor instanceof User) {
                Notification::send(
                    $profile->assignedAdvisor,
                    new EntrepreneurDeactivationRequestedNotification($profile),
                );
            }

            $audit->record('user.deactivation_requested', subject: $user, actor: $user, after: [
                'user_id' => $user->getKey(),
                'user_type' => $user->user_type,
                'entrepreneur_profile_id' => $profile?->getKey(),
                'requested_at' => $user->deactivation_requested_at?->toIso8601String(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deactivation request submitted.')]);

        return to_route('profile.edit');
    }

    public function cancelIdeaValidation(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $request->validate([
            'confirm_cancellation' => ['accepted'],
        ]);

        $refund = $this->ideaValidationCancellation->cancel($user);

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with(
            'status',
            sprintf(
                'Your Idea Validation has been cancelled. Your account is now deactivated and your full refund of %s %s has been initiated to the original payment method.',
                $refund->currency,
                number_format((float) $refund->amount, 2),
            ),
        );
    }
}
