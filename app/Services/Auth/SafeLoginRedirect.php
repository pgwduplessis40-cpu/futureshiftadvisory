<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;

final class SafeLoginRedirect
{
    public function clearForbiddenIntendedUrl(Request $request, User $user): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || $intended === '') {
            return;
        }

        $path = parse_url($intended, PHP_URL_PATH);
        if (! is_string($path)) {
            return;
        }

        if (str_starts_with($path, '/admin') && $user->fsaRole() !== User::TYPE_SUPER_ADMIN) {
            $request->session()->forget('url.intended');
        }
    }
}
