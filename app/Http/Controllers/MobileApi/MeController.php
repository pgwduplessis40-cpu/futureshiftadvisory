<?php

declare(strict_types=1);

namespace App\Http\Controllers\MobileApi;

use App\Http\Controllers\Controller;
use App\Models\DeviceRegistration;
use App\Models\User;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $device = $request->attributes->get('mobile_device');

        return [
            'user' => [
                'id' => (string) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->fsaRole(),
                'user_type' => $user->user_type,
            ],
            'device' => $device instanceof DeviceRegistration ? [
                'id' => (string) $device->getKey(),
                'platform' => $device->platform,
                'device_name' => $device->device_name,
                'app_version' => $device->app_version,
                'last_used_at' => $device->last_used_at?->toIso8601String(),
            ] : null,
            'client_ids' => $user->accessibleClientIds(),
        ];
    }
}
