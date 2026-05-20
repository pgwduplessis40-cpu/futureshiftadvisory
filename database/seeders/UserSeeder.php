<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@futureshiftadvisory.com'],
            [
                'name' => 'Admin User',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'user_type' => User::TYPE_SUPER_ADMIN,
                'primary_role' => User::TYPE_SUPER_ADMIN,
                'last_password_set_at' => now(),
            ]
        );

        $user->forceFill([
            'user_type' => User::TYPE_SUPER_ADMIN,
            'primary_role' => User::TYPE_SUPER_ADMIN,
        ])->save();

        if (Role::query()->where('name', User::TYPE_SUPER_ADMIN)->where('guard_name', 'web')->exists()) {
            $user->syncRoles([User::TYPE_SUPER_ADMIN]);
        }
    }
}
