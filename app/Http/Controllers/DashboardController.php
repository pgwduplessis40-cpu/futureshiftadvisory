<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Entrepreneurs\EntrepreneurInviteReconciler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->user_type === User::TYPE_ENTREPRENEUR) {
            app(EntrepreneurInviteReconciler::class)->reconcile($user);

            return to_route('portal.entrepreneur.dashboard');
        }

        if ($user instanceof User && $user->user_type === User::TYPE_NPO_BOARD_MEMBER) {
            return to_route('portal.npo-board.dashboard');
        }

        if (
            $user instanceof User
            && in_array($user->user_type, [User::TYPE_CLIENT_PRIMARY, User::TYPE_CLIENT_TEAM], true)
            && $user->accessibleClientIds() !== []
        ) {
            return to_route('portal.dashboard');
        }

        if ($user instanceof User && $this->usesStaffDashboard($user)) {
            return app()->call(StaffDashboardController::class, [
                'request' => $request,
            ]);
        }

        return Inertia::render('dashboard');
    }

    private function usesStaffDashboard(User $user): bool
    {
        return in_array($user->user_type, [
            User::TYPE_SUPER_ADMIN,
            User::TYPE_ADVISOR,
            User::TYPE_JUNIOR_ADVISOR,
            User::TYPE_ENTREPRENEUR_MENTOR,
            User::TYPE_BROKER,
            User::TYPE_COACH,
        ], true);
    }
}
