<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('CAR_ADMIN_EMAIL');

        if (! is_string($email) || trim($email) === '') {
            $this->command?->warn('Admin creation skipped: set CAR_ADMIN_EMAIL and CAR_ADMIN_PASSWORD to provision an administrator.');

            return;
        }

        $email = trim($email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('CAR_ADMIN_EMAIL must be a valid email address.');
        }

        // Never reset credentials or promote an existing customer on reseeding.
        if (User::where('email', $email)->exists()) {
            return;
        }

        $password = env('CAR_ADMIN_PASSWORD');

        if (! is_string($password) || strlen($password) < 12) {
            throw new \InvalidArgumentException('Set CAR_ADMIN_PASSWORD to at least 12 characters to create an administrator.');
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('CAR_ADMIN_NAME', 'Administrator'),
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
