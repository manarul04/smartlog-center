<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Accounts are idempotent: re-running the seeder restores the known
     * password and role instead of creating duplicates.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => env('APP_ADMIN_NAME', 'Administrator'),
                'username' => env('APP_ADMIN_USERNAME', 'admin'),
                'email' => env('APP_ADMIN_EMAIL', 'admin@wazuh.local'),
                'password' => env('APP_ADMIN_PASSWORD', 'Admin#2026!secure'),
                'role' => 'admin',
            ],
            [
                'name' => 'Security Analyst',
                'username' => 'analyst',
                'email' => 'analyst@wazuh.local',
                'password' => 'Analyst#2026!secure',
                'role' => 'analyst',
            ],
            [
                'name' => 'Read Only',
                'username' => 'viewer',
                'email' => 'viewer@wazuh.local',
                'password' => 'Viewer#2026!secure',
                'role' => 'viewer',
            ],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrNew(['username' => $account['username']]);

            $user->fill([
                'name' => $account['name'],
                'email' => $account['email'],
                'role' => $account['role'],
                'is_active' => true,
            ]);

            // Only reset the password when the account is first created, so a
            // change made through the UI is not silently reverted by a reseed.
            if (! $user->exists) {
                $user->password = Hash::make($account['password']);
            }

            $user->save();
        }
    }
}
