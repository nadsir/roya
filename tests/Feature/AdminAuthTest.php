<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_log_in_use_admin_api_and_log_out(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('secret-password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@example.com');

        $this->withToken($token)
            ->getJson('/api/admin/products/meta')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/admin/logout')
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $admin->id,
            'name' => 'admin-dashboard',
        ]);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/admin/me')
            ->assertUnauthorized();
    }

    public function test_non_admin_login_is_rejected_without_disclosing_role(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'password' => Hash::make('secret-password'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
