<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Create or update the single administrator account.
     */
    public function run(): void
    {
        $admin = config('registry.admin');

        if (blank($admin['password'])) {
            throw new RuntimeException('Set ADMIN_PASSWORD in the .env file before seeding the administrator account.');
        }

        User::query()->updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => $admin['password'],
                'email_verified_at' => now(),
            ],
        );
    }
}
