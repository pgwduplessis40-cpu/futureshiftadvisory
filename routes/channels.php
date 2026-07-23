<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\CoBrowse\CoBrowsePresence;
use App\Services\ScreenShare\ScreenSharePresence;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('screen-share.connection.{connection}', function (User $user, string $connection): bool {
    $secret = request()->header('X-Screen-Share-Connection-Secret');

    if (! is_string($secret) || strlen($secret) !== 64) {
        return false;
    }

    try {
        app(ScreenSharePresence::class)->assertConnection($user, $connection, $secret);

        return true;
    } catch (\Throwable) {
        return false;
    }
});

Broadcast::channel('co-browse.connection.{connection}', function (User $user, string $connection): bool {
    $secret = request()->header('X-Co-Browse-Connection-Secret');

    if (! is_string($secret) || strlen($secret) !== 64) {
        return false;
    }

    try {
        app(CoBrowsePresence::class)->assertConnection($user, $connection, $secret);

        return true;
    } catch (\Throwable) {
        return false;
    }
});
