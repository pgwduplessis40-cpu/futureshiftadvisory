<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Notifications\NotificationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationCenter $center) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('notifications/Index', [
            'notifications' => $this->center->list($user),
            'summary' => $this->center->summary($user),
            'markAllReadUrl' => route('notifications.mark-all-read', absolute: false),
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $user = $this->user($request);
        $record = $user->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $record->markAsRead();

        return back()->with('status', 'notification-read');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->user($request)
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back()->with('status', 'notifications-read');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
